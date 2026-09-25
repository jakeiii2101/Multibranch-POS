<div class="sniper-page">
    <div class="sniper-page-header">
        <div>
            <div class="sniper-kicker">Access Control</div>
            <h1 class="sniper-title mt-1">Users</h1>
            <p class="sniper-subtitle">Manage administrator, manager, supervisor, and cashier accounts. Assign their branches on the Branches page.</p>
        </div>
        <button type="button" wire:click="create" class="sniper-btn-primary">+ Add User</button>
    </div>

    @if (session('success')) <div class="sniper-alert-success mb-5">{{ session('success') }}</div> @endif

    @if ($showForm)
        <form wire:submit="save" class="sniper-form-panel mb-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="font-heading text-lg font-bold text-sniper-navy">{{ $editingId ? 'Edit User' : 'Add User' }}</h2><p class="mt-1 text-sm text-sniper-slate">Assign only the access level needed for the person’s role.</p></div><span class="sniper-badge-navy">Account setup</span></div>
            <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <div><x-input-label for="user-name" value="Name" /><x-text-input id="user-name" wire:model="name" type="text" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                <div><x-input-label for="user-email" value="Email" /><x-text-input id="user-email" wire:model="email" type="email" class="mt-1.5 block w-full" /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
                <div><x-input-label for="user-role" value="Role" /><select id="user-role" wire:model="role" class="mt-1.5 block w-full"><option value="cashier">Cashier</option><option value="supervisor">Supervisor</option><option value="manager">Manager</option><option value="admin">Admin</option></select><x-input-error :messages="$errors->get('role')" class="mt-2" /></div>
                <div><x-input-label for="user-status" value="Status" /><select id="user-status" wire:model="status" class="mt-1.5 block w-full"><option value="active">Active</option><option value="inactive">Inactive</option></select><x-input-error :messages="$errors->get('status')" class="mt-2" /></div>
                <div><x-input-label for="user-password" :value="$editingId ? 'New Password (optional)' : 'Password'" /><x-text-input id="user-password" wire:model="password" type="password" class="mt-1.5 block w-full" autocomplete="new-password" /><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
                <div><x-input-label for="user-password-confirmation" value="Confirm Password" /><x-text-input id="user-password-confirmation" wire:model="passwordConfirmation" type="password" class="mt-1.5 block w-full" autocomplete="new-password" /><x-input-error :messages="$errors->get('passwordConfirmation')" class="mt-2" /></div>
            </div>
            @if ($editingId)<div class="sniper-alert-info mt-5">Leave password fields blank to keep the current password.</div>@endif
            <div class="mt-6 flex flex-wrap justify-end gap-3"><x-secondary-button type="button" wire:click="cancel">Cancel</x-secondary-button><x-primary-button type="submit">{{ $editingId ? 'Update User' : 'Save User' }}</x-primary-button></div>
        </form>
    @endif

    <div class="sniper-table-wrap">
        <div class="sniper-section-header"><h2 class="font-heading text-base font-bold text-sniper-navy">User Directory</h2><p class="mt-1 text-xs text-sniper-slate">Inactive accounts cannot access protected SniperPOS workflows.</p></div>
        <table class="sniper-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th class="!text-right">Action</th></tr></thead>
            <tbody>
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="font-semibold !text-sniper-navy">{{ $user->name }} @if ($user->id === auth()->id())<span class="ml-1 text-xs font-medium text-sniper-slate">(You)</span>@endif</td>
                        <td>{{ $user->email }}</td>
                        <td><span class="{{ $user->role === 'admin' ? 'sniper-badge-navy' : 'sniper-badge-info' }}">{{ ucfirst($user->role) }}</span></td>
                        <td><span class="{{ $user->status === 'active' ? 'sniper-badge-success' : 'sniper-badge-neutral' }}">{{ ucfirst($user->status) }}</span></td>
                        <td class="!text-right"><button type="button" wire:click="edit({{ $user->id }})" class="sniper-action-link">Edit</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="sniper-empty">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
