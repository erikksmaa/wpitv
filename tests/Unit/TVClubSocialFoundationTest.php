<?php

namespace Tests\Unit;

use App\Exceptions\TVClubSocialException;
use App\Models\Role;
use App\Models\TVClubSocialAccountObservation;
use App\Models\TVClubSocialEvent;
use App\Models\TVClubSocialPostObservation;
use App\Models\TVClubSocialReportVersion;
use App\Models\User;
use App\Policies\TVClubSocialPolicy;
use App\Support\TVClubSocialRules as Rules;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\TestCase;

class TVClubSocialFoundationTest extends TestCase
{
    public function test_active_role_matrix(): void
    {
        $policy = new TVClubSocialPolicy;
        foreach (User::ADMIN_ROLES as $role) {
            foreach ([true, false] as $active) {
                $actor = (new User)->forceFill(['status' => $active])->setRelation('role', (new Role)->forceFill(['name' => $role]));
                $read = $active && in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_TVCLUB], true);
                $write = $active && $role === User::ROLE_TVCLUB;
                $this->assertSame($read, $policy->viewAny($actor));
                $this->assertSame($read, $policy->view($actor));
                $this->assertSame($read, $policy->export($actor));
                $this->assertSame($read, $policy->manageAccounts($actor));
                $this->assertSame($write, $policy->manageContent($actor));
                $this->assertSame($write, $policy->publish($actor));
            }
        }
    }

    public function test_platform_urls_are_normalized_without_following_redirects(): void
    {
        $this->assertSame(['url' => 'https://www.tiktok.com/@wpitv/video/123/', 'key' => '123'], Rules::postUrl('tiktok', 'https://tiktok.com/@wpitv/video/123?track=1'));
        $this->assertSame(['url' => 'https://www.instagram.com/reel/Ab_9/', 'key' => 'Ab_9'], Rules::postUrl('instagram', 'https://Instagram.com/reels/Ab_9/#fragment'));
        $this->assertSame(['url' => 'https://www.instagram.com/stories/wp/987/', 'key' => '987'], Rules::postUrl('instagram', 'https://instagram.com/stories/wp/987/'));
        $this->assertSame(['url' => 'https://www.facebook.com/permalink.php?story_fbid=901&id=432', 'key' => '901'], Rules::postUrl('facebook', 'https://m.facebook.com/story.php?id=432&story_fbid=901&tracking=3'));
        $this->assertSame('https://www.facebook.com/profile.php?id=432', Rules::profileUrl('facebook', 'https://m.facebook.com/profile.php?id=432&track=1'));
        foreach ([
            ['tiktok', 'https://vm.tiktok.com/short/'],
            ['tiktok', 'https://www.tiktok.com.example.test/@wpitv/video/123'],
            ['tiktok', 'https://name@tiktok.com/@wpitv/video/123'],
            ['tiktok', 'http://tiktok.com/@wpitv/video/123'],
            ['tiktok', 'https://tiktok.com:443/@wpitv/video/123'],
            ['instagram', 'https://instagram.com/p/%2FAb/'],
            ['facebook', 'https://facebook.com/watch/?v=123&v=456'],
            ['facebook', 'https://facebook.com/story.php?story_fbid=123'],
            ['facebook', 'https://instagram.com/p/Ab/'],
        ] as [$platform, $url]) {
            $this->invalid('url', fn () => Rules::postUrl($platform, $url));
        }
    }

    public function test_metrics_distinguish_zero_null_scope_and_overflow(): void
    {
        $this->assertSame(['views' => 0, 'likes' => null, 'comments' => null, 'shares' => 4],
            Rules::metrics(['views' => '0', 'comments' => '', 'shares' => 4], Rules::POST_METRICS));
        $this->assertSame(['followers' => PHP_INT_MAX], Rules::metrics(['followers' => (string) PHP_INT_MAX], Rules::ACCOUNT_METRICS));
        foreach ([-1, '-1', '18446744073709551616', '1e3', 1.0, false] as $value) {
            $this->invalid('followers', fn () => Rules::metrics(['followers' => $value], Rules::ACCOUNT_METRICS));
        }
        $this->invalid('metrics', fn () => Rules::metrics(['followers' => 2], Rules::POST_METRICS));
        $this->invalid('metrics', fn () => Rules::metrics(['likes' => 2], Rules::ACCOUNT_METRICS));
    }

    public function test_content_types_and_wib_year_boundary(): void
    {
        $this->assertSame('short_video', Rules::contentType('tiktok', 'short_video'));
        $this->assertSame('carousel', Rules::contentType('instagram', 'carousel'));
        $this->assertSame('link', Rules::contentType('facebook', 'link'));
        $this->invalid('content_type', fn () => Rules::contentType('facebook', 'story'));
        $this->invalid('platform', fn () => Rules::contentType('youtube', 'video'));
        $this->invalid('period', fn () => Rules::period('2026-13'));
        $december = Rules::period('2026-12');
        $this->assertSame('2026-11-30 17:00:00', $december->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-31 17:00:00', $december->addMonth()->utc()->format('Y-m-d H:i:s'));
        $this->assertTrue($december->addMonth()->equalTo(Rules::period('2027-01')));
    }

    public function test_correction_and_revision_require_a_reason_and_exact_version(): void
    {
        $this->assertNull(Rules::correction(null, null));
        $this->assertSame('Koreksi', Rules::correction(9, ' Koreksi '));
        $this->invalid('reason', fn () => Rules::correction(9, null));
        $this->invalid('reason', fn () => Rules::correction(null, 'Orphan'));
        $this->invalid('reason', fn () => Rules::correction(0, 'Invalid'));
        $this->assertSame([1, null], Rules::revision(0, 0, null));
        $this->assertSame([3, 'Revisi'], Rules::revision(2, 2, ' Revisi '));
        $this->invalid('reason', fn () => Rules::revision(2, 2, ' '));
        $this->invalid('reason', fn () => Rules::revision(0, 0, 'Revisi'));
        Rules::version(3, 3);
        try {
            Rules::revision(3, 2, 'Revisi');
            $this->fail('Stale revision accepted.');
        } catch (TVClubSocialException $exception) {
            $this->assertSame('SOCIAL_STALE', $exception->errorCode);
        }
    }

    public function test_existing_observations_versions_and_events_are_immutable_through_model_methods(): void
    {
        foreach ([new TVClubSocialPostObservation, new TVClubSocialAccountObservation, new TVClubSocialReportVersion, new TVClubSocialEvent] as $record) {
            $record->exists = true;
            foreach (['save', 'saveQuietly', 'delete', 'deleteQuietly', 'forceDelete', 'incrementQuietly'] as $method) {
                try {
                    $method === 'incrementQuietly' ? $record->$method('id') : $record->$method();
                    $this->fail('Mutation accepted: '.$method);
                } catch (LogicException $exception) {
                    $this->assertStringContainsString('TVClub social', $exception->getMessage());
                }
            }
        }
    }

    private function invalid(string $field, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Invalid input accepted: '.$field);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
