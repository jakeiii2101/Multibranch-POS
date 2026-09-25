<?php

namespace App\Support;

use App\Models\BirSetting;
use App\Models\Branch;
use App\Models\InvoiceSequence;
use Illuminate\Validation\ValidationException;

class InvoiceNumberService
{
    /** @return array{invoice_number:string, setting:BirSetting} */
    public function next(?Branch $branch = null): array
    {
        $setting = BirSetting::query()->where('is_active', true)
            ->where('branch_id', $branch?->id)->first();

        if ($setting === null) {
            throw ValidationException::withMessages([
                'cart' => 'BIR invoicing is not configured. Ask an administrator to activate BIR Settings.',
            ]);
        }

        $sequence = InvoiceSequence::query()
            ->where('document_type', InvoiceSequence::TYPE_SALES_INVOICE)
            ->where('branch_id', $branch?->id)
            ->where('branch_code', $setting->branch_code)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            throw ValidationException::withMessages([
                'cart' => 'No active Sales Invoice sequence is configured for this branch.',
            ]);
        }

        $nextNumber = max($sequence->current_number + 1, $sequence->starting_number);

        if ($sequence->ending_number !== null && $nextNumber > $sequence->ending_number) {
            throw ValidationException::withMessages([
                'cart' => 'The configured Sales Invoice number range has been exhausted.',
            ]);
        }

        $sequence->update(['current_number' => $nextNumber]);

        return [
            'invoice_number' => $sequence->prefix.str_pad((string) $nextNumber, 12, '0', STR_PAD_LEFT),
            'setting' => $setting,
        ];
    }
}
