<div class="sniper-page">
    <div class="sniper-page-header">
        <div>
            <div class="sniper-kicker">Branch Operations</div>
            <h1 class="sniper-title mt-1">Branch Monitor</h1>
            <p class="sniper-subtitle">Today's completed sales before refunds, plus current branch stock. Historical sales without a branch are excluded.</p>
        </div>
        <div>
            <x-input-label for="monitor-branch" value="Branch" />
            <select id="monitor-branch" wire:model.live="branchId" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                <option value="">All accessible branches</option>
                @foreach ($choices as $choice)
                    <option value="{{ $choice->id }}">{{ $choice->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="sniper-table-wrap">
        <table class="sniper-table">
            <thead><tr><th>Branch</th><th>Sales today</th><th>Transactions</th><th>Products</th><th>Units on hand</th><th>Low stock</th></tr></thead>
            <tbody>
                @forelse ($branches as $branch)
                    <tr wire:key="monitor-{{ $branch->id }}">
                        <td class="font-semibold !text-sniper-navy">{{ $branch->name }} <span class="font-normal text-sniper-slate">({{ $branch->code }})</span></td>
                        <td>₱{{ number_format((float) ($sales[$branch->id]->sales_total ?? 0), 2) }}</td>
                        <td>{{ (int) ($sales[$branch->id]->transactions ?? 0) }}</td>
                        <td>{{ (int) ($inventory[$branch->id]->products ?? 0) }}</td>
                        <td>{{ (int) ($inventory[$branch->id]->units ?? 0) }}</td>
                        <td>{{ (int) ($inventory[$branch->id]->low_stock ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="sniper-empty">No active branches are assigned to your account.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
