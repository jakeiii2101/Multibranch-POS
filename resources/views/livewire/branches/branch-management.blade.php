<div class="sniper-page">
    <div class="sniper-page-header">
        <div>
            <div class="sniper-kicker">Access Control</div>
            <h1 class="sniper-title mt-1">Branches</h1>
            <p class="sniper-subtitle">Create branches and assign managers, supervisors, and cashiers. Sales and stock remain on the original POS workflow during this phase.</p>
        </div>
        <button type="button" wire:click="create" class="sniper-btn-primary">+ Add Branch</button>
    </div>

    @if (session('success')) <div class="sniper-alert-success mb-5">{{ session('success') }}</div> @endif

    @if ($showForm)
        <form wire:submit="save" class="sniper-form-panel mb-6">
            <h2 class="font-heading text-lg font-bold text-sniper-navy">{{ $editingId ? 'Edit Branch' : 'Add Branch' }}</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div><x-input-label for="branch-code" value="Internal Code" /><x-text-input id="branch-code" wire:model="code" type="text" class="mt-1.5 block w-full" maxlength="30" placeholder="BR-001" /><x-input-error :messages="$errors->get('code')" class="mt-2" /></div>
                <div><x-input-label for="branch-name" value="Branch Name" /><x-text-input id="branch-name" wire:model="name" type="text" class="mt-1.5 block w-full" maxlength="150" /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                <div><x-input-label for="branch-address" value="Address" /><x-text-input id="branch-address" wire:model="address" type="text" class="mt-1.5 block w-full" maxlength="255" /><x-input-error :messages="$errors->get('address')" class="mt-2" /></div>
                <div><x-input-label for="branch-status" value="Status" /><select id="branch-status" wire:model="status" class="mt-1.5 block w-full"><option value="active">Active</option><option value="inactive">Inactive</option></select><x-input-error :messages="$errors->get('status')" class="mt-2" /></div>
            </div>
            <div class="mt-6 flex justify-end gap-3"><x-secondary-button type="button" wire:click="cancel">Cancel</x-secondary-button><x-primary-button type="submit">Save Branch</x-primary-button></div>
        </form>
    @endif

    <form wire:submit="assign" class="sniper-form-panel mb-6">
        <h2 class="font-heading text-lg font-bold text-sniper-navy">Assign Staff</h2>
        <p class="mt-1 text-sm text-sniper-slate">Managers can oversee multiple branches. Each supervisor and cashier has one active branch.</p>
        <div class="mt-5 grid gap-5 md:grid-cols-2">
            <div><x-input-label for="assign-user" value="Staff Member" /><select id="assign-user" wire:model="userId" class="mt-1.5 block w-full"><option value="">Choose a user</option>@foreach ($staff as $person)<option value="{{ $person->id }}">{{ $person->name }} ({{ ucfirst($person->role) }})</option>@endforeach</select><x-input-error :messages="$errors->get('userId')" class="mt-2" /></div>
            <div><x-input-label for="assign-branch" value="Branch" /><select id="assign-branch" wire:model="branchId" class="mt-1.5 block w-full"><option value="">Choose a branch</option>@foreach ($branches->where('status', 'active') as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('branchId')" class="mt-2" /></div>
        </div>
        <div class="mt-5 flex justify-end"><x-primary-button type="submit">Assign to Branch</x-primary-button></div>
    </form>

    <div class="sniper-table-wrap">
        <div class="sniper-section-header"><h2 class="font-heading text-base font-bold text-sniper-navy">Branch Directory</h2></div>
        <table class="sniper-table">
            <thead><tr><th>Code</th><th>Name</th><th>Status</th><th>Assigned Staff</th><th class="!text-right">Action</th></tr></thead>
            <tbody>
                @forelse ($branches as $branch)
                    <tr wire:key="branch-{{ $branch->id }}">
                        <td>{{ $branch->code }}</td>
                        <td class="font-semibold !text-sniper-navy">{{ $branch->name }}</td>
                        <td><span class="{{ $branch->isActive() ? 'sniper-badge-success' : 'sniper-badge-neutral' }}">{{ ucfirst($branch->status) }}</span></td>
                        <td>
                            @forelse ($branch->users as $person)
                                <div wire:key="assignment-{{ $branch->id }}-{{ $person->id }}" class="mb-1 flex items-center gap-2">
                                    <span>{{ $person->name }} ({{ ucfirst($person->role) }})</span>
                                    <button type="button" wire:click="revoke({{ $person->id }}, {{ $branch->id }})" wire:confirm="Revoke this assignment?" class="sniper-action-danger">Revoke</button>
                                </div>
                            @empty
                                <span class="text-sniper-slate">No staff assigned</span>
                            @endforelse
                        </td>
                        <td class="!text-right"><button type="button" wire:click="edit({{ $branch->id }})" class="sniper-action-link">Edit</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="sniper-empty">No branches yet. Add the original branch first.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
