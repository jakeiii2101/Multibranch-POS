<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'branch_id',
    'user_id',
    'type',
    'quantity',
    'stock_before',
    'stock_after',
    'reference',
    'reason',
])]
class StockMovement extends Model
{
    use HasFactory;

    public const TYPE_STOCK_IN = 'stock_in';

    public const TYPE_STOCK_OUT = 'stock_out';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_SALE = 'sale';

    public const TYPE_VOID = 'void';

    public const TYPE_REFUND = 'refund';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
        ];
    }
}
