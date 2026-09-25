<?php

use App\Http\Controllers\ComplianceFilesController;
use App\Http\Controllers\ReportsExportController;
use App\Livewire\Audit\AuditLogList;
use App\Livewire\Branches\BranchManagement;
use App\Livewire\Branches\BranchMonitor;
use App\Http\Middleware\RedirectBranchDashboard;
use App\Livewire\Categories\CategoryList;
use App\Livewire\Dashboard\DashboardOverview;
use App\Livewire\Inventory\InventoryList;
use App\Livewire\Pos\SaleTerminal;
use App\Livewire\Products\ProductList;
use App\Livewire\Reports\DailyReadings;
use App\Livewire\Reports\ReportsDashboard;
use App\Livewire\Sales\SalesHistory;
use App\Livewire\Settings\BirSettings;
use App\Livewire\Settings\SystemReadiness;
use App\Livewire\Users\UserManagement;
use App\Models\Branch;
use App\Models\DailyClosing;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('dashboard', DashboardOverview::class)
    ->middleware(['auth', 'active', 'verified', RedirectBranchDashboard::class])
    ->name('dashboard');

Route::get('branch-monitor', BranchMonitor::class)
    ->middleware(['auth', 'active', 'verified', 'role:admin,manager,supervisor'])
    ->name('branch-monitor');

Route::view('profile', 'profile')
    ->middleware(['auth', 'active'])
    ->name('profile');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('branches', function (Request $request) {
        $user = $request->user();
        $branches = Branch::query()
            ->when(! $user->isAdmin(), fn ($query) => $query
                ->where('status', Branch::STATUS_ACTIVE)
                ->whereHas('users', fn ($users) => $users
                    ->whereKey($user->getKey())
                    ->where('branch_user.status', Branch::STATUS_ACTIVE)))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'status']);

        return response()->json($branches);
    })->name('branches.index');

    Route::get('branches/{branch}', function (Request $request, Branch $branch) {
        abort_unless($request->user()->canAccessBranch($branch), 404);

        return response()->json($branch->only(['id', 'code', 'name', 'address', 'timezone', 'status']));
    })->name('branches.show');
});

Route::middleware(['auth', 'active', 'role:admin,cashier'])->group(function () {
    Route::get('pos', SaleTerminal::class)->name('pos');

    Route::get('sales/{sale}/invoice', function (Sale $sale) {
        $sale->load(['items', 'user', 'payment', 'adjustment.authorizedBy', 'refunds.items']);

        return view('sales.receipt', compact('sale'));
    })->name('sales.invoice');

    Route::get('sales/{sale}/receipt', fn (Sale $sale) => redirect()->route('sales.invoice', $sale));
});

Route::middleware(['auth', 'active', 'role:admin'])->group(function () {
    Route::get('branch-management', BranchManagement::class)->name('branch-management');
    Route::get('categories', CategoryList::class)->name('categories');
    Route::get('products', ProductList::class)->name('products');
    Route::get('inventory', InventoryList::class)->name('inventory');
    Route::get('sales', SalesHistory::class)->name('sales');

    Route::get('sales/{sale}', function (Sale $sale) {
        $sale->load(['items', 'user', 'payment', 'adjustment.authorizedBy']);

        return view('sales.show', compact('sale'));
    })->name('sales.show');

    Route::get('reports', ReportsDashboard::class)->name('reports');
    Route::get('daily-readings', DailyReadings::class)->name('daily-readings');
    Route::get('daily-readings/{dailyClosing}/print', function (DailyClosing $dailyClosing) {
        $dailyClosing->load('closedBy');

        return view('reports.daily-closing', compact('dailyClosing'));
    })->name('daily-readings.print');
    Route::get('reports/export/bir-sales', [ReportsExportController::class, 'sales'])->name('reports.export.sales');
    Route::get('reports/export/reversals', [ReportsExportController::class, 'reversals'])->name('reports.export.reversals');
    Route::get('users', UserManagement::class)->name('users');
    Route::get('audit-logs', AuditLogList::class)->name('audit-logs');
    Route::get('settings/bir', BirSettings::class)->name('settings.bir');
    Route::get('settings/readiness', SystemReadiness::class)->name('settings.readiness');
    Route::get('compliance/backups/{filename}', [ComplianceFilesController::class, 'backup'])->name('compliance.backup');
    Route::get('compliance/audit-export', [ComplianceFilesController::class, 'audit'])->name('compliance.audit');
});

require __DIR__.'/auth.php';
