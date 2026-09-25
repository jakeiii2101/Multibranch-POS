<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'registered_name',
    'branch_id',
    'trade_name',
    'tin',
    'branch_code',
    'registered_address',
    'rdo_code',
    'tax_type',
    'vat_rate',
    'permit_number',
    'permit_date',
    'invoice_footer',
    'is_active',
])]
class BirSetting extends Model
{
    public const TAX_TYPE_VAT = 'vat';

    public const TAX_TYPE_NON_VAT = 'non_vat';

    /** @return array<string, mixed> */
    public function invoiceSnapshot(): array
    {
        return [
            'registered_name' => $this->registered_name,
            'trade_name' => $this->trade_name,
            'tin' => $this->tin,
            'branch_code' => $this->branch_code,
            'registered_address' => $this->registered_address,
            'rdo_code' => $this->rdo_code,
            'tax_type' => $this->tax_type,
            'vat_rate' => (string) $this->vat_rate,
            'permit_number' => $this->permit_number,
            'permit_date' => $this->permit_date?->toDateString(),
            'invoice_footer' => $this->invoice_footer,
        ];
    }

    protected function casts(): array
    {
        return [
            'vat_rate' => 'decimal:2',
            'permit_date' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
