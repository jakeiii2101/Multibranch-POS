<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable([
    'sale_number',
    'invoice_number',
    'user_id',
    'branch_id',
    'subtotal',
    'discount_type',
    'discount_value',
    'discount_amount',
    'total',
    'cash_received',
    'change_due',
    'status',
    'completed_at',
    'tax_type',
    'vatable_sales',
    'vat_amount',
    'vat_exempt_sales',
    'zero_rated_sales',
    'non_vat_sales',
    'seller_snapshot',
    'buyer_name',
    'buyer_tin',
    'buyer_address',
    'buyer_business_style',
    'discount_beneficiary_name',
    'discount_id_number',
    'vat_exemption_amount',
])]
class Sale extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';
    public const DISCOUNT_FIXED = 'fixed';
    public const DISCOUNT_PERCENTAGE = 'percentage';

    public const DISCOUNT_SENIOR = 'senior';

    public const DISCOUNT_PWD = 'pwd';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function adjustment(): HasOne
    {
        return $this->hasOne(SaleAdjustment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(SaleRefund::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Sale $sale): void {
            if ($sale->status === self::STATUS_COMPLETED) {
                throw new LogicException('Completed sales are financial history and cannot be deleted.');
            }
        });

        static::updating(function (Sale $sale): void {
            if ($sale->getOriginal('status') !== self::STATUS_COMPLETED) {
                return;
            }

            $protected = [
                'sale_number',
                'invoice_number',
                'user_id',
                'branch_id',
                'subtotal',
                'discount_type',
                'discount_value',
                'discount_amount',
                'total',
                'cash_received',
                'change_due',
                'status',
                'completed_at',
                'tax_type',
                'vatable_sales',
                'vat_amount',
                'vat_exempt_sales',
                'zero_rated_sales',
                'non_vat_sales',
                'seller_snapshot',
                'buyer_name',
                'buyer_tin',
                'buyer_address',
                'buyer_business_style',
                'discount_beneficiary_name',
                'discount_id_number',
                'vat_exemption_amount',
            ];

            if ($sale->isDirty($protected)) {
                throw new LogicException('Completed sales are immutable financial history.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_due' => 'decimal:2',
            'completed_at' => 'datetime',
            'vatable_sales' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'vat_exempt_sales' => 'decimal:2',
            'zero_rated_sales' => 'decimal:2',
            'non_vat_sales' => 'decimal:2',
            'seller_snapshot' => 'array',
            'vat_exemption_amount' => 'decimal:2',
        ];
    }
}
