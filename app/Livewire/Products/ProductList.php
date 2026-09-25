<?php

namespace App\Livewire\Products;

use App\Models\Category;
use App\Models\Product;
use App\Support\Audit;
use App\Support\LegacyStockMirror;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProductList extends Component
{
    public ?int $editingId = null;

    public ?int $categoryId = null;

    public string $sku = '';

    public string $barcode = '';

    public string $name = '';

    public string $costPrice = '0.00';

    public string $sellingPrice = '';

    public int $stockQuantity = 0;

    public int $lowStockLevel = 5;

    public string $status = Product::STATUS_ACTIVE;

    public string $taxType = Product::TAX_VATABLE;

    public bool $isSeniorPwdDiscountEligible = false;

    public bool $showForm = false;

    public function mount(): void
    {
        $this->showForm = request()->boolean('create');
    }

    public function boot(): void
    {
        abort_unless(
            auth()->check() && auth()->user()->isActive() && auth()->user()->isAdmin(),
            403,
        );
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);

        $this->editingId = $product->id;
        $this->categoryId = $product->category_id;
        $this->sku = $product->sku;
        $this->barcode = $product->barcode ?? '';
        $this->name = $product->name;
        $this->costPrice = (string) $product->cost_price;
        $this->sellingPrice = (string) $product->selling_price;
        $this->stockQuantity = $product->stock_quantity;
        $this->lowStockLevel = $product->low_stock_level;
        $this->status = $product->status;
        $this->taxType = $product->tax_type;
        $this->isSeniorPwdDiscountEligible = $product->is_senior_pwd_discount_eligible;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $rules = [
            'categoryId' => ['required', 'integer', Rule::exists('categories', 'id')->where('status', Category::STATUS_ACTIVE)],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($this->editingId)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:150'],
            'costPrice' => ['required', 'numeric', 'min:0'],
            'sellingPrice' => ['required', 'numeric', 'min:0'],
            'lowStockLevel' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in([Product::STATUS_ACTIVE, Product::STATUS_INACTIVE])],
            'taxType' => ['required', Rule::in([
                Product::TAX_VATABLE,
                Product::TAX_VAT_EXEMPT,
                Product::TAX_ZERO_RATED,
            ])],
            'isSeniorPwdDiscountEligible' => ['boolean'],
        ];

        if ($this->editingId === null) {
            $rules['stockQuantity'] = ['required', 'integer', 'min:0'];
        }

        $validated = $this->validate($rules);

        if ($validated['isSeniorPwdDiscountEligible'] && $validated['taxType'] !== Product::TAX_VATABLE) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'isSeniorPwdDiscountEligible' => 'Senior/PWD eligibility may only be enabled for VATable products.',
            ]);
        }

        $data = [
            'category_id' => $validated['categoryId'],
            'sku' => trim($validated['sku']),
            'barcode' => filled($validated['barcode']) ? trim($validated['barcode']) : null,
            'name' => trim($validated['name']),
            'cost_price' => $validated['costPrice'],
            'selling_price' => $validated['sellingPrice'],
            'low_stock_level' => $validated['lowStockLevel'],
            'status' => $validated['status'],
            'tax_type' => $validated['taxType'],
            'is_senior_pwd_discount_eligible' => $validated['isSeniorPwdDiscountEligible'],
        ];

        DB::transaction(function () use ($data, $validated): void {
            if ($this->editingId !== null) {
                $product = Product::query()->lockForUpdate()->findOrFail($this->editingId);
                $previousStock = $product->stock_quantity;
                $before = $product->only([
                    'category_id',
                    'sku',
                    'barcode',
                    'name',
                    'cost_price',
                    'selling_price',
                    'low_stock_level',
                    'status',
                    'tax_type',
                    'is_senior_pwd_discount_eligible',
                ]);
                $oldSellingPrice = (string) $product->selling_price;

                $product->update($data);
                $product->refresh();
                app(LegacyStockMirror::class)->sync($product, $previousStock);

                Audit::record(
                    'product.updated',
                    $product,
                    'Product updated.',
                    [
                        'before' => $before,
                        'after' => $product->only(array_keys($before)),
                    ],
                );

                if ($oldSellingPrice !== (string) $product->selling_price) {
                    Audit::record(
                        'product.price_changed',
                        $product,
                        'Product selling price changed.',
                        [
                            'old_selling_price' => $oldSellingPrice,
                            'new_selling_price' => (string) $product->selling_price,
                        ],
                    );
                }

                session()->flash('success', 'Product updated successfully.');
            } else {
                $data['stock_quantity'] = $validated['stockQuantity'];
                $product = Product::query()->create($data);
                app(LegacyStockMirror::class)->sync($product, null);

                Audit::record(
                    'product.created',
                    $product,
                    'Product created.',
                    [
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'selling_price' => (string) $product->selling_price,
                        'stock_quantity' => $product->stock_quantity,
                        'status' => $product->status,
                    ],
                );

                session()->flash('success', 'Product created successfully.');
            }
        });

        $this->resetForm();
    }

    public function delete(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);

        if ($product->stockMovements()->exists()) {
            session()->flash('error', 'This product has inventory history and cannot be deleted. Set it to inactive instead.');
            return;
        }

        $originalBranchId = DB::table('original_branch_inventory')->where('id', 1)->value('branch_id');
        if ($product->branchProducts()
            ->when($originalBranchId !== null, fn ($query) => $query->where('branch_id', '!=', $originalBranchId))
            ->exists()) {
            session()->flash('error', 'This product is assigned to another branch. Deactivate it instead.');
            return;
        }

        DB::transaction(function () use ($product): void {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            app(LegacyStockMirror::class)->remove($product);

            Audit::record(
                'product.deleted',
                $product,
                'Product deleted before any inventory history existed.',
                [
                    'sku' => $product->sku,
                    'name' => $product->name,
                ],
            );

            $product->delete();
        });

        if ($this->editingId === $productId) {
            $this->resetForm();
        }

        session()->flash('success', 'Product deleted successfully.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->categoryId = null;
        $this->sku = '';
        $this->barcode = '';
        $this->name = '';
        $this->costPrice = '0.00';
        $this->sellingPrice = '';
        $this->stockQuantity = 0;
        $this->lowStockLevel = 5;
        $this->status = Product::STATUS_ACTIVE;
        $this->taxType = Product::TAX_VATABLE;
        $this->isSeniorPwdDiscountEligible = false;
        $this->showForm = false;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.products.product-list', [
            'products' => Product::query()->with('category')->orderBy('name')->get(),
            'categories' => Category::query()->where('status', Category::STATUS_ACTIVE)->orderBy('name')->get(),
        ]);
    }
}
