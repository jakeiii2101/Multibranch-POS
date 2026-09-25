<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category_id',
    'sku',
    'barcode',
    'name',
    'cost_price',
    'selling_price',
    'stock_quantity',
    'low_stock_level',
    'status',
    'tax_type',
    'is_senior_pwd_discount_eligible',
])]
class Product extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const TAX_VATABLE = 'vatable';

    public const TAX_VAT_EXEMPT = 'vat_exempt';

    public const TAX_ZERO_RATED = 'zero_rated';

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function branchProducts(): HasMany
    {
        return $this->hasMany(BranchProduct::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->low_stock_level;
    }

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_level' => 'integer',
            'is_senior_pwd_discount_eligible' => 'boolean',
        ];
    }
}
