<?php

namespace App\Livewire\Settings;

use App\Models\BirSetting;
use App\Models\Branch;
use App\Models\InvoiceSequence;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class BranchInvoiceSettings extends Component
{
    public string $branchId = '';
    public string $registeredName = '';
    public string $tradeName = '';
    public string $tin = '';
    public string $branchCode = '';
    public string $registeredAddress = '';
    public string $rdoCode = '';
    public string $taxType = BirSetting::TAX_TYPE_NON_VAT;
    public string $vatRate = '12.00';
    public string $permitNumber = '';
    public string $permitDate = '';
    public string $invoiceFooter = '';
    public string $invoicePrefix = '';
    public string $startingNumber = '1';
    public string $endingNumber = '';
    public bool $isActive = false;

    public function boot(): void
    {
        abort_unless(auth()->check() && auth()->user()->isActive() && auth()->user()->isAdmin(), 403);
    }

    public function updatedBranchId(): void
    {
        $this->resetValidation();
        $this->reset([
            'registeredName', 'tradeName', 'tin', 'branchCode', 'registeredAddress', 'rdoCode',
            'permitNumber', 'permitDate', 'invoiceFooter', 'invoicePrefix', 'endingNumber',
        ]);
        $this->taxType = BirSetting::TAX_TYPE_NON_VAT;
        $this->vatRate = '12.00';
        $this->startingNumber = '1';
        $this->isActive = false;

        if ($this->branchId === '') {
            return;
        }

        $branch = $this->editableBranches()->firstWhere('id', (int) $this->branchId);
        abort_unless(ctype_digit($this->branchId) && $branch !== null, 404);
        $setting = BirSetting::query()->where('branch_id', $branch->id)->first();

        if ($setting === null) {
            return;
        }

        $this->registeredName = $setting->registered_name;
        $this->tradeName = $setting->trade_name ?? '';
        $this->tin = $setting->tin;
        $this->branchCode = $setting->branch_code;
        $this->registeredAddress = $setting->registered_address;
        $this->rdoCode = $setting->rdo_code ?? '';
        $this->taxType = $setting->tax_type;
        $this->vatRate = (string) $setting->vat_rate;
        $this->permitNumber = $setting->permit_number ?? '';
        $this->permitDate = $setting->permit_date?->toDateString() ?? '';
        $this->invoiceFooter = $setting->invoice_footer ?? '';
        $this->isActive = $setting->is_active;

        $sequence = InvoiceSequence::query()->where('branch_id', $branch->id)
            ->where('document_type', InvoiceSequence::TYPE_SALES_INVOICE)->first();
        if ($sequence !== null) {
            $this->invoicePrefix = $sequence->prefix;
            $this->startingNumber = (string) $sequence->starting_number;
            $this->endingNumber = $sequence->ending_number === null ? '' : (string) $sequence->ending_number;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'branchId' => ['required', 'integer', Rule::exists('branches', 'id')],
            'registeredName' => ['required', 'string', 'max:200'],
            'tradeName' => ['nullable', 'string', 'max:200'],
            'tin' => ['required', 'string', 'max:30', 'regex:/^[0-9-]+$/'],
            'branchCode' => ['required', 'string', 'max:10', 'regex:/^[0-9]+$/'],
            'registeredAddress' => ['required', 'string', 'max:1000'],
            'rdoCode' => ['nullable', 'string', 'max:10'],
            'taxType' => ['required', Rule::in([BirSetting::TAX_TYPE_VAT, BirSetting::TAX_TYPE_NON_VAT])],
            'vatRate' => ['required_if:taxType,'.BirSetting::TAX_TYPE_VAT, 'numeric', 'min:0', 'max:100'],
            'permitNumber' => ['nullable', 'string', 'max:100'],
            'permitDate' => ['nullable', 'date'],
            'invoiceFooter' => ['nullable', 'string', 'max:1000'],
            'invoicePrefix' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/'],
            'startingNumber' => ['required', 'integer', 'min:1'],
            'endingNumber' => ['nullable', 'integer', 'gte:startingNumber'],
            'isActive' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated): void {
            $branch = Branch::query()->whereKey($validated['branchId'])->lockForUpdate()->firstOrFail();
            $originalId = DB::table('original_branch_inventory')->where('id', 1)->value('branch_id');
            abort_unless($branch->isActive() && $originalId !== null && $branch->id !== (int) $originalId, 404);

            $setting = BirSetting::query()->where('branch_id', $branch->id)->lockForUpdate()->first();
            $sequence = InvoiceSequence::query()->where('branch_id', $branch->id)
                ->where('document_type', InvoiceSequence::TYPE_SALES_INVOICE)->lockForUpdate()->first();
            $start = (int) $validated['startingNumber'];
            $issued = $sequence !== null && $sequence->current_number >= $sequence->starting_number;

            if ($issued && ($start !== $sequence->starting_number
                || $validated['invoicePrefix'] !== $sequence->prefix
                || $validated['branchCode'] !== $sequence->branch_code)) {
                throw ValidationException::withMessages([
                    'invoicePrefix' => 'Branch code, prefix, and starting number cannot change after an invoice is issued.',
                ]);
            }

            if ($issued && $validated['endingNumber'] !== '' && (int) $validated['endingNumber'] < $sequence->current_number) {
                throw ValidationException::withMessages(['endingNumber' => 'Ending number cannot be below the last issued invoice.']);
            }

            if (InvoiceSequence::query()->where('prefix', $validated['invoicePrefix'])
                ->when($sequence !== null, fn ($query) => $query->where('id', '!=', $sequence->id))->exists()) {
                throw ValidationException::withMessages(['invoicePrefix' => 'Use a distinct invoice prefix for each branch.']);
            }

            if (InvoiceSequence::query()->where('document_type', InvoiceSequence::TYPE_SALES_INVOICE)
                ->where('branch_code', $validated['branchCode'])
                ->when($sequence !== null, fn ($query) => $query->where('id', '!=', $sequence->id))->exists()) {
                throw ValidationException::withMessages(['branchCode' => 'This registered branch code already has an invoice sequence.']);
            }

            $data = [
                'registered_name' => trim($validated['registeredName']),
                'trade_name' => $this->optional($validated['tradeName']),
                'tin' => trim($validated['tin']),
                'branch_code' => $validated['branchCode'],
                'registered_address' => trim($validated['registeredAddress']),
                'rdo_code' => $this->optional($validated['rdoCode']),
                'tax_type' => $validated['taxType'],
                'vat_rate' => (float) $validated['vatRate'],
                'permit_number' => $this->optional($validated['permitNumber']),
                'permit_date' => $validated['permitDate'] ?: null,
                'invoice_footer' => $this->optional($validated['invoiceFooter']),
                'is_active' => $validated['isActive'],
            ];

            if ($setting === null) {
                $setting = BirSetting::query()->create(['branch_id' => $branch->id] + $data);
            } else {
                $setting->update($data);
            }

            $sequenceData = [
                'branch_code' => $validated['branchCode'],
                'prefix' => $validated['invoicePrefix'],
                'current_number' => $issued ? $sequence->current_number : $start - 1,
                'starting_number' => $start,
                'ending_number' => $validated['endingNumber'] === '' ? null : (int) $validated['endingNumber'],
                'is_active' => $validated['isActive'],
            ];

            if ($sequence === null) {
                InvoiceSequence::query()->create([
                    'branch_id' => $branch->id,
                    'document_type' => InvoiceSequence::TYPE_SALES_INVOICE,
                ] + $sequenceData);
            } else {
                $sequence->update($sequenceData);
            }

            Audit::record('branch.invoice_settings_updated', $setting, 'Branch invoice settings saved: '.$branch->name, [
                'branch_id' => $branch->id, 'branch_code' => $setting->branch_code,
                'is_active' => $setting->is_active,
            ]);
        });

        session()->flash('success', 'Branch invoice settings saved.');
    }

    private function optional(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function editableBranches()
    {
        $originalId = DB::table('original_branch_inventory')->where('id', 1)->value('branch_id');

        return Branch::query()->where('status', Branch::STATUS_ACTIVE)
            ->when($originalId === null, fn ($query) => $query->where('id', -1))
            ->when($originalId !== null, fn ($query) => $query->where('id', '!=', $originalId))
            ->orderBy('name')->get(['id', 'name', 'code']);
    }

    public function render()
    {
        $branches = $this->editableBranches();
        if ($this->branchId !== '') {
            abort_unless(ctype_digit($this->branchId) && $branches->contains('id', (int) $this->branchId), 404);
        }

        $lastIssuedNumber = $this->branchId === '' ? null : InvoiceSequence::query()
            ->where('branch_id', (int) $this->branchId)
            ->where('document_type', InvoiceSequence::TYPE_SALES_INVOICE)
            ->value('current_number');

        return view('livewire.settings.branch-invoice-settings', compact('branches', 'lastIssuedNumber'));
    }
}
