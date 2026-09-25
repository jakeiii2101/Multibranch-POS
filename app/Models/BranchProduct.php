<?php

namespace App\Models;

use Database\Factories\BranchProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'product_id', 'on_hand', 'reserved', 'reorder_level', 'price_override', 'is_available'])]
class BranchProduct extends Model
{
    /** @use HasFactory<BranchProductFactory> */
    use HasFactory;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'reorder_level' => 'integer',
            'price_override' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }
}
