<div class="sniper-page">
    <div class="sniper-page-header">
        <div>
            <div class="sniper-kicker">Branch Operations</div>
            <h1 class="sniper-title mt-1">Branch Inventory</h1>
            <p class="sniper-subtitle">Record stock in or out for an additional branch. Original branch stock stays in the legacy Inventory screen.</p>
        </div>
    </div>

    @if (session('success')) <div class="sniper-alert-success mb-5">{{ session('success') }}</div> @endif

    @if (! $ready)
        <div class="sniper-form-panel mb-6">Activate and audit the original branch inventory before adding stock to another branch.</div>
    @else
        <form wire:submit="save" class="sniper-form-panel mb-6">
            <h2 class="font-heading text-lg font-bold text-sniper-navy">Record Movement</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div><x-input-label for="stock-branch" value="Branch" /><select id="stock-branch" wire:model.live="branchId" class="mt-1.5 block w-full"><option value="">Choose a branch</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>@endforeach</select><x-input-error :messages="$errors->get('branchId')" class="mt-2" /></div>
                <div><x-input-label for="stock-product" value="Product" /><select id="stock-product" wire:model="productId" class="mt-1.5 block w-full"><option value="">Choose a product</option>@foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select><x-input-error :messages="$errors->get('productId')" class="mt-2" /></div>
                <div><x-input-label for="stock-type" value="Movement" /><select id="stock-type" wire:model="type" class="mt-1.5 block w-full"><option value="stock_in">Stock In</option><option value="stock_out">Stock Out</option></select><x-input-error :messages="$errors->get('type')" class="mt-2" /></div>
                <div><x-input-label for="stock-quantity" value="Quantity" /><x-text-input id="stock-quantity" wire:model="quantity" type="number" min="1" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('quantity')" class="mt-2" /></div>
                <div class="md:col-span-2"><x-input-label for="stock-reason" value="Reason" /><x-text-input id="stock-reason" wire:model="reason" type="text" maxlength="255" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('reason')" class="mt-2" /></div>
            </div>
            <div class="mt-5 flex justify-end"><x-primary-button type="submit">Save Movement</x-primary-button></div>
        </form>
    @endif

    <div class="sniper-table-wrap">
        <div class="sniper-section-header"><h2 class="font-heading text-base font-bold text-sniper-navy">Selected Branch Stock</h2></div>
        <table class="sniper-table">
            <thead><tr><th>Product</th><th>SKU</th><th>On hand</th><th>Reorder level</th></tr></thead>
            <tbody>
                @forelse ($balances as $balance)
                    <tr wire:key="balance-{{ $balance->id }}"><td>{{ $balance->product->name }}</td><td>{{ $balance->product->sku }}</td><td>{{ $balance->on_hand }}</td><td>{{ $balance->reorder_level }}</td></tr>
                @empty
                    <tr><td colspan="4" class="sniper-empty">Select a branch to view its stock.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
