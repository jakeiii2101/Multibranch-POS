<?php

namespace App\Livewire\Pos;

use App\Models\BirSetting;
use App\Models\Branch;
use App\Models\DailyClosing;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Support\Audit;
use App\Support\InvoiceNumberService;
use App\Support\LegacyStockMirror;
use App\Support\SaleTaxCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SaleTerminal extends Component
{
    public string $search = '';

    /** @var array<int, array{id:int,name:string,sku:string,barcode:?string,price:float,quantity:int,stock:int}> */
    public array $cart = [];

    public string $cashReceived = '';

    public string $paymentMethod = Payment::METHOD_CASH;

    public string $paymentReference = '';

    public string $discountType = Sale::DISCOUNT_FIXED;

    public string $discountValue = '';

    public ?string $appliedDiscountType = null;

    public float $appliedDiscountValue = 0.0;

    public string $buyerName = '';
    public string $buyerTin = '';
    public string $buyerAddress = '';
    public string $buyerBusinessStyle = '';
    public string $discountBeneficiaryName = '';
    public string $discountIdNumber = '';

    public ?int $lastSaleId = null;

    public function boot(): void
    {
        $user = auth()->user();

        abort_unless(
            $user !== null && $user->isActive() && ($user->isAdmin() || $user->isCashier()),
            403,
        );
    }

    public function addBySearch(): void
    {
        $term = trim($this->search);

        if ($term === '') {
            return;
        }

        $product = Product::query()
            ->where('status', Product::STATUS_ACTIVE)
            ->where(function ($query) use ($term): void {
                $query->where('barcode', $term)
                    ->orWhere('sku', $term);
            })
            ->first();

        if ($product === null) {
            $this->addError('search', 'No active product found with that barcode or SKU.');
            return;
        }

        $this->addProduct($product->id);
        $this->search = '';
        $this->resetErrorBag('search');
    }

    public function addProduct(int $productId): void
    {
        $product = Product::query()
            ->where('status', Product::STATUS_ACTIVE)
            ->findOrFail($productId);

        if ($product->stock_quantity < 1) {
            $this->addError('cart', $product->name.' is out of stock.');
            return;
        }

        if (isset($this->cart[$productId])) {
            $this->increase($productId);
            return;
        }

        $this->cart[$productId] = [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'price' => (float) $product->selling_price,
            'quantity' => 1,
            'stock' => $product->stock_quantity,
            'tax_type' => $product->tax_type,
            'is_senior_pwd_discount_eligible' => $product->is_senior_pwd_discount_eligible,
        ];

        $this->resetErrorBag('cart');
    }

    public function increase(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $product = Product::query()->findOrFail($productId);
        $nextQuantity = $this->cart[$productId]['quantity'] + 1;

        if ($nextQuantity > $product->stock_quantity) {
            $this->addError('cart', 'Not enough stock for '.$product->name.'.');
            return;
        }

        $this->cart[$productId]['quantity'] = $nextQuantity;
        $this->cart[$productId]['stock'] = $product->stock_quantity;
        $this->resetErrorBag('cart');
    }

    public function decrease(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        if ($this->cart[$productId]['quantity'] <= 1) {
            $this->remove($productId);
            return;
        }

        $this->cart[$productId]['quantity']--;
    }

    public function remove(int $productId): void
    {
        unset($this->cart[$productId]);
        $this->resetErrorBag('cart');
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->resetDiscountState();
        $this->resetPaymentState();
        $this->resetBuyerState();
        $this->resetValidation();
    }

    public function applyDiscount(): void
    {
        if ($this->cart === []) {
            $this->addError('cart', 'Add at least one product before applying a discount.');
            return;
        }

        $validated = $this->validate([
            'discountType' => ['required', Rule::in([
                Sale::DISCOUNT_FIXED,
                Sale::DISCOUNT_PERCENTAGE,
                Sale::DISCOUNT_SENIOR,
                Sale::DISCOUNT_PWD,
            ])],
            'discountValue' => [Rule::requiredIf(! $this->isRegulatedDiscount($this->discountType)), 'nullable', 'numeric', 'gt:0'],
        ]);

        $regulated = $this->isRegulatedDiscount($validated['discountType']);
        $value = $regulated ? 20.0 : round((float) $validated['discountValue'], 2);
        $subtotal = $this->cartSubtotal();

        if ($regulated && ! collect($this->cart)->contains('is_senior_pwd_discount_eligible', true)) {
            $this->addError('discountType', 'The cart has no product eligible for a Senior/PWD discount.');
            return;
        }

        if ($validated['discountType'] === Sale::DISCOUNT_PERCENTAGE && $value > 100) {
            $this->addError('discountValue', 'Percentage discount cannot exceed 100%.');
            return;
        }

        if ($validated['discountType'] === Sale::DISCOUNT_FIXED && $value > $subtotal) {
            $this->addError('discountValue', 'Fixed discount cannot exceed the current subtotal.');
            return;
        }

        $this->appliedDiscountType = $validated['discountType'];
        $this->appliedDiscountValue = $value;
        $this->resetErrorBag('discountType');
        $this->resetErrorBag('discountValue');
    }

    public function clearDiscount(): void
    {
        $this->resetDiscountState();
        $this->resetErrorBag('discountType');
        $this->resetErrorBag('discountValue');
    }

    public function completeSale(InvoiceNumberService $invoiceNumberService, SaleTaxCalculator $calculator): void
    {
        if ($this->cart === []) {
            $this->addError('cart', 'Add at least one product before completing the sale.');
            return;
        }

        $rules = [
            'paymentMethod' => ['required', Rule::in([
                Payment::METHOD_CASH,
                Payment::METHOD_GCASH,
                Payment::METHOD_CARD,
                Payment::METHOD_OTHER,
            ])],
            'buyerName' => ['nullable', 'string', 'max:200', 'required_with:buyerTin,buyerAddress,buyerBusinessStyle'],
            'buyerTin' => ['nullable', 'string', 'max:30', 'regex:/^[0-9-]+$/'],
            'buyerAddress' => ['nullable', 'string', 'max:500'],
            'buyerBusinessStyle' => ['nullable', 'string', 'max:200'],
        ];

        if ($this->isRegulatedDiscount($this->appliedDiscountType)) {
            $rules['discountBeneficiaryName'] = ['required', 'string', 'max:200'];
            $rules['discountIdNumber'] = ['required', 'string', 'max:100'];
        }

        if ($this->paymentMethod === Payment::METHOD_CASH) {
            $rules['cashReceived'] = ['required', 'numeric', 'min:0'];
        } else {
            $rules['paymentReference'] = ['required', 'string', 'max:100'];
        }

        $validated = $this->validate($rules, [
            'paymentReference.required' => 'A payment reference is required for non-cash payments.',
        ]);

        $discountType = $this->appliedDiscountType;
        $discountValue = $this->appliedDiscountValue;
        $paymentMethod = $validated['paymentMethod'];

        $sale = DB::transaction(function () use ($validated, $discountType, $discountValue, $paymentMethod, $invoiceNumberService, $calculator): Sale {
            $branchId = app(LegacyStockMirror::class)->activeBranchId();
            if ($branchId !== null) {
                $branch = Branch::query()->whereKey($branchId)->lockForUpdate()->first();
                if ($branch === null || ! $branch->isActive() || ! auth()->user()->canAccessBranch($branch)) {
                    throw ValidationException::withMessages([
                        'cart' => 'Checkout requires access to the active original branch. Ask an administrator to assign your branch.',
                    ]);
                }
            }

            if (DailyClosing::query()->whereDate('business_date', now())->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'cart' => 'Today already has a Z-reading. New sales are locked for this business date.',
                ]);
            }

            $lines = [];
            $subtotal = 0.0;

            foreach (collect($this->cart)->sortKeys() as $item) {
                $product = Product::query()->lockForUpdate()->findOrFail($item['id']);
                $quantity = (int) $item['quantity'];

                if (! $product->isActive()) {
                    throw ValidationException::withMessages([
                        'cart' => $product->name.' is no longer active.',
                    ]);
                }

                if ($quantity > $product->stock_quantity) {
                    throw ValidationException::withMessages([
                        'cart' => 'Not enough stock for '.$product->name.'. Available: '.$product->stock_quantity.'.',
                    ]);
                }

                $before = $product->stock_quantity;
                $after = $before - $quantity;
                $unitPrice = round((float) $product->selling_price, 2);
                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal = round($subtotal + $lineTotal, 2);

                $lines[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'before' => $before,
                    'after' => $after,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'tax_type' => $product->tax_type,
                    'is_senior_pwd_discount_eligible' => $product->is_senior_pwd_discount_eligible,
                ];
            }

            if ($this->isRegulatedDiscount($discountType) && ! collect($lines)->contains('is_senior_pwd_discount_eligible', true)) {
                throw ValidationException::withMessages([
                    'cart' => 'The cart no longer contains a product eligible for this statutory discount.',
                ]);
            }

            $invoice = $invoiceNumberService->next();
            $birSetting = $invoice['setting'];
            $calculation = $calculator->calculate($lines, $birSetting, $discountType, $discountValue);
            $lines = $calculation['lines'];
            $discountAmount = $calculation['discount_amount'];
            $total = $calculation['total'];

            if ($paymentMethod === Payment::METHOD_CASH) {
                $amountTendered = round((float) $validated['cashReceived'], 2);

                if ($amountTendered < $total) {
                    throw ValidationException::withMessages([
                        'cashReceived' => 'Cash received must be at least the sale total.',
                    ]);
                }

                $changeDue = round($amountTendered - $total, 2);
                $paymentReference = null;
                $saleCashReceived = $amountTendered;
                $saleChangeDue = $changeDue;
            } else {
                $paymentReference = trim((string) $validated['paymentReference']);

                if ($paymentReference === '') {
                    throw ValidationException::withMessages([
                        'paymentReference' => 'A payment reference is required for non-cash payments.',
                    ]);
                }

                $amountTendered = $total;
                $changeDue = 0.0;
                $saleCashReceived = 0.0;
                $saleChangeDue = 0.0;
            }

            $sale = Sale::query()->create([
                'sale_number' => $invoice['invoice_number'],
                'invoice_number' => $invoice['invoice_number'],
                'user_id' => auth()->id(),
                'branch_id' => $branchId,
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountType === null ? 0 : $discountValue,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'cash_received' => $saleCashReceived,
                'change_due' => $saleChangeDue,
                'status' => Sale::STATUS_COMPLETED,
                'completed_at' => now(),
                'tax_type' => $birSetting->tax_type,
                'seller_snapshot' => $birSetting->invoiceSnapshot(),
                'buyer_name' => $this->nullableTrim($validated['buyerName'] ?? null),
                'buyer_tin' => $this->nullableTrim($validated['buyerTin'] ?? null),
                'buyer_address' => $this->nullableTrim($validated['buyerAddress'] ?? null),
                'buyer_business_style' => $this->nullableTrim($validated['buyerBusinessStyle'] ?? null),
                'discount_beneficiary_name' => $this->isRegulatedDiscount($discountType) ? trim($validated['discountBeneficiaryName']) : null,
                'discount_id_number' => $this->isRegulatedDiscount($discountType) ? trim($validated['discountIdNumber']) : null,
                'vat_exemption_amount' => $calculation['vat_exemption_amount'],
                'vatable_sales' => $calculation['vatable_sales'],
                'vat_amount' => $calculation['vat_amount'],
                'vat_exempt_sales' => $calculation['vat_exempt_sales'],
                'zero_rated_sales' => $calculation['zero_rated_sales'],
                'non_vat_sales' => $calculation['non_vat_sales'],
            ]);

            $sale->payment()->create([
                'method' => $paymentMethod,
                'amount' => $total,
                'amount_tendered' => $amountTendered,
                'change_due' => $changeDue,
                'reference' => $paymentReference,
            ]);

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];

                $sale->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                    'tax_type' => $line['tax_type'],
                    'is_senior_pwd_discount_eligible' => $line['is_senior_pwd_discount_eligible'],
                    'discount_amount' => $line['discount_amount'],
                    'vat_amount' => $line['vat_amount'],
                    'vat_exemption_amount' => $line['vat_exemption_amount'],
                    'net_total' => $line['net_total'],
                ]);

                $product->update(['stock_quantity' => $line['after']]);
                $branchId = app(LegacyStockMirror::class)->sync($product, $line['before']);

                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'branch_id' => $branchId,
                    'user_id' => auth()->id(),
                    'type' => StockMovement::TYPE_SALE,
                    'quantity' => -$line['quantity'],
                    'stock_before' => $line['before'],
                    'stock_after' => $line['after'],
                    'reference' => $sale->sale_number,
                    'reason' => 'POS sale',
                ]);
            }

            Audit::record(
                'sale.completed',
                $sale,
                'POS sale completed: '.$sale->sale_number,
                [
                    'sale_number' => $sale->sale_number,
                    'invoice_number' => $sale->invoice_number,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'total' => $total,
                    'payment_method' => $paymentMethod,
                    'item_count' => (int) collect($lines)->sum(fn (array $line): int => $line['quantity']),
                ],
            );

            return $sale;
        });

        $this->lastSaleId = $sale->id;
        $this->cart = [];
        $this->search = '';
        $this->resetDiscountState();
        $this->resetPaymentState();
        $this->resetBuyerState();
        $this->resetValidation();
        session()->flash('success', 'Sale completed successfully.');
    }

    private function cartSubtotal(): float
    {
        return round(collect($this->cart)->sum(
            fn (array $item): float => $item['price'] * $item['quantity']
        ), 2);
    }

    private function previewTotals(): array
    {
        $setting = BirSetting::query()->where('is_active', true)->first();
        if ($setting === null || $this->cart === []) {
            return ['discount_amount' => 0.0, 'vat_exemption_amount' => 0.0, 'total' => $this->cartSubtotal()];
        }

        $lines = collect($this->cart)->values()->map(fn (array $item): array => [
            'line_total' => round($item['price'] * $item['quantity'], 2),
            'tax_type' => $item['tax_type'],
            'is_senior_pwd_discount_eligible' => $item['is_senior_pwd_discount_eligible'],
        ])->all();

        return app(SaleTaxCalculator::class)->calculate($lines, $setting, $this->appliedDiscountType, $this->appliedDiscountValue);
    }

    private function resetDiscountState(): void
    {
        $this->discountType = Sale::DISCOUNT_FIXED;
        $this->discountValue = '';
        $this->appliedDiscountType = null;
        $this->appliedDiscountValue = 0.0;
    }

    private function resetPaymentState(): void
    {
        $this->paymentMethod = Payment::METHOD_CASH;
        $this->paymentReference = '';
        $this->cashReceived = '';
    }

    private function resetBuyerState(): void
    {
        $this->buyerName = '';
        $this->buyerTin = '';
        $this->buyerAddress = '';
        $this->buyerBusinessStyle = '';
        $this->discountBeneficiaryName = '';
        $this->discountIdNumber = '';
    }

    private function isRegulatedDiscount(?string $type): bool
    {
        return in_array($type, [Sale::DISCOUNT_SENIOR, Sale::DISCOUNT_PWD], true);
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function render()
    {
        $term = trim($this->search);

        $products = Product::query()
            ->where('status', Product::STATUS_ACTIVE)
            ->where('stock_quantity', '>', 0)
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', '%'.$term.'%')
                        ->orWhere('sku', 'like', '%'.$term.'%')
                        ->orWhere('barcode', 'like', '%'.$term.'%');
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        $subtotal = $this->cartSubtotal();
        $preview = $this->previewTotals();
        $discountAmount = $preview['discount_amount'];
        $vatExemptionAmount = $preview['vat_exemption_amount'];
        $total = $preview['total'];
        $cash = is_numeric($this->cashReceived) ? (float) $this->cashReceived : 0.0;
        $changeDue = $this->paymentMethod === Payment::METHOD_CASH
            ? max(0, round($cash - $total, 2))
            : 0.0;

        return view('livewire.pos.sale-terminal', [
            'products' => $products,
            'subtotal' => $subtotal,
            'discountAmount' => $discountAmount,
            'vatExemptionAmount' => $vatExemptionAmount,
            'total' => $total,
            'changeDue' => $changeDue,
        ]);
    }
}
