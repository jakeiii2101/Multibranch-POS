<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'document_type',
    'branch_id',
    'branch_code',
    'prefix',
    'current_number',
    'starting_number',
    'ending_number',
    'is_active',
])]
class InvoiceSequence extends Model
{
    public const TYPE_SALES_INVOICE = 'sales_invoice';

    protected function casts(): array
    {
        return [
            'current_number' => 'integer',
            'starting_number' => 'integer',
            'ending_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
