<?php

namespace App\Livewire\Reports;

use App\Models\Branch;
use App\Models\BranchDailyClosing;
use App\Support\BranchDailyReadingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class BranchDailyReadings extends Component
{
    public string $branchId = '';
    public string $businessDate = '';
    public string $notes = '';
    public string $authorizationPassword = '';
    public bool $confirmed = false;

    public function mount(): void
    {
        $this->businessDate = now()->toDateString();
    }

    public function boot(): void
    {
        abort_unless(auth()->check() && auth()->user()->isActive() && auth()->user()->isAdmin(), 403);
    }

    public function createZReading(BranchDailyReadingService $service): void
    {
        $validated = $this->validate([
            'branchId' => ['required', 'integer', 'exists:branches,id'],
            'businessDate' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
            'authorizationPassword' => ['required', 'current_password'],
            'confirmed' => ['accepted'],
        ]);

        $branch = $this->branches()->firstWhere('id', (int) $validated['branchId']);
        abort_unless($branch !== null, 404);
        $closing = $service->close($branch, Carbon::parse($validated['businessDate']), auth()->user(), $validated['notes'] ?? null);

        $this->reset(['notes', 'authorizationPassword', 'confirmed']);
        session()->flash('success', 'Branch Z-reading '.$closing->reading_number.' created.');
    }

    private function branches()
    {
        $originalId = DB::table('original_branch_inventory')->where('id', 1)->value('branch_id');

        return Branch::query()->where('status', Branch::STATUS_ACTIVE)
            ->when($originalId === null, fn ($query) => $query->where('id', -1))
            ->when($originalId !== null, fn ($query) => $query->where('id', '!=', $originalId))
            ->orderBy('name')->get(['id', 'code', 'name', 'status']);
    }

    public function render(BranchDailyReadingService $service)
    {
        $branches = $this->branches();
        $branch = $this->branchId === '' ? null : $branches->firstWhere('id', (int) $this->branchId);
        abort_unless($this->branchId === '' || (ctype_digit($this->branchId) && $branch !== null), 404);

        $validDate = validator(['date' => $this->businessDate], ['date' => ['required', 'date_format:Y-m-d']])->passes();
        $date = $validDate ? Carbon::parse($this->businessDate) : now();
        $snapshot = $branch === null ? null : $service->snapshot($branch, $date);
        $existingClosing = $branch === null ? null : BranchDailyClosing::query()
            ->where('branch_id', $branch->id)->whereDate('business_date', $date)->first();
        $closings = $branch === null ? collect() : BranchDailyClosing::query()
            ->with('closedBy')->where('branch_id', $branch->id)->latest('business_date')->limit(31)->get();

        return view('livewire.reports.branch-daily-readings', compact(
            'branches', 'branch', 'snapshot', 'existingClosing', 'closings',
        ));
    }
}
