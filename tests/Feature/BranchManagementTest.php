<?php

namespace Tests\Feature;

use App\Livewire\Branches\BranchManagement;
use App\Livewire\Users\UserManagement;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    public static function nonAdminRoles(): array
    {
        return [
            'manager' => [User::ROLE_MANAGER],
            'supervisor' => [User::ROLE_SUPERVISOR],
            'cashier' => [User::ROLE_CASHIER],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_only_admin_can_open_or_invoke_branch_management(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get(route('branch-management'))->assertForbidden();
        Livewire::actingAs($user)->test(BranchManagement::class)->assertForbidden();
    }

    public function test_admin_can_create_branch_and_duplicate_code_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(BranchManagement::class)
            ->call('create')
            ->set('code', 'GEN-01')
            ->set('name', 'General Santos')
            ->set('address', 'Downtown')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('branches', ['code' => 'GEN-01', 'name' => 'General Santos']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'branch.created']);

        Livewire::actingAs($admin)->test(BranchManagement::class)
            ->call('create')
            ->set('code', 'GEN-01')
            ->set('name', 'Duplicate')
            ->call('save')
            ->assertHasErrors(['code' => 'unique']);

        $this->assertSame(1, Branch::query()->count());
    }

    public function test_manager_can_be_assigned_to_two_branches_and_revocation_is_immediate(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $a = Branch::factory()->create();
        $b = Branch::factory()->create();

        foreach ([$a, $b] as $branch) {
            Livewire::actingAs($admin)->test(BranchManagement::class)
                ->set('userId', $manager->id)->set('branchId', $branch->id)
                ->call('assign')->assertHasNoErrors();
        }

        $this->assertSame(2, $manager->branches()->wherePivot('status', Branch::STATUS_ACTIVE)->count());
        $this->actingAs($manager)->get(route('branches.show', $b))->assertOk();

        Livewire::actingAs($admin)->test(BranchManagement::class)->call('revoke', $manager->id, $b->id);

        $this->actingAs($manager)->get(route('branches.show', $b))->assertNotFound();
        $this->actingAs($manager)->get(route('branches.show', $a))->assertOk();
        $this->assertSame(2, AuditLog::query()->where('action', 'branch.user_assigned')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'branch.user_revoked')->count());
    }

    #[DataProvider('singleBranchRoles')]
    public function test_supervisor_and_cashier_cannot_receive_a_second_active_branch(string $role): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create(['role' => $role]);
        $a = Branch::factory()->create();
        $b = Branch::factory()->create();
        $staff->branches()->attach($a->id);

        Livewire::actingAs($admin)->test(BranchManagement::class)
            ->set('userId', $staff->id)->set('branchId', $b->id)
            ->call('assign')->assertHasErrors('userId');

        $this->assertDatabaseMissing('branch_user', ['user_id' => $staff->id, 'branch_id' => $b->id]);
    }

    public static function singleBranchRoles(): array
    {
        return [
            'supervisor' => [User::ROLE_SUPERVISOR],
            'cashier' => [User::ROLE_CASHIER],
        ];
    }

    public function test_admin_cannot_assign_inactive_branch_or_other_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $inactive = Branch::factory()->inactive()->create();
        $active = Branch::factory()->create();

        Livewire::actingAs($admin)->test(BranchManagement::class)
            ->set('userId', $otherAdmin->id)->set('branchId', $active->id)
            ->call('assign')->assertHasErrors('userId');
        Livewire::actingAs($admin)->test(BranchManagement::class)
            ->set('userId', User::factory()->manager()->create()->id)->set('branchId', $inactive->id)
            ->call('assign')->assertHasErrors('branchId');

        $this->assertSame(0, $active->users()->count());
    }

    public function test_demoting_manager_with_two_branches_to_cashier_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $manager->branches()->attach(Branch::factory()->count(2)->create()->modelKeys());

        Livewire::actingAs($admin)->test(UserManagement::class)
            ->call('edit', $manager->id)
            ->set('role', User::ROLE_CASHIER)
            ->call('save')
            ->assertHasErrors('role');

        $this->assertSame(User::ROLE_MANAGER, $manager->fresh()->role);
    }
}
