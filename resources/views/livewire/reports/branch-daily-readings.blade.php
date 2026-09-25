<div class="sniper-page">
    <div class="sniper-page-header">
        <div><div class="sniper-kicker">Branch Operations</div><h1 class="sniper-title mt-1">Branch Daily Readings</h1><p class="sniper-subtitle">Preview attributed transactions and close one additional branch at a time.</p></div>
    </div>

    @if (session('success')) <div class="sniper-alert-success mb-5">{{ session('success') }}</div> @endif

    <div class="sniper-form-panel mb-6 grid gap-4 md:grid-cols-2">
        <div><x-input-label for="reading-branch" value="Additional branch" /><select id="reading-branch" wire:model.live="branchId" class="sniper-input mt-1.5"><option value="">Choose a branch</option>@foreach ($branches as $choice)<option value="{{ $choice->id }}">{{ $choice->name }} ({{ $choice->code }})</option>@endforeach</select><x-input-error :messages="$errors->get('branchId')" class="mt-2" /></div>
        <div><x-input-label for="reading-date" value="Business date" /><x-text-input id="reading-date" wire:model.live="businessDate" type="date" max="{{ now()->toDateString() }}" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('businessDate')" class="mt-2" /></div>
    </div>

    @if ($branch !== null)
        <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
            <section class="space-y-6">
                <div class="sniper-card p-5">
                    <h2 class="font-heading text-lg font-bold text-sniper-navy">X-Reading · {{ $branch->name }}</h2>
                    <p class="mt-1 text-xs text-sniper-slate">Live preview from sales attributed to this branch. Historical sales without a branch are excluded.</p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-slate-50 p-4"><div class="sniper-stat-label">Issued invoices</div><div class="mt-1 font-heading text-xl font-bold">{{ $snapshot['invoice_range']['issued_count'] }}</div></div>
                        <div class="rounded-xl bg-slate-50 p-4"><div class="sniper-stat-label">Gross sales after partial refunds</div><div class="mt-1 font-heading text-xl font-bold">₱{{ number_format($snapshot['sales']['gross_sales'], 2) }}</div></div>
                        <div class="rounded-xl bg-emerald-50 p-4"><div class="sniper-stat-label">Net sales after partial refunds</div><div class="mt-1 font-heading text-xl font-bold">₱{{ number_format($snapshot['sales']['net_sales'], 2) }}</div></div>
                    </div>
                    <div class="mt-4 text-sm text-sniper-slate">Invoice range: {{ $snapshot['invoice_range']['first'] ?? '—' }} to {{ $snapshot['invoice_range']['last'] ?? '—' }} · Reversals: {{ $snapshot['reversals']['count'] }} · Partial refunds: {{ $snapshot['reversals']['partial_refund_count'] }}</div>
                </div>

                <div class="sniper-table-wrap">
                    <div class="sniper-section-header"><h2 class="font-heading text-base font-bold text-sniper-navy">Permanent Branch Closings</h2></div>
                    <table class="sniper-table"><thead><tr><th>Reading</th><th>Date</th><th>Closed by</th><th>Net sales</th></tr></thead><tbody>
                        @forelse ($closings as $closing)
                            <tr wire:key="closing-{{ $closing->id }}"><td>{{ $closing->reading_number }}</td><td>{{ $closing->business_date->format('Y-m-d') }}</td><td>{{ $closing->closedBy->name }}</td><td>₱{{ number_format($closing->snapshot['sales']['net_sales'], 2) }}</td></tr>
                        @empty <tr><td colspan="4" class="sniper-empty">No branch closings yet.</td></tr>
                        @endforelse
                    </tbody></table>
                </div>
            </section>

            <aside class="sniper-card p-5 lg:self-start">
                <h2 class="font-heading text-lg font-bold text-sniper-navy">Branch Z-Reading</h2>
                @if ($existingClosing)
                    <p class="mt-3 text-sm text-emerald-800">Date already closed: {{ $existingClosing->reading_number }}</p>
                @else
                    <p class="mt-2 text-xs text-sniper-slate">This creates an immutable closing for this branch and date. Check the preview before proceeding.</p>
                    @error('closing') <p class="mt-3 text-sm text-red-700">{{ $message }}</p> @enderror
                    <div class="mt-4 space-y-4"><div><x-input-label for="branch-closing-notes" value="Notes" /><textarea id="branch-closing-notes" wire:model="notes" class="sniper-input mt-1.5" maxlength="500"></textarea><x-input-error :messages="$errors->get('notes')" class="mt-2" /></div><div><x-input-label for="branch-closing-password" value="Admin password" /><x-text-input id="branch-closing-password" wire:model="authorizationPassword" type="password" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('authorizationPassword')" class="mt-2" /></div><label class="flex items-start gap-2 text-xs"><input type="checkbox" wire:model="confirmed" /><span>I confirm this permanent branch closing.</span></label><x-input-error :messages="$errors->get('confirmed')" class="mt-2" /><button type="button" wire:click="createZReading" wire:confirm="Close this branch business date permanently?" wire:loading.attr="disabled" class="sniper-btn-primary w-full">Create Branch Z-Reading</button></div>
                @endif
            </aside>
        </div>
    @endif
</div>
