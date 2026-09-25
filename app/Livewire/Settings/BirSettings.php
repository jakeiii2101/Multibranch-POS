<?php

namespace App\Livewire\Settings;

use App\Models\BirSetting;
use App\Models\InvoiceSequence;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class BirSettings extends Component
{
    public string $registeredName = '';

    public string $tradeName = '';

    public string $tin = '';

    public string $branchCode = '00000';

    public string $registeredAddress = '';

    public string $rdoCode = '';

    public string $taxType = BirSetting::TAX_TYPE_NON_VAT;

    public string $vatRate = '12.00';

    public string $permitNumber = '';

    public string $permitDate = '';

    public string $invoicePrefix = 'SI-';

    public string $startingNumber = '1';

    public string $endingNumber = '';

    public string $invoiceFooter = '';

    public bool $isActive = false;

    public function boot(): void
    {
        abort_unless(
            auth()->check() && auth()->user()->isActive() && auth()->user()->isAdmin(),
            403,
        );
    }

    public function mount(): void
    {
        $setting = BirSetting::query()->whereNull('branch_id')->latest('id')->first();

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

        $sequence = InvoiceSequence::query()
            ->where('document_type', InvoiceSequence::TYPE_SALES_INVOICE)
            ->whereNull('branch_id')
            ->where('branch_code', $setting->branch_code)
            ->first();

        if ($sequence !== null) {
            $this->invoicePrefix = $sequence->prefix;
            $this->startingNumber = (string) $sequence->starting_number;
            $this->endingNumber = $sequence->ending_number === null ? '' : (string) $sequence->ending_number;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
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
            'invoicePrefix' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/'],
            'startingNumber' => ['required', 'integer', 'min:1'],
            'endingNumber' => ['nullable', 'integer', 'gte:startingNumber'],
            'invoiceFooter' => ['nullable', 'string', 'max:1000'],
            'isActive' => ['boolean'],
        ], [
            'tin.regex' => 'The TIN may contain numbers and hyphens only.',
            'branchCode.regex' => 'The branch code must contain numbers only.',
            'invoicePrefix.regex' => 'The invoice prefix may contain uppercase letters, numbers, and hyphens only.',
        ]);

        DB::transaction(function () use ($validated): void {
            $sequence = InvoiceSequence::query()
                ->where('document_type', InvoiceSequence::TYPE_SALES_INVOICE)
                ->whereNull('branch_id')
                ->where('branch_code', $validated['branchCode'])
                ->lockForUpdate()
                ->first();

            $startingNumber = (int) $validated['startingNumber'];

            if ($sequence !== null && $sequence->current_number > 0 && $startingNumber !== $sequence->starting_number) {
                throw ValidationException::withMessages([
                    'startingNumber' => 'Starting number cannot be changed after an invoice has been issued.',
                ]);
            }

            if ($sequence !== null && $sequence->current_number > 0 && $validated['invoicePrefix'] !== $sequence->prefix) {
                throw ValidationException::withMessages([
                    'invoicePrefix' => 'Invoice prefix cannot be changed after an invoice has been issued.',
                ]);
            }

            BirSetting::query()->whereNull('branch_id')->update(['is_active' => false]);

            $setting = BirSetting::query()->updateOrCreate(
                ['id' => BirSetting::query()->whereNull('branch_id')->latest('id')->value('id')],
                [
                    'registered_name' => trim($validated['registeredName']),
                    'trade_name' => $this->nullableString($validated['tradeName']),
                    'tin' => trim($validated['tin']),
                    'branch_code' => trim($validated['branchCode']),
                    'registered_address' => trim($validated['registeredAddress']),
                    'rdo_code' => $this->nullableString($validated['rdoCode']),
                    'tax_type' => $validated['taxType'],
                    'vat_rate' => (float) $validated['vatRate'],
                    'permit_number' => $this->nullableString($validated['permitNumber']),
                    'permit_date' => $validated['permitDate'] ?: null,
                    'invoice_footer' => $this->nullableString($validated['invoiceFooter']),
                    'is_active' => $validated['isActive'],
                ],
            );

            InvoiceSequence::query()->updateOrCreate(
                [
                    'document_type' => InvoiceSequence::TYPE_SALES_INVOICE,
                    'branch_id' => null,
                    'branch_code' => $validated['branchCode'],
                ],
                [
                    'prefix' => $validated['invoicePrefix'],
                    'current_number' => $sequence?->current_number ?? ($startingNumber - 1),
                    'starting_number' => $startingNumber,
                    'ending_number' => $validated['endingNumber'] === '' ? null : (int) $validated['endingNumber'],
                    'is_active' => true,
                ],
            );

            Audit::record(
                'bir.settings.updated',
                $setting,
                'BIR invoicing settings updated.',
                [
                    'branch_code' => $setting->branch_code,
                    'tax_type' => $setting->tax_type,
                    'invoice_prefix' => $validated['invoicePrefix'],
                    'is_active' => $setting->is_active,
                ],
            );
        });

        session()->flash('success', 'BIR settings saved successfully.');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function render()
    {
        $lastIssuedNumber = InvoiceSequence::query()
            ->where('document_type', InvoiceSequence::TYPE_SALES_INVOICE)
            ->whereNull('branch_id')
            ->where('branch_code', $this->branchCode)
            ->value('current_number');

        return view('livewire.settings.bir-settings', [
            'lastIssuedNumber' => $lastIssuedNumber,
        ]);
    }
}
