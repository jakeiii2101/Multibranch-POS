<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BranchAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_list_and_open_only_assigned_branches(): void
    {
        $manager = User::factory()->manager()->create();
        $branchA = Branch::factory()->create(['name' => 'Branch A']);
        $branchB = Branch::factory()->create(['name' => 'Branch B']);
        $other = Branch::factory()->create(['name' => 'Other Branch']);
        $manager->branches()->attach([$branchA->id, $branchB->id]);

        $this->actingAs($manager)->get(route('branches.index'))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => 'Branch A'])
            ->assertJsonFragment(['name' => 'Branch B'])
            ->assertDontSee('Other Branch');

        $this->actingAs($manager)->get(route('branches.show', $branchA))->assertOk();
        $this->actingAs($manager)->get(route('branches.show', $branchB))->assertOk();
        $this->actingAs($manager)->get(route('branches.show', $other))->assertNotFound();
    }

    public static function branchStaffRoles(): array
    {
        return [
            'supervisor' => [User::ROLE_SUPERVISOR],
            'cashier' => [User::ROLE_CASHIER],
        ];
    }

    #[DataProvider('branchStaffRoles')]
    public function test_staff_cannot_open_another_branch_by_changing_the_url(string $role): void
    {
        $staff = User::factory()->create(['role' => $role]);
        $assigned = Branch::factory()->create();
        $other = Branch::factory()->create();
        $staff->branches()->attach($assigned->id);

        $this->actingAs($staff)->get(route('branches.show', $assigned))->assertOk();
        $this->actingAs($staff)->get(route('branches.show', $other))->assertNotFound();
    }

    public function test_revoked_assignment_takes_effect_on_the_next_request(): void
    {
        $manager = User::factory()->manager()->create();
        $branch = Branch::factory()->create();
        $manager->branches()->attach($branch->id);

        $this->actingAs($manager)->get(route('branches.show', $branch))->assertOk();
        $manager->branches()->updateExistingPivot($branch->id, ['status' => Branch::STATUS_INACTIVE]);

        $this->actingAs($manager)->get(route('branches.show', $branch))->assertNotFound();
        $this->actingAs($manager)->get(route('branches.index'))->assertJsonCount(0);
    }

    public function test_inactive_branch_cannot_be_opened_by_assigned_staff(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $branch = Branch::factory()->inactive()->create();
        $supervisor->branches()->attach($branch->id);

        $this->actingAs($supervisor)->get(route('branches.show', $branch))->assertNotFound();
    }

    public function test_admin_can_view_branches_without_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();

        $this->actingAs($admin)->get(route('branches.show', $branch))->assertOk();
    }
}
