<?php

namespace Tests\Feature\Public;

use App\Models\TVClubGeneration;
use App\Models\TVClubMember;
use App\Models\TvClubOrganizationMember;
use App\Models\TvClubOrganizationPosition;
use App\Models\User;
use App\Models\WpitvOrganizationPosition;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationStructurePublicTest extends TestCase
{
    use DatabaseTransactions;

    public function test_about_shows_only_active_wpitv_structure_and_safe_person_identity(): void
    {
        $person = User::factory()->create([
            'name' => 'Nama Personel Publik',
            'username' => 'internal_org_username',
            'password' => 'internal-secret-value',
            'status' => true,
        ]);
        WpitvOrganizationPosition::create(['user_id' => $person->id, 'name' => 'Kepala Organisasi Publik', 'sort_order' => 0, 'status' => true]);
        WpitvOrganizationPosition::create(['name' => 'Posisi Organisasi Rahasia', 'sort_order' => 1, 'status' => false]);
        WpitvOrganizationPosition::create(['name' => 'Posisi Belum Terisi', 'sort_order' => 2, 'status' => true]);

        $response = $this->get(route('public.about'));

        $response->assertOk()
            ->assertSee('Struktur Organisasi WPiTV')
            ->assertSee('Kepala Organisasi Publik')
            ->assertSee('Nama Personel Publik')
            ->assertSee('Posisi Belum Terisi')
            ->assertSee('Belum ditetapkan')
            ->assertDontSee('Posisi Organisasi Rahasia')
            ->assertDontSee('internal_org_username')
            ->assertDontSee('internal-secret-value');
    }

    public function test_generation_detail_scopes_active_structure_and_never_renders_student_number(): void
    {
        $generationA = TVClubGeneration::create(['name' => 'Generasi Struktur A', 'year' => 2187, 'status' => true]);
        $generationB = TVClubGeneration::create(['name' => 'Generasi Struktur B', 'year' => 2188, 'status' => true]);
        $memberA = TVClubMember::create(['generation_id' => $generationA->id, 'student_number' => 'NIM-PRIVATE-2187', 'name' => 'Anggota Struktur Publik', 'status' => true]);
        $memberInactive = TVClubMember::create(['generation_id' => $generationA->id, 'student_number' => 'NIM-PRIVATE-INACTIVE', 'name' => 'Anggota Organisasi Nonaktif', 'status' => false]);
        $positionA = TvClubOrganizationPosition::create(['generation_id' => $generationA->id, 'name' => 'Ketua Struktur A', 'sort_order' => 0, 'status' => true]);
        $positionInactive = TvClubOrganizationPosition::create(['generation_id' => $generationA->id, 'name' => 'Posisi Struktur Nonaktif', 'sort_order' => 1, 'status' => false]);
        $positionB = TvClubOrganizationPosition::create(['generation_id' => $generationB->id, 'name' => 'Ketua Struktur B', 'sort_order' => 0, 'status' => true]);
        TvClubOrganizationMember::create(['position_id' => $positionA->id, 'member_id' => $memberA->id, 'sort_order' => 0]);
        TvClubOrganizationMember::create(['position_id' => $positionA->id, 'member_id' => $memberInactive->id, 'sort_order' => 1]);

        $response = $this->get(route('tvclub.generations.show', $generationA));

        $response->assertOk()
            ->assertSee('Struktur Organisasi')
            ->assertSee('Ketua Struktur A')
            ->assertSee('Anggota Struktur Publik')
            ->assertDontSee('Posisi Struktur Nonaktif')
            ->assertDontSee('Ketua Struktur B')
            ->assertDontSee('NIM-PRIVATE-2187')
            ->assertDontSee('NIM-PRIVATE-INACTIVE');

        $organizationHtml = Str::between($response->getContent(), 'id="struktur-organisasi"', 'id="anggota"');
        $this->assertStringContainsString('Anggota Struktur Publik', $organizationHtml);
        $this->assertStringNotContainsString('Anggota Organisasi Nonaktif', $organizationHtml);

        $this->assertNotNull($positionInactive->id);
        $this->assertNotNull($positionB->id);
    }

    public function test_generation_detail_renders_multiple_roots_arbitrary_depth_and_sort_order(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Tree Lengkap', 'year' => 2183, 'status' => true]);
        $rootB = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'name' => 'Root Kedua', 'sort_order' => 2, 'status' => true]);
        $rootA = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'name' => 'Root Pertama', 'sort_order' => 1, 'status' => true]);
        $child = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'parent_id' => $rootA->id, 'name' => 'Child Dalam', 'sort_order' => 1, 'status' => true]);
        TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'parent_id' => $child->id, 'name' => 'Grandchild Dalam', 'sort_order' => 1, 'status' => true]);

        $this->get(route('tvclub.generations.show', $generation))
            ->assertOk()
            ->assertSeeInOrder(['Root Pertama', 'Child Dalam', 'Grandchild Dalam', 'Root Kedua']);

        $this->assertNotNull($rootB->id);
    }

    public function test_generation_without_active_structure_is_safe_and_hides_section(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Tanpa Struktur Aktif', 'year' => 2182, 'status' => true]);
        TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'name' => 'Posisi Nonaktif Saja', 'sort_order' => 0, 'status' => false]);

        $this->get(route('tvclub.generations.show', $generation))
            ->assertOk()
            ->assertDontSee('id="struktur-organisasi"', false)
            ->assertDontSee('Posisi Nonaktif Saja');
    }
}
