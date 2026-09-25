<div class="sniper-page">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="sniper-kicker">Checkout workspace</div>
            <h1 class="sniper-title mt-2">Point of Sale{{ $registerBranch ? ' · '.$registerBranch->name : ' · Original register' }}</h1>
            <a href="{{ route('pos.select') }}" class="text-sm underline">Change register</a>
            <p class="sniper-copy mt-2">Scan or search products, build the cart, and complete the sale with fewer clicks.</p>
            <div class="mt-3 inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-sniper-slate ring-1 ring-slate-200">
                <svg class="h-4 w-4 text-sniper-navy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <span>{{ now()->format('M d, Y · h:i A') }} · {{ config('app.timezone') }}</span>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-sniper-navy font-heading text-xs font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
            <span>
                <span class="block text-[10px] font-semibold uppercase tracking-[0.12em] text-sniper-slate">Cashier</span>
                <span class="block text-sm font-semibold text-sniper-navy">{{ auth()->user()->name }}</span>
            </span>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-5 flex flex-col gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 sm:flex-row sm:items-center sm:justify-between">
            <span class="font-medium">{{ session('success') }}</span>
            @if ($lastSaleId)
                <a href="{{ route('sales.invoice', ['sale' => $lastSaleId], false) }}" target="_blank" class="font-semibold underline underline-offset-4">Print Sales Invoice</a>
            @endif
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
        <section class="sniper-card overflow-hidden">
            <div class="border-b border-slate-200 p-5 sm:p-6">
                <form wire:submit="addBySearch" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="pos-search" class="sniper-label">Product / SKU / Barcode</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input id="pos-search" wire:model.live.debounce.250ms="search" type="text" class="sniper-input pl-11" placeholder="Scan barcode or type product name" autofocus autocomplete="off" />
                        </div>
                        <x-input-error :messages="$errors->get('search')" class="mt-2" />
                        <p class="mt-2 text-[11px] text-sniper-slate">Press Enter to add an exact SKU/barcode match, or choose a product from the list below.</p>
                    </div>

                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('products', [], false) }}?create=1" wire:navigate class="sniper-btn-primary h-[42px] gap-2 px-5" title="Create a new product">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                            <span>Add Product</span>
                        </a>
                    @else
                        <button type="submit" class="sniper-btn-primary h-[42px] gap-2 px-5">
                            <span>Add to Cart</span>
                            <span aria-hidden="true">＋</span>
                        </button>
                    @endif
                </form>
            </div>

            <div class="flex items-center justify-between bg-slate-50 px-5 py-3 sm:px-6">
                <div>
                    <div class="text-xs font-bold uppercase tracking-[0.14em] text-sniper-navy">Available products</div>
                    <div class="mt-0.5 text-[11px] text-sniper-slate">Choose an item to add it to the cart</div>
                </div>
                <span class="sniper-badge-neutral">Live stock</span>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($products as $product)
                    <button type="button" wire:click="addProduct({{ $product->id }})" class="group flex w-full items-center justify-between gap-4 px-5 py-4 text-left hover:bg-slate-50 sm:px-6">
                        <div class="flex min-w-0 items-center gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-sniper-navy ring-1 ring-slate-200 group-hover:bg-white">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3 8 4-8 4-8-4 8-4Zm-8 4v10l8 4 8-4V7m-8 4v10"/></svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-sniper-navy">{{ $product->name }}</span>
                                <span class="mt-1 block truncate text-xs text-sniper-slate">SKU: {{ $product->sku }} @if($product->barcode) · Barcode: {{ $product->barcode }} @endif</span>
                            </span>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="font-heading text-base font-bold text-sniper-navy">₱{{ number_format((float) ($registerBranch ? ($product->branchProducts->first()?->price_override ?? $product->selling_price) : $product->selling_price), 2) }}</div>
                            <div class="mt-1 text-xs font-medium {{ ($registerBranch ? ($product->branchProducts->first()->on_hand - $product->branchProducts->first()->reserved <= $product->branchProducts->first()->reorder_level) : $product->isLowStock()) ? 'text-red-600' : 'text-emerald-600' }}">{{ ($registerBranch ? ($product->branchProducts->first()->on_hand - $product->branchProducts->first()->reserved <= $product->branchProducts->first()->reorder_level) : $product->isLowStock()) ? 'Low stock' : 'In stock' }} · {{ $registerBranch ? $product->branchProducts->first()->on_hand - $product->branchProducts->first()->reserved : $product->stock_quantity }}</div>
                        </div>
                    </button>
                @empty
                    <div class="px-6 py-14 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></div>
                        <div class="mt-3 text-sm font-semibold text-sniper-navy">No available products found</div>
                        <div class="mt-1 text-xs text-sniper-slate">Try a different product name, SKU, or barcode.</div>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="xl:sticky xl:top-24 xl:self-start">
            <div class="sniper-card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="sniper-kicker">Current sale</div>
                            <h2 class="mt-1 font-heading text-lg font-bold text-sniper-navy">Cart</h2>
                        </div>
                        @if ($cart)
                            <button type="button" wire:click="clearCart" wire:confirm="Clear the current cart?" class="rounded-lg px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Clear Cart</button>
                        @endif
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <a href="#pos-discount" class="flex items-center gap-2 rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 ring-1 ring-red-100">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 19 19 5M7.5 7.5h.01M16.5 16.5h.01"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/></svg>
                            Discount
                        </a>
                        <a href="#pos-payment" class="flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-sniper-navy ring-1 ring-slate-200">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
                            Cash · GCash · Card · Other
                        </a>
                    </div>
                </div>

                @error('cart')
                    <div class="mx-5 mt-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
                @enderror

                <div class="max-h-[360px] divide-y divide-slate-100 overflow-y-auto">
                    @forelse ($cart as $item)
                        <div wire:key="cart-{{ $item['id'] }}" class="px-5 py-4">
                            <div class="flex justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-sniper-navy">{{ $item['name'] }}</div>
                                    <div class="mt-1 text-xs text-sniper-slate">{{ $item['sku'] }} · ₱{{ number_format($item['price'], 2) }} each</div>
                                </div>
                                <div class="shrink-0 font-heading font-bold text-sniper-navy">₱{{ number_format($item['price'] * $item['quantity'], 2) }}</div>
                            </div>
                            <div class="mt-3 flex items-center justify-between">
                                <div class="inline-flex items-center overflow-hidden rounded-lg border border-slate-300 bg-white">
                                    <button type="button" wire:click="decrease({{ $item['id'] }})" class="px-3 py-1.5 text-sniper-navy hover:bg-slate-50">−</button>
                                    <span class="min-w-10 border-x border-slate-300 px-3 py-1.5 text-center text-sm font-semibold text-sniper-navy">{{ $item['quantity'] }}</span>
                                    <button type="button" wire:click="increase({{ $item['id'] }})" class="px-3 py-1.5 text-sniper-navy hover:bg-slate-50">+</button>
                                </div>
                                <button type="button" wire:click="remove({{ $item['id'] }})" class="text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-sniper-navy ring-1 ring-slate-200"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h2l2.4 10.2a2 2 0 0 0 2 1.5h7.8a2 2 0 0 0 1.9-1.4L21 7H6"/></svg></div>
                            <div class="mt-3 text-sm font-semibold text-sniper-navy">Your cart is empty</div>
                            <div class="mt-1 text-xs text-sniper-slate">Add a product to start the sale.</div>
                        </div>
                    @endforelse
                </div>

                <div class="border-t border-slate-200 bg-slate-50 px-5 py-5">
                    <div id="pos-discount" class="mb-5 scroll-mt-24 rounded-xl border border-red-100 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-sniper-red">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 19 19 5M7.5 7.5h.01M16.5 16.5h.01"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/></svg>
                                </span>
                                <div>
                                    <div class="text-xs font-bold uppercase tracking-[0.12em] text-sniper-navy">Discount</div>
                                    <div class="mt-0.5 text-[11px] text-sniper-slate">Promotional or statutory discount.</div>
                                </div>
                            </div>
                            @if ($appliedDiscountType)
                                <button type="button" wire:click="clearDiscount" class="shrink-0 text-xs font-semibold text-red-600 hover:text-red-700">Clear</button>
                            @endif
                        </div>

                        @if ($cart)
                            <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                                <div>
                                    <label for="discount-type" class="sniper-label">Type</label>
                                    <select id="discount-type" wire:model="discountType" class="sniper-input">
                                        <option value="fixed">Fixed amount</option>
                                        <option value="percentage">Percentage</option>
                                        <option value="senior">Senior Citizen (20%)</option>
                                        <option value="pwd">PWD (20%)</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('discountType')" class="mt-2" />
                                </div>
                                @if (! in_array($discountType, ['senior', 'pwd'], true))
                                <div>
                                    <label for="discount-value" class="sniper-label">Value</label>
                                    <input id="discount-value" wire:model="discountValue" type="number" step="0.01" min="0" class="sniper-input text-right" placeholder="0.00" />
                                    <x-input-error :messages="$errors->get('discountValue')" class="mt-2" />
                                </div>
                                @else
                                <div class="flex items-end"><div class="w-full rounded-lg bg-slate-50 px-3 py-3 text-sm font-semibold text-sniper-navy ring-1 ring-slate-200">Statutory rate: 20%</div></div>
                                @endif
                                <div class="flex items-end">
                                    <button type="button" wire:click="applyDiscount" class="sniper-btn-secondary h-[42px] w-full px-4 sm:w-auto">Apply</button>
                                </div>
                            </div>
                        @else
                            <div class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-sniper-slate ring-1 ring-slate-200">Add an item to the cart to enable discounts.</div>
                        @endif

                        @if ($appliedDiscountType)
                            <div class="mt-3 flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-800 ring-1 ring-emerald-200">
                                <span class="font-semibold">Discount applied</span>
                                <span>
                                    @if (in_array($appliedDiscountType, ['senior', 'pwd'], true))
                                        {{ $appliedDiscountType === 'senior' ? 'Senior Citizen' : 'PWD' }} · 20%
                                    @elseif ($appliedDiscountType === 'percentage')
                                        {{ number_format($appliedDiscountValue, 2) }}%
                                    @else
                                        ₱{{ number_format($appliedDiscountValue, 2) }}
                                    @endif
                                </span>
                            </div>
                        @endif
                        @if ($vatExemptionAmount > 0)
                            <div class="flex justify-between text-sky-700"><span>VAT exemption</span><span class="font-semibold">− ₱{{ number_format($vatExemptionAmount, 2) }}</span></div>
                        @endif
                    </div>

                    <div class="mt-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-[0.12em] text-sniper-navy">Buyer information</div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div><label for="buyer-name" class="sniper-label">Registered name</label><input id="buyer-name" wire:model="buyerName" class="sniper-input" maxlength="200" placeholder="Optional" /><x-input-error :messages="$errors->get('buyerName')" class="mt-2" /></div>
                            <div><label for="buyer-tin" class="sniper-label">TIN</label><input id="buyer-tin" wire:model="buyerTin" class="sniper-input" maxlength="30" placeholder="Optional" /><x-input-error :messages="$errors->get('buyerTin')" class="mt-2" /></div>
                            <div><label for="buyer-style" class="sniper-label">Business style</label><input id="buyer-style" wire:model="buyerBusinessStyle" class="sniper-input" maxlength="200" placeholder="Optional" /><x-input-error :messages="$errors->get('buyerBusinessStyle')" class="mt-2" /></div>
                            <div><label for="buyer-address" class="sniper-label">Address</label><input id="buyer-address" wire:model="buyerAddress" class="sniper-input" maxlength="500" placeholder="Optional" /><x-input-error :messages="$errors->get('buyerAddress')" class="mt-2" /></div>
                        </div>
                        @if (in_array($appliedDiscountType, ['senior', 'pwd'], true))
                            <div class="mt-4 border-t border-slate-200 pt-4"><div class="grid gap-3 sm:grid-cols-2"><div><label for="beneficiary-name" class="sniper-label">Beneficiary name</label><input id="beneficiary-name" wire:model="discountBeneficiaryName" class="sniper-input" maxlength="200" /><x-input-error :messages="$errors->get('discountBeneficiaryName')" class="mt-2" /></div><div><label for="discount-id" class="sniper-label">Senior/PWD ID number</label><input id="discount-id" wire:model="discountIdNumber" class="sniper-input" maxlength="100" /><x-input-error :messages="$errors->get('discountIdNumber')" class="mt-2" /></div></div></div>
                        @endif
                    </div>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-sniper-slate"><span>Subtotal</span><span class="font-medium text-sniper-navy">₱{{ number_format($subtotal, 2) }}</span></div>
                        @if ($discountAmount > 0)
                            <div class="flex justify-between text-emerald-700"><span>Discount</span><span class="font-semibold">− ₱{{ number_format($discountAmount, 2) }}</span></div>
                        @endif
                        <div class="flex items-end justify-between border-t border-slate-200 pt-3">
                            <span class="font-heading text-base font-bold text-sniper-navy">Grand Total</span>
                            <span class="font-heading text-2xl font-extrabold tracking-tight text-sniper-navy">₱{{ number_format($total, 2) }}</span>
                        </div>
                    </div>

                    <div id="pos-payment" class="mt-5 scroll-mt-24 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sniper-navy text-white">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
                            </span>
                            <div>
                                <div class="text-xs font-bold uppercase tracking-[0.12em] text-sniper-navy">Mode of Payment</div>
                                <div class="mt-0.5 text-[11px] text-sniper-slate">Choose Cash, GCash, Card, or Other.</div>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ([
                                'cash' => 'Cash',
                                'gcash' => 'GCash',
                                'card' => 'Card',
                                'other' => 'Other',
                            ] as $methodValue => $methodLabel)
                                <button type="button" wire:click="$set('paymentMethod', '{{ $methodValue }}')" class="rounded-lg px-2 py-2 text-xs font-semibold ring-1 transition {{ $paymentMethod === $methodValue ? 'bg-sniper-navy text-white ring-sniper-navy' : 'bg-white text-sniper-navy ring-slate-200 hover:ring-red-200' }}">{{ $methodLabel }}</button>
                            @endforeach
                        </div>

                        <div class="mt-3">
                            <label for="payment-method" class="sniper-label">Payment Method</label>
                            <select id="payment-method" wire:model.live="paymentMethod" class="sniper-input">
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="card">Card</option>
                                <option value="other">Other</option>
                            </select>
                            <x-input-error :messages="$errors->get('paymentMethod')" class="mt-2" />
                        </div>

                        @if ($paymentMethod === 'cash')
                            <div class="mt-3">
                                <label for="cash-received" class="sniper-label">Cash Received</label>
                                <input id="cash-received" wire:model.live.debounce.200ms="cashReceived" type="number" step="0.01" min="0" class="sniper-input text-right text-lg font-semibold" placeholder="0.00" />
                                <x-input-error :messages="$errors->get('cashReceived')" class="mt-2" />
                            </div>

                            <div class="mt-3 flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
                                <span class="text-sm text-sniper-slate">Change</span>
                                <span class="font-heading text-lg font-bold text-sniper-navy">₱{{ number_format($changeDue, 2) }}</span>
                            </div>
                        @else
                            <div class="mt-3">
                                <label for="payment-reference" class="sniper-label">Reference / Transaction No.</label>
                                <input id="payment-reference" wire:model="paymentReference" type="text" maxlength="100" class="sniper-input" placeholder="Enter payment reference" autocomplete="off" />
                                <x-input-error :messages="$errors->get('paymentReference')" class="mt-2" />
                            </div>

                            <div class="mt-3 flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
                                <span class="text-sm text-sniper-slate">Amount to collect</span>
                                <span class="font-heading text-lg font-bold text-sniper-navy">₱{{ number_format($total, 2) }}</span>
                            </div>
                        @endif
                    </div>

                    <button type="button" wire:click="completeSale" wire:loading.attr="disabled" class="sniper-btn-primary mt-5 w-full gap-2 py-3.5 font-heading uppercase tracking-[0.08em]">
                        <span>Complete Sale</span>
                        <span aria-hidden="true">→</span>
                    </button>
                </div>
            </div>
        </section>
    </div>
</div>
