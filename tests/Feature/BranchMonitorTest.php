<?php

namespace Tests\Feature;

use App\Livewire\Branches\BranchMonitor;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class BranchMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_only_assigned_branches_and_their_attributed_sales(): void
    {
        $manager = User::factory()->manager()->create();
        $a = Branch::factory()->create(['name' => 'Assigned North']);
        $b = Branch::factory()->create(['name' => 'Assigned South']);
        $other = Branch::factory()->create(['name' => 'Private East']);
        $manager->branches()->attach([$a->id, $b->id]);
        $product = Product::factory()->create();
        BranchProduct::factory()->for($a)->for($product)->create(['on_hand' => 11]);
        BranchProduct::factory()->for($other)->for($product)->create(['on_hand' => 93]);
        $this->sale($manager, $a, 'ASSIGNED-SALE', 120);
        $this->sale($manager, $other, 'PRIVATE-SALE', 987);
        $this->sale($manager, null, 'LEGACY-SALE', 321);

        $this->actingAs($manager)->get(route('dashboard'))->assertRedirect(route('branch-monitor'));
        $this->actingAs($manager)->get(route('branch-monitor'))->assertOk()
            ->assertSee('Assigned North')->assertSee('Assigned South')
            ->assertSee('120.00')->assertDontSee('Private East')
            ->assertDontSee('987.00')->assertDontSee('321.00');
        Livewire::actingAs($manager)->test(BranchMonitor::class)
            ->set('branchId', (string) $other->id)->assertNotFound();
    }

    public function test_revoked_or_inactive_branch_disappears_and_supervisor_cannot_select_it(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $branch = Branch::factory()->create(['name' => 'Former Branch']);
        $supervisor->branches()->attach($branch->id);
        $this->actingAs($supervisor)->get(route('branch-monitor'))->assertSee('Former Branch');

        $supervisor->branches()->updateExistingPivot($branch->id, ['status' => Branch::STATUS_INACTIVE]);

        $this->actingAs($supervisor)->get(route('branch-monitor'))->assertOk()->assertDontSee('Former Branch');
        Livewire::actingAs($supervisor)->test(BranchMonitor::class)
            ->set('branchId', (string) $branch->id)->assertNotFound();
    }

    public function test_admin_can_monitor_any_active_branch_and_cashier_cannot_open_monitor(): void
    {
        $admin = User::factory()->admin()->create();
        $cashier = User::factory()->create();
        $branch = Branch::factory()->create(['name' => 'Open Branch']);

        $this->actingAs($admin)->get(route('branch-monitor'))->assertOk()->assertSee('Open Branch');
        $this->actingAs($cashier)->get(route('branch-monitor'))->assertForbidden();
        Livewire::actingAs($cashier)->test(BranchMonitor::class)->assertForbidden();
    }

    public function test_cashier_uses_pos_instead_of_global_dashboard_after_activation(): void
    {
        $cashier = User::factory()->create();
        $branch = Branch::factory()->create();
        DB::table('original_branch_inventory')->insert([
            'id' => 1, 'branch_id' => $branch->id, 'activated_at' => now(),
        ]);

        $this->actingAs($cashier)->get(route('dashboard'))->assertRedirect(route('pos'));
        Livewire::actingAs($cashier)->test(\App\Livewire\Dashboard\DashboardOverview::class)->assertForbidden();
    }

    private function sale(User $user, ?Branch $branch, string $number, int $total): void
    {
        Sale::query()->create([
            'sale_number' => $number,
            'branch_id' => $branch?->id,
            'user_id' => $user->id,
            'subtotal' => $total,
            'total' => $total,
            'cash_received' => $total,
            'change_due' => 0,
            'status' => Sale::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
