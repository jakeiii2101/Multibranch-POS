<div class="sniper-page">
    <div class="sniper-page-header">
        <div>
            <div class="sniper-kicker">Tax configuration</div>
            <h1 class="sniper-title mt-1">Branch Invoice Settings</h1>
            <p class="sniper-subtitle">Configure an additional branch's registered seller identity and separate Sales Invoice sequence.</p>
        </div>
        <span class="{{ $isActive ? 'sniper-badge-success' : 'sniper-badge-neutral' }}">{{ $isActive ? 'Invoicing active' : 'Setup inactive' }}</span>
    </div>

    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Enter only registration details confirmed for this branch. Saving this form does not enable checkout for additional branches or mean the system is BIR-accredited or approved.
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="sniper-card mb-6 p-5">
        <x-input-label for="selected-branch" value="Additional branch" />
        <select id="selected-branch" wire:model.live="branchId" class="sniper-input mt-1.5">
            <option value="">Choose a branch</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('branchId')" class="mt-2" />
        @if ($branches->isEmpty()) <p class="mt-2 text-sm text-sniper-slate">Activate the original branch and add an active additional branch first.</p> @endif
    </div>

    @if ($branchId !== '')
    <form wire:submit="save" class="space-y-6">
        <section class="sniper-card p-5 sm:p-6">
            <div class="sniper-section-header -mx-5 -mt-5 mb-5 sm:-mx-6 sm:-mt-6">
                <h2 class="font-heading text-base font-bold text-sniper-navy">Registered Business</h2>
                <p class="mt-1 text-xs text-sniper-slate">These values are permanently copied into each finalized invoice.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div><x-input-label for="registered-name" value="Registered business name" /><x-text-input id="registered-name" wire:model="registeredName" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('registeredName')" class="mt-2" /></div>
                <div><x-input-label for="trade-name" value="Trade name (optional)" /><x-text-input id="trade-name" wire:model="tradeName" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('tradeName')" class="mt-2" /></div>
                <div><x-input-label for="tin" value="TIN" /><x-text-input id="tin" wire:model="tin" class="mt-1.5 block w-full" placeholder="000-000-000-00000" /><x-input-error :messages="$errors->get('tin')" class="mt-2" /></div>
                <div><x-input-label for="branch-code" value="Registered invoice branch code" /><x-text-input id="branch-code" wire:model="branchCode" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('branchCode')" class="mt-2" /></div>
                <div><x-input-label for="rdo-code" value="RDO code (optional)" /><x-text-input id="rdo-code" wire:model="rdoCode" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('rdoCode')" class="mt-2" /></div>
                <div><x-input-label for="tax-type" value="Tax registration" /><select id="tax-type" wire:model.live="taxType" class="sniper-input mt-1.5"><option value="non_vat">Non-VAT</option><option value="vat">VAT Registered</option></select><x-input-error :messages="$errors->get('taxType')" class="mt-2" /></div>
                @if ($taxType === 'vat')
                    <div><x-input-label for="vat-rate" value="VAT rate (%)" /><x-text-input id="vat-rate" wire:model="vatRate" type="number" step="0.01" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('vatRate')" class="mt-2" /></div>
                @endif
                <div class="md:col-span-2"><x-input-label for="registered-address" value="Registered address" /><textarea id="registered-address" wire:model="registeredAddress" rows="3" class="sniper-input mt-1.5"></textarea><x-input-error :messages="$errors->get('registeredAddress')" class="mt-2" /></div>
            </div>
        </section>

        <section class="sniper-card p-5 sm:p-6">
            <div class="sniper-section-header -mx-5 -mt-5 mb-5 sm:-mx-6 sm:-mt-6">
                <h2 class="font-heading text-base font-bold text-sniper-navy">Sales Invoice Control</h2>
                <p class="mt-1 text-xs text-sniper-slate">Issued numbers are never reused. Last issued: {{ $lastIssuedNumber ?? 'None' }}.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <div><x-input-label for="invoice-prefix" value="Invoice prefix" /><x-text-input id="invoice-prefix" wire:model="invoicePrefix" class="mt-1.5 block w-full uppercase" /><x-input-error :messages="$errors->get('invoicePrefix')" class="mt-2" /></div>
                <div><x-input-label for="starting-number" value="Starting number" /><x-text-input id="starting-number" wire:model="startingNumber" type="number" min="1" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('startingNumber')" class="mt-2" /></div>
                <div><x-input-label for="ending-number" value="Ending number (optional)" /><x-text-input id="ending-number" wire:model="endingNumber" type="number" min="1" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('endingNumber')" class="mt-2" /></div>
                <div><x-input-label for="permit-number" value="PTU / acknowledgment number (optional)" /><x-text-input id="permit-number" wire:model="permitNumber" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('permitNumber')" class="mt-2" /></div>
                <div><x-input-label for="permit-date" value="Approval date (optional)" /><x-text-input id="permit-date" wire:model="permitDate" type="date" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('permitDate')" class="mt-2" /></div>
                <div class="flex items-end"><label class="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-4 py-3"><input type="checkbox" wire:model="isActive" class="rounded border-slate-300 text-sniper-red focus:ring-sniper-red"><span class="text-sm font-semibold text-sniper-navy">Activate Sales Invoicing</span></label></div>
                <div class="md:col-span-3"><x-input-label for="invoice-footer" value="Invoice footer (optional)" /><textarea id="invoice-footer" wire:model="invoiceFooter" rows="3" class="sniper-input mt-1.5" placeholder="Add only wording confirmed by your RDO."></textarea><x-input-error :messages="$errors->get('invoiceFooter')" class="mt-2" /></div>
            </div>
        </section>

        <div class="flex justify-end">
            <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="save">Save Branch Invoice Settings</x-primary-button>
        </div>
    </form>
    @endif
</div>
