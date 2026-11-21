<?php

namespace Tests\Browser;

use App\Models\TVClubSocialAccount;
use App\Models\TVClubSocialReportVersion;
use App\Models\User;
use App\Services\TVClubSocialService;
use Carbon\CarbonImmutable;

class TVClubSocialBrowserTest extends EquipmentBrowserTest
{
    protected function setUpEquipmentFixtures(): void
    {
        parent::setUpEquipmentFixtures();
        $actor = $this->actor(User::ROLE_TVCLUB);
        $service = app(TVClubSocialService::class);
        $account = $service->createAccount($actor, 'tiktok', $this->prefix, 'qa-'.$this->prefix, 'Akun Media QA', 'https://tiktok.com/@wpitv');
        $post = $service->savePost($actor, $account->id, null, 0, [
            'title' => '=Unggahan QA Browser', 'content_type' => 'short_video', 'responsible_label' => 'Koordinator QA',
            'url' => 'https://tiktok.com/@wpitv/video/123',
        ], CarbonImmutable::parse('2026-10-10 10:30', 'Asia/Jakarta'));
        $observed = CarbonImmutable::parse('2026-10-11 08:30', 'Asia/Jakarta');
        $service->observePost($actor, $account->id, $post->id, $observed, ['views' => 0, 'shares' => 5]);
        $service->observeAccount($actor, $account->id, $observed, ['followers' => 25]);
        if (getenv('WPITV_SOCIAL_RECAP_QA') === 'true') {
            $version = $service->publish($actor, '2026-10', 0);
            fwrite(STDOUT, PHP_EOL.'Social QA report: /admin/tvclub/social-reports/'.$version->id.PHP_EOL);
        }
    }

    protected function cleanUpEquipmentFixtures(): void
    {
        $this->primary->transaction(function (): void {
            $accounts = TVClubSocialAccount::where('account_key', $this->prefix)->pluck('id');
            $posts = $this->primary->table('tvclub_social_posts')->whereIn('account_id', $accounts)->pluck('id');
            $this->primary->table('tvclub_social_events')->whereIn('actor_id', $this->owned['users'])->delete();
            $this->primary->table((new TVClubSocialReportVersion)->getTable())->whereIn('published_by', $this->owned['users'])->delete();
            foreach (['post' => $posts, 'account' => $accounts] as $scope => $ids) {
                $rows = $this->primary->table('tvclub_social_'.$scope.'_observations')->whereIn($scope.'_id', $ids);
                foreach ((clone $rows)->orderByDesc('id')->pluck('id') as $id) {
                    (clone $rows)->where('id', $id)->delete();
                }
            }
            $this->primary->table('tvclub_social_posts')->whereIn('account_id', $accounts)->delete();
            $this->primary->table('tvclub_social_accounts')->whereIn('id', $accounts)->delete();
        });
        parent::cleanUpEquipmentFixtures();
    }
}
