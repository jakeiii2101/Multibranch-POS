<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0F2747">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="SniperPOS">

    <title>{{ config('app.name', 'SniperPOS') }}</title>

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.png" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=montserrat:600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-900 antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[1.05fr_.95fr]">
        <section class="relative hidden overflow-hidden bg-sniper-navy px-12 py-12 text-white lg:flex lg:items-center lg:justify-center">
            <div class="absolute -left-24 -top-24 h-72 w-72 rounded-full border-[42px] border-white/[0.03]"></div>
            <div class="absolute -bottom-32 -right-24 h-80 w-80 rounded-full border-[48px] border-sniper-red/[0.10]"></div>
            <div class="absolute left-12 top-12"><x-sniper-brand dark /></div>

            <div class="relative max-w-xl">
                <div class="inline-flex rounded-full bg-white/[0.07] px-4 py-2 text-[10px] font-bold uppercase tracking-[0.22em] text-slate-200 ring-1 ring-white/10">Secure business access</div>
                <h1 class="mt-7 font-heading text-5xl font-extrabold leading-[1.02] tracking-[-0.05em] text-white">Precision starts<br><span class="text-sniper-red">at sign in.</span></h1>
                <p class="mt-5 max-w-lg text-base leading-7 text-slate-300">Access your sales, inventory, products, users, and reports through one focused SniperPOS workspace.</p>

                <div class="mt-9 grid grid-cols-3 gap-3">
                    @foreach ([['Fast','Checkout'],['Clear','Inventory'],['Reliable','Reports']] as [$top,$bottom])
                        <div class="rounded-2xl bg-white/[0.06] p-4 ring-1 ring-white/10">
                            <div class="font-heading text-lg font-bold text-white">{{ $top }}</div>
                            <div class="mt-1 text-xs text-slate-400">{{ $bottom }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:min-h-0 lg:px-10">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center lg:hidden">
                    <x-sniper-brand class="justify-center" />
                </div>

                <div class="sniper-card px-6 py-7 sm:px-8 sm:py-8">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs leading-5 text-sniper-slate">SniperPOS · Precision in Every Sale.</p>
            </div>
        </section>
    </div>
</body>
</html>
