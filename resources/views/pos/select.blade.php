<x-app-layout>
    <div class="sniper-page mx-auto max-w-3xl">
        <h1 class="sniper-title">Choose a register</h1>
        <p class="sniper-copy mt-2">Select the branch where you are working today.</p>
        <div class="mt-6 grid gap-3">
            @if ($originalId === null && auth()->user()->isAdmin())
                <a href="{{ route('pos') }}" class="sniper-card p-5 font-semibold">Original register</a>
            @endif
            @foreach ($branches as $branch)
                <a href="{{ $branch->id === $originalId ? route('pos') : route('pos.branch', $branch) }}" class="sniper-card p-5 font-semibold">
                    {{ $branch->name }} <span class="text-sm text-slate-500">({{ $branch->code }})</span>
                </a>
            @endforeach
        </div>
    </div>
</x-app-layout>
