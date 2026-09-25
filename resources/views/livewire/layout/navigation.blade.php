<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<div x-data="{ open: false }">
    <div class="fixed inset-x-0 top-0 z-40 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 lg:hidden">
        <x-sniper-brand />
        <button @click="open = true" class="rounded-xl p-2 text-sniper-navy hover:bg-sniper-light focus:outline-none focus:ring-2 focus:ring-sniper-red/30" aria-label="Open navigation">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>
    </div>

    <div x-show="open" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/55 backdrop-blur-[2px] lg:hidden" @click="open = false" x-cloak></div>

    <aside :class="open ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-sniper-navy text-white shadow-2xl shadow-slate-950/20 transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-20 items-center justify-between border-b border-white/10 px-5">
            <x-sniper-brand dark />
            <button @click="open = false" class="rounded-lg p-2 text-slate-300 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Close navigation">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="px-5 pt-5 text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">Workspace</div>

        <nav class="mt-3 flex-1 space-y-1 overflow-y-auto px-3 pb-5">
            @php
                $items = [
                    ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
                    ['route' => 'pos', 'label' => 'POS', 'icon' => 'cart'],
                ];

                if (auth()->user()->isAdmin()) {
                    $items = array_merge($items, [
                        ['route' => 'branch-monitor', 'label' => 'Branch Monitor', 'icon' => 'chart'],
                        ['route' => 'branch-inventory', 'label' => 'Branch Inventory', 'icon' => 'inventory'],
                        ['route' => 'products', 'label' => 'Products', 'icon' => 'box'],
                        ['route' => 'inventory', 'label' => 'Inventory', 'icon' => 'inventory'],
                        ['route' => 'categories', 'label' => 'Categories', 'icon' => 'tag'],
                        ['route' => 'sales', 'label' => 'Sales', 'icon' => 'sales'],
                        ['route' => 'reports', 'label' => 'Reports', 'icon' => 'chart'],
                        ['route' => 'daily-readings', 'label' => 'Daily Readings', 'icon' => 'audit'],
                        ['route' => 'users', 'label' => 'Users', 'icon' => 'users'],
                        ['route' => 'branch-management', 'label' => 'Branches', 'icon' => 'users'],
                        ['route' => 'audit-logs', 'label' => 'Audit Log', 'icon' => 'audit'],
                        ['route' => 'settings.bir', 'label' => 'BIR Settings', 'icon' => 'settings'],
                        ['route' => 'settings.readiness', 'label' => 'System Readiness', 'icon' => 'audit'],
                    ]);
                } elseif (in_array(auth()->user()->role, [\App\Models\User::ROLE_MANAGER, \App\Models\User::ROLE_SUPERVISOR], true)) {
                    $items = [
                        ['route' => 'branch-monitor', 'label' => 'Branch Monitor', 'icon' => 'chart'],
                        ['route' => 'branch-inventory', 'label' => 'Branch Inventory', 'icon' => 'inventory'],
                    ];
                } elseif (auth()->user()->isCashier()) {
                    $items = [['route' => 'pos', 'label' => 'POS', 'icon' => 'cart']];
                }
            @endphp

            @foreach ($items as $item)
                @php($active = request()->routeIs($item['route']))
                <a href="{{ route($item['route'], [], false) }}" wire:navigate @click="open = false"
                   @class([
                        'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-all duration-200',
                        'bg-sniper-red text-white shadow-[0_8px_22px_rgba(229,9,20,.22)]' => $active,
                        'text-slate-300 hover:bg-white/[0.07] hover:text-white' => ! $active,
                   ])>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $active ? 'bg-white/15' : 'bg-white/[0.04] group-hover:bg-white/[0.08]' }}">
                        @switch($item['icon'])
                            @case('dashboard')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 13h6V4H4v9Zm10 7h6v-9h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z"/></svg>
                                @break
                            @case('cart')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l2.4 10.2a2 2 0 0 0 2 1.5h7.8a2 2 0 0 0 1.9-1.4L21 7H6m4 12a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm9 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"/></svg>
                                @break
                            @case('box')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 8 4-8 4-8-4 8-4Zm-8 4v10l8 4 8-4V7m-8 4v10"/></svg>
                                @break
                            @case('inventory')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v14H4V6Zm3-3h10v3H7V3Zm2 8h6"/></svg>
                                @break
                            @case('tag')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13 11 22l-9-9V4h9l9 9Z"/><path d="M7 8h.01"/></svg>
                                @break
                            @case('sales')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4h14v16H5V4Zm3 4h8M8 12h8M8 16h4"/></svg>
                                @break
                            @case('chart')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 20V10m7 10V4m7 16v-7"/></svg>
                                @break
                            @case('audit')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 19 6v5c0 4.6-2.8 8-7 10-4.2-2-7-5.4-7-10V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
                                @break
                            @default
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/></svg>
                        @endswitch
                    </span>
                    <span>{{ __($item['label']) }}</span>
                </a>
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-4">
            <div class="mb-3 flex items-center gap-3 rounded-xl bg-white/[0.06] p-3 ring-1 ring-white/[0.06]">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 font-heading text-xs font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-white" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                    <div class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <a href="{{ route('profile', [], false) }}" wire:navigate class="rounded-lg bg-white/[0.04] px-3 py-2 text-center text-xs font-medium text-slate-300 hover:bg-white/10 hover:text-white">Profile</a>
                <button wire:click="logout" class="rounded-lg bg-white/[0.04] px-3 py-2 text-xs font-medium text-slate-300 hover:bg-white/10 hover:text-white">Log Out</button>
            </div>
        </div>
    </aside>

    <div class="h-16 lg:hidden"></div>
</div>
