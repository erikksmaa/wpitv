<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Organization\Index as WpitvOrganizationIndex;
use App\Livewire\Admin\TVClub\Organization\Index as TvClubOrganizationIndex;
use App\Models\Role;
use App\Models\TVClubGeneration;
use App\Models\TVClubMember;
use App\Models\TvClubOrganizationMember;
use App\Models\TvClubOrganizationPosition;
use App\Models\User;
use App\Models\WpitvOrganizationPosition;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationStructureManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $adminProduction;

    protected User $adminNews;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'Super Admin')->value('id'),
            'status' => true,
        ]);
        $this->adminProduction = User::factory()->create([
            'role_id' => Role::where('name', 'Admin Production')->value('id'),
            'status' => true,
        ]);
        $this->adminNews = User::factory()->create([
            'role_id' => Role::where('name', 'Admin News')->value('id'),
            'status' => true,
        ]);
    }

    public function test_wpitv_model_relationships_work(): void
    {
        $root = WpitvOrganizationPosition::create(['user_id' => $this->superAdmin->id, 'name' => 'Kepala', 'sort_order' => 0, 'status' => true]);
        $child = WpitvOrganizationPosition::create(['parent_id' => $root->id, 'name' => 'Produksi', 'sort_order' => 1, 'status' => true]);

        $this->assertTrue($child->parent->is($root));
        $this->assertTrue($root->children->first()->is($child));
        $this->assertTrue($root->user->is($this->superAdmin));
        $this->assertTrue($this->superAdmin->organizationPositions->first()->is($root));
    }

    public function test_tvclub_model_relationships_and_multiple_assignments_work(): void
    {
        [$generation, $member] = $this->generationWithMember(2190, '21.2190.001');
        $root = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'name' => 'Ketua', 'sort_order' => 0, 'status' => true]);
        $child = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'parent_id' => $root->id, 'name' => 'Produksi', 'sort_order' => 1, 'status' => true]);
        $second = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'name' => 'Dokumentasi', 'sort_order' => 2, 'status' => true]);
        TvClubOrganizationMember::create(['position_id' => $child->id, 'member_id' => $member->id, 'sort_order' => 0]);
        TvClubOrganizationMember::create(['position_id' => $second->id, 'member_id' => $member->id, 'sort_order' => 0]);

        $this->assertTrue($root->generation->is($generation));
        $this->assertTrue($child->parent->is($root));
        $this->assertTrue($root->children->first()->is($child));
        $this->assertTrue($child->assignments->first()->member->is($member));
        $this->assertCount(2, $member->organizationPositions);
        $this->assertCount(2, $member->organizationAssignments);
        $this->assertCount(3, $generation->organizationPositions);
    }

    public function test_wpitv_cannot_parent_position_to_itself_or_descendant(): void
    {
        $root = WpitvOrganizationPosition::create(['name' => 'Kepala', 'sort_order' => 0, 'status' => true]);
        $child = WpitvOrganizationPosition::create(['parent_id' => $root->id, 'name' => 'News', 'sort_order' => 0, 'status' => true]);

        Livewire::actingAs($this->superAdmin)->test(WpitvOrganizationIndex::class)
            ->call('editPosition', $root->id)
            ->set('parent_id', $root->id)
            ->call('savePosition')
            ->assertHasErrors('parent_id');

        Livewire::actingAs($this->superAdmin)->test(WpitvOrganizationIndex::class)
            ->call('editPosition', $root->id)
            ->set('parent_id', $child->id)
            ->call('savePosition')
            ->assertHasErrors('parent_id');

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_wpitv_admin_can_create_assign_edit_reorder_and_deactivate_position(): void
    {
        Livewire::actingAs($this->adminProduction)->test(WpitvOrganizationIndex::class)
            ->call('createPosition')
            ->set('name', 'Koordinator Organisasi Test')
            ->set('user_id', $this->adminProduction->id)
            ->set('sort_order', 4)
            ->set('status', true)
            ->call('savePosition')
            ->assertHasNoErrors();

        $position = WpitvOrganizationPosition::where('name', 'Koordinator Organisasi Test')->firstOrFail();

        Livewire::actingAs($this->adminProduction)->test(WpitvOrganizationIndex::class)
            ->call('editPosition', $position->id)
            ->set('name', 'Koordinator Organisasi Diperbarui')
            ->set('sort_order', 1)
            ->set('status', false)
            ->call('savePosition')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('wpitv_organization_positions', [
            'id' => $position->id,
            'user_id' => $this->adminProduction->id,
            'name' => 'Koordinator Organisasi Diperbarui',
            'sort_order' => 1,
            'status' => 0,
        ]);
    }

    public function test_tvclub_parent_and_member_are_restricted_to_route_generation(): void
    {
        [$generationA] = $this->generationWithMember(2191, '21.2191.001');
        [$generationB, $memberB] = $this->generationWithMember(2192, '21.2192.001');
        $foreignParent = TvClubOrganizationPosition::create(['generation_id' => $generationB->id, 'name' => 'Ketua B', 'sort_order' => 0, 'status' => true]);
        $positionA = TvClubOrganizationPosition::create(['generation_id' => $generationA->id, 'name' => 'Ketua A', 'sort_order' => 0, 'status' => true]);

        Livewire::actingAs($this->superAdmin)->test(TvClubOrganizationIndex::class, ['generation' => $generationA])
            ->call('editPosition', $positionA->id)
            ->set('parent_id', $foreignParent->id)
            ->call('savePosition')
            ->assertHasErrors('parent_id')
            ->call('openMemberForm', $positionA->id)
            ->set('member_id', $memberB->id)
            ->set('member_sort_order', 0)
            ->call('addMember')
            ->assertHasErrors('member_id');

        $this->assertDatabaseMissing('tvclub_organization_members', ['position_id' => $positionA->id, 'member_id' => $memberB->id]);
    }

    public function test_tvclub_rejects_duplicate_member_in_same_position_with_friendly_error(): void
    {
        [$generation, $member] = $this->generationWithMember(2193, '21.2193.001');
        $position = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'name' => 'Ketua', 'sort_order' => 0, 'status' => true]);
        TvClubOrganizationMember::create(['position_id' => $position->id, 'member_id' => $member->id, 'sort_order' => 0]);

        Livewire::actingAs($this->superAdmin)->test(TvClubOrganizationIndex::class, ['generation' => $generation])
            ->call('openMemberForm', $position->id)
            ->set('member_id', $member->id)
            ->call('addMember')
            ->assertHasErrors('member_id');

        $this->assertSame(1, TvClubOrganizationMember::where('position_id', $position->id)->where('member_id', $member->id)->count());
    }

    public function test_tvclub_admin_can_create_position_and_assign_multiple_same_generation_members(): void
    {
        [$generation, $firstMember] = $this->generationWithMember(2199, '21.2199.001');
        $secondMember = TVClubMember::create(['generation_id' => $generation->id, 'student_number' => '21.2199.002', 'name' => 'Anggota Kedua', 'status' => true]);

        Livewire::actingAs($this->superAdmin)->test(TvClubOrganizationIndex::class, ['generation' => $generation])
            ->call('createPosition')
            ->set('name', 'Divisi Dokumentasi')
            ->set('sort_order', 3)
            ->call('savePosition')
            ->assertHasNoErrors();

        $position = TvClubOrganizationPosition::where('generation_id', $generation->id)->where('name', 'Divisi Dokumentasi')->firstOrFail();

        foreach ([[$firstMember, 0], [$secondMember, 1]] as [$member, $order]) {
            Livewire::actingAs($this->superAdmin)->test(TvClubOrganizationIndex::class, ['generation' => $generation])
                ->call('openMemberForm', $position->id)
                ->set('member_id', $member->id)
                ->set('member_sort_order', $order)
                ->call('addMember')
                ->assertHasNoErrors();
        }

        $this->assertSame(2, $position->assignments()->count());
        $this->assertSame([0, 1], $position->assignments()->pluck('sort_order')->all());
    }

    public function test_tvclub_cannot_parent_position_to_itself_or_descendant(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Siklus', 'year' => 2197, 'status' => true]);
        $root = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'name' => 'Ketua', 'sort_order' => 0, 'status' => true]);
        $child = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'parent_id' => $root->id, 'name' => 'Produksi', 'sort_order' => 0, 'status' => true]);

        Livewire::actingAs($this->superAdmin)->test(TvClubOrganizationIndex::class, ['generation' => $generation])
            ->call('editPosition', $root->id)
            ->set('parent_id', $root->id)
            ->call('savePosition')
            ->assertHasErrors('parent_id');

        Livewire::actingAs($this->superAdmin)->test(TvClubOrganizationIndex::class, ['generation' => $generation])
            ->call('editPosition', $root->id)
            ->set('parent_id', $child->id)
            ->call('savePosition')
            ->assertHasErrors('parent_id');

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_parent_delete_is_blocked_and_leaf_delete_is_allowed(): void
    {
        $root = WpitvOrganizationPosition::create(['name' => 'Kepala', 'sort_order' => 0, 'status' => true]);
        $leaf = WpitvOrganizationPosition::create(['parent_id' => $root->id, 'name' => 'News', 'sort_order' => 0, 'status' => true]);

        Livewire::actingAs($this->superAdmin)->test(WpitvOrganizationIndex::class)
            ->call('deletePosition', $root->id)
            ->assertSee('Posisi ini masih memiliki posisi di bawahnya');
        $this->assertDatabaseHas('wpitv_organization_positions', ['id' => $root->id]);

        Livewire::actingAs($this->superAdmin)->test(WpitvOrganizationIndex::class)
            ->call('deletePosition', $leaf->id);
        $this->assertDatabaseMissing('wpitv_organization_positions', ['id' => $leaf->id]);
    }

    public function test_tvclub_parent_delete_is_blocked_and_leaf_delete_is_allowed(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Hapus Posisi', 'year' => 2198, 'status' => true]);
        $root = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'name' => 'Ketua', 'sort_order' => 0, 'status' => true]);
        $leaf = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'parent_id' => $root->id, 'name' => 'Dokumentasi', 'sort_order' => 0, 'status' => true]);

        Livewire::actingAs($this->adminProduction)->test(TvClubOrganizationIndex::class, ['generation' => $generation])
            ->call('deletePosition', $root->id)
            ->assertSee('Posisi ini masih memiliki posisi di bawahnya');
        $this->assertDatabaseHas('tvclub_organization_positions', ['id' => $root->id]);

        Livewire::actingAs($this->adminProduction)->test(TvClubOrganizationIndex::class, ['generation' => $generation])
            ->call('deletePosition', $leaf->id);
        $this->assertDatabaseMissing('tvclub_organization_positions', ['id' => $leaf->id]);
    }

    public function test_authorization_matches_existing_tvclub_policy_pattern(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Otorisasi', 'year' => 2194, 'status' => true]);

        $this->actingAs($this->superAdmin)->get(route('admin.organization.index'))->assertOk()->assertSee('Tambah Posisi');
        $this->actingAs($this->adminProduction)->get(route('admin.tvclub.generations.organization', $generation))->assertOk()->assertSee('Tambah Posisi');
        $this->actingAs($this->adminNews)->get(route('admin.organization.index'))->assertOk()->assertDontSee('Tambah Posisi');
        $this->actingAs($this->adminNews)->get(route('admin.tvclub.generations.organization', $generation))->assertOk()->assertDontSee('Tambah Posisi');

        Livewire::actingAs($this->adminNews)->test(WpitvOrganizationIndex::class)->call('createPosition')->assertForbidden();
        Livewire::actingAs($this->adminNews)->test(TvClubOrganizationIndex::class, ['generation' => $generation])->call('createPosition')->assertForbidden();
    }

    public function test_generation_list_has_an_obvious_reachable_organization_entry_point(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Entry Point', 'year' => 2186, 'status' => true]);

        $this->actingAs($this->adminProduction)
            ->get(route('admin.tvclub.generations.index'))
            ->assertOk()
            ->assertSee('Struktur Organisasi')
            ->assertSee(route('admin.tvclub.generations.organization', $generation), false);

        $this->actingAs($this->adminProduction)
            ->get(route('admin.tvclub.generations.organization', $generation))
            ->assertOk()
            ->assertSee('Struktur Organisasi TV Club')
            ->assertSee('Tambah Posisi');
    }

    public function test_edit_parent_options_hide_current_position_and_all_descendants(): void
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi Parent Options', 'year' => 2185, 'status' => true]);
        $root = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'name' => 'Root Opsi', 'sort_order' => 0, 'status' => true]);
        $child = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'parent_id' => $root->id, 'name' => 'Child Opsi', 'sort_order' => 0, 'status' => true]);
        TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'parent_id' => $child->id, 'name' => 'Grandchild Opsi', 'sort_order' => 0, 'status' => true]);
        TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'name' => 'Root Alternatif', 'sort_order' => 1, 'status' => true]);

        Livewire::actingAs($this->superAdmin)
            ->test(TvClubOrganizationIndex::class, ['generation' => $generation])
            ->call('editPosition', $root->id)
            ->assertSee('Root Alternatif')
            ->assertDontSee('<option value="'.$root->id.'">Root Opsi', false)
            ->assertDontSee('<option value="'.$child->id.'">Child Opsi', false)
            ->assertDontSee('Grandchild Opsi</option>', false);
    }

    public function test_member_assignment_can_be_removed_without_affecting_other_position_roles(): void
    {
        [$generation, $member] = $this->generationWithMember(2184, '21.2184.001');
        $first = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'name' => 'Ketua Remove', 'sort_order' => 0, 'status' => true]);
        $second = TvClubOrganizationPosition::create(['generation_id' => $generation->id, 'name' => 'Produksi Remove', 'sort_order' => 1, 'status' => true]);
        $remove = TvClubOrganizationMember::create(['position_id' => $first->id, 'member_id' => $member->id, 'sort_order' => 0]);
        TvClubOrganizationMember::create(['position_id' => $second->id, 'member_id' => $member->id, 'sort_order' => 0]);

        Livewire::actingAs($this->adminProduction)
            ->test(TvClubOrganizationIndex::class, ['generation' => $generation])
            ->call('removeMember', $first->id, $remove->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('tvclub_organization_members', ['id' => $remove->id]);
        $this->assertDatabaseHas('tvclub_organization_members', ['position_id' => $second->id, 'member_id' => $member->id]);
    }

    public function test_tvclub_route_binding_cannot_mix_position_from_another_generation(): void
    {
        $generationA = TVClubGeneration::create(['name' => 'Generasi Route A', 'year' => 2195, 'status' => true]);
        $generationB = TVClubGeneration::create(['name' => 'Generasi Route B', 'year' => 2196, 'status' => true]);
        $positionB = TvClubOrganizationPosition::create(['generation_id' => $generationB->id, 'name' => 'Ketua B', 'sort_order' => 0, 'status' => true]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->superAdmin)->test(TvClubOrganizationIndex::class, ['generation' => $generationA])
            ->call('editPosition', $positionB->id);
    }

    private function generationWithMember(int $year, string $studentNumber): array
    {
        $generation = TVClubGeneration::create(['name' => 'Generasi '.$year, 'year' => $year, 'status' => true]);
        $member = TVClubMember::create([
            'generation_id' => $generation->id,
            'student_number' => $studentNumber,
            'name' => 'Anggota '.$year,
            'status' => true,
        ]);

        return [$generation, $member];
    }
}
