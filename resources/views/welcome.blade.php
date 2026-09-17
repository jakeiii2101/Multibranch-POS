<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0F2747">

    <title>SniperPOS — Precision in Every Sale.</title>
    <meta name="description" content="SniperPOS is a fast, reliable point-of-sale system for sales, inventory, reporting, and business growth.">

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.png" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=montserrat:600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --sniper-navy: #0F2747;
            --sniper-navy-deep: #08111F;
            --sniper-red: #E50914;
            --sniper-slate: #64748B;
            --sniper-light: #F1F5F9;
            --sniper-border: #E2E8F0;
        }

        html { scroll-behavior: smooth; }
        .landing-shell [id] { scroll-margin-top: var(--landing-header-height, 150px); }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
        }
        body { font-family: 'Inter', sans-serif; color: var(--sniper-navy); }
        .font-display { font-family: 'Montserrat', sans-serif; }
        .landing-shell { background: #fff; overflow-x: clip; }
        .landing-header { position: sticky; top: 0; }
        .landing-header a[aria-current="location"] { color: var(--sniper-red); font-weight: 700; }
        .hero-grid {
            background-image:
                radial-gradient(circle at 62% 36%, rgba(229, 9, 20, .085), transparent 28%),
                radial-gradient(circle at 78% 58%, rgba(15, 39, 71, .07), transparent 33%);
        }
        .dot-field {
            background-image: radial-gradient(rgba(229, 9, 20, .62) 1.8px, transparent 1.8px);
            background-size: 14px 14px;
        }
        .glass-card {
            background: rgba(255,255,255,.94);
            border: 1px solid rgba(226,232,240,.92);
            box-shadow: 0 24px 70px rgba(15,39,71,.13);
            backdrop-filter: blur(10px);
        }
        .device-shadow { box-shadow: 0 28px 60px rgba(8,17,31,.24); }
        .red-shadow { box-shadow: 0 14px 32px rgba(229,9,20,.23); }
        .nav-link { position: relative; }
        .nav-link::after {
            content: '';
            position: absolute;
            left: 0;
            right: 100%;
            bottom: -10px;
            height: 2px;
            background: var(--sniper-red);
            transition: right .2s ease;
        }
        .nav-link:hover::after, .nav-link[aria-current="location"]::after { right: 0; }
        .mini-icon svg { width: 24px; height: 24px; }
    </style>
</head>
<body class="landing-shell antialiased">
    <header class="landing-header z-40 bg-white/95 backdrop-blur border-b border-slate-100">
        <div class="mx-auto flex max-w-[1540px] items-center justify-between px-5 py-4 sm:px-8 lg:px-12">
            <a href="#home" class="flex items-center gap-3" aria-label="SniperPOS home">
                <img src="/icons/icon-192.png" alt="SniperPOS" class="h-12 w-12 rounded-xl object-contain sm:h-14 sm:w-14">
                <div class="leading-none">
                    <div class="font-display text-[24px] font-extrabold tracking-[-0.04em] sm:text-[31px]">
                        <span class="text-[#0F2747]">Sniper</span><span class="text-[#E50914]">POS</span>
                    </div>
                    <div class="mt-1 text-[8px] font-semibold uppercase tracking-[0.30em] text-slate-500 sm:text-[9px]">Precision in Every Sale.</div>
                </div>
            </a>

            <nav class="hidden items-center gap-9 lg:flex">
                <a href="#home" aria-current="location" class="nav-link text-sm font-medium text-[#0F2747]">Home</a>
                <a href="#features" class="nav-link text-sm font-medium text-[#0F2747]">Features</a>
                <a href="#solutions" class="nav-link text-sm font-medium text-[#0F2747]">Solutions</a>
                <a href="#pricing" class="nav-link text-sm font-medium text-[#0F2747]">Pricing</a>
                <a href="#resources" class="nav-link text-sm font-medium text-[#0F2747]">Resources</a>
            </nav>

            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-[#0F2747] transition hover:border-[#0F2747] sm:inline-flex">Dashboard</a>
                    <a href="{{ route('pos') }}" class="red-shadow rounded-xl bg-[#E50914] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#c90812] sm:px-7">Open POS</a>
                @else
                    <a href="{{ route('login') }}" class="hidden px-4 py-3 text-sm font-semibold text-[#0F2747] transition hover:text-[#E50914] sm:inline-flex">Log In</a>
                    <a href="{{ route('login') }}" class="red-shadow rounded-xl bg-[#E50914] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#c90812] sm:px-7">Get Started</a>
                @endauth
            </div>
        </div>

        <details class="border-t border-slate-100 px-5 py-3 lg:hidden">
            <summary class="cursor-pointer list-none text-sm font-semibold text-[#0F2747]">Browse site</summary>
            <div class="mt-3 grid grid-cols-2 gap-2 pb-1 text-sm">
                <a href="#home" aria-current="location" class="rounded-lg bg-slate-50 px-3 py-2">Home</a>
                <a href="#features" class="rounded-lg bg-slate-50 px-3 py-2">Features</a>
                <a href="#solutions" class="rounded-lg bg-slate-50 px-3 py-2">Solutions</a>
                <a href="#pricing" class="rounded-lg bg-slate-50 px-3 py-2">Pricing</a>
                <a href="#resources" class="rounded-lg bg-slate-50 px-3 py-2">Resources</a>
            </div>
        </details>
    </header>

    <main>
        <section id="home" class="hero-grid relative min-h-[calc(100vh-80px)] border-b border-slate-100">
            <div class="pointer-events-none absolute right-[38%] top-28 hidden h-36 w-36 opacity-50 dot-field lg:block"></div>
            <div class="mx-auto grid max-w-[1540px] items-center gap-12 px-5 py-14 sm:px-8 lg:grid-cols-[0.92fr_1.25fr] lg:px-12 lg:py-20 xl:gap-16">
                <div class="relative z-10">
                    <div class="inline-flex rounded-full bg-slate-100 px-5 py-2 text-[11px] font-bold uppercase tracking-[0.30em] text-[#0F2747]">All-in-one POS solution</div>

                    <h1 class="font-display mt-7 max-w-[660px] text-[52px] font-extrabold leading-[0.96] tracking-[-0.055em] text-[#0F2747] sm:text-[68px] lg:text-[76px] xl:text-[88px]">
                        Precision in<br><span class="text-[#E50914]">Every Sale.</span>
                    </h1>

                    <p class="mt-7 max-w-[650px] text-lg leading-8 text-[#64748B] sm:text-xl">
                        Manage your sales, inventory, products, users, and reports in one powerful and easy-to-use POS system.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        @auth
                            <a href="{{ route('pos') }}" class="red-shadow inline-flex items-center justify-center gap-3 rounded-xl bg-[#E50914] px-8 py-4 font-display text-base font-bold text-white transition hover:-translate-y-0.5 hover:bg-[#c90812]">
                                Open POS
                                <span aria-hidden="true">→</span>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="red-shadow inline-flex items-center justify-center gap-3 rounded-xl bg-[#E50914] px-8 py-4 font-display text-base font-bold text-white transition hover:-translate-y-0.5 hover:bg-[#c90812]">
                                Get Started
                                <span aria-hidden="true">→</span>
                            </a>
                        @endauth
                        <a href="#preview" class="inline-flex items-center justify-center gap-3 rounded-xl border-2 border-[#0F2747] bg-white px-8 py-4 font-display text-base font-bold text-[#0F2747] transition hover:bg-slate-50">
                            View Demo <span class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-[#0F2747] text-xs">▶</span>
                        </a>
                    </div>

                    <div id="features" class="mt-12 grid max-w-[650px] grid-cols-2 gap-x-6 gap-y-6 sm:grid-cols-4">
                        @php
                            $heroFeatures = [
                                ['Point of Sale', 'Fast & Reliable', 'cart'],
                                ['Inventory', 'Always in Control', 'box'],
                                ['Business Ready', 'Built to Scale', 'store'],
                                ['Reports', 'Insights that Matter', 'chart'],
                            ];
                        @endphp
                        @foreach ($heroFeatures as [$title, $subtitle, $icon])
                            <div>
                                <div class="mini-icon flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 text-[#0F2747] ring-1 ring-slate-100">
                                    @if ($icon === 'cart')
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2m0 0H21l-2 8H7L5.4 5ZM8 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm10 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/></svg>
                                    @elseif ($icon === 'box')
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 8 4-8 4-8-4 8-4Zm-8 4v10l8 4 8-4V7m-8 4v10"/></svg>
                                    @elseif ($icon === 'store')
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10h16M5 10v9h14v-9M3 10l2-5h14l2 5M9 19v-5h6v5"/></svg>
                                    @else
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 20V10m7 10V4m7 16v-7"/></svg>
                                    @endif
                                </div>
                                <div class="font-display mt-3 text-sm font-bold text-[#0F2747]">{{ $title }}</div>
                                <div class="mt-1 text-xs text-[#64748B]">{{ $subtitle }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div id="preview" class="relative min-h-[600px] lg:min-h-[680px]">
                    <div class="absolute inset-x-8 top-12 h-[510px] rounded-[34px] bg-[rgba(229,9,20,.05)] blur-[1px]"></div>

                    <div class="device-shadow absolute right-0 top-10 w-full max-w-[820px] rotate-[2.7deg] overflow-hidden rounded-[22px] border-[9px] border-slate-800 bg-slate-50 sm:right-2 lg:w-[94%]">
                        <div class="grid min-h-[500px] grid-cols-[145px_1fr] sm:grid-cols-[175px_1fr]">
                            <aside class="bg-[#0F2747] p-4 text-white sm:p-5">
                                <div class="flex items-center gap-2 border-b border-white/10 pb-5">
                                    <img src="/icons/icon-192.png" class="h-8 w-8 rounded-lg" alt="">
                                    <div class="font-display text-sm font-bold">Sniper<span class="text-[#E50914]">POS</span></div>
                                </div>
                                <div class="mt-5 space-y-2 text-[11px] sm:text-xs">
                                    <div class="rounded-lg bg-[#E50914] px-3 py-3 font-semibold">⌂ &nbsp; Dashboard</div>
                                    <div class="rounded-lg px-3 py-2.5 text-slate-300">🛒 &nbsp; POS</div>
                                    <div class="rounded-lg px-3 py-2.5 text-slate-300">□ &nbsp; Products</div>
                                    <div class="rounded-lg px-3 py-2.5 text-slate-300">◇ &nbsp; Inventory</div>
                                    <div class="rounded-lg px-3 py-2.5 text-slate-300">▤ &nbsp; Sales</div>
                                    <div class="rounded-lg px-3 py-2.5 text-slate-300">▥ &nbsp; Reports</div>
                                </div>
                            </aside>
                            <div class="bg-[#F8FAFC] p-4 sm:p-6">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="font-display text-lg font-bold text-[#0F2747] sm:text-2xl">Good morning,</div>
                                        <div class="mt-1 text-xs text-[#64748B] sm:text-sm">Let’s make it happen today!</div>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-slate-400"><span>Today</span><span class="rounded-full bg-[#0F2747] px-2.5 py-2 text-white">JD</span></div>
                                </div>

                                <div class="mt-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
                                    @foreach ([['Total Sales','₱12,480.00'],['Items Sold','1,245'],['Low Stock','4'],['Products','892']] as [$label,$value])
                                        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                                            <div class="mb-3 flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-[#E50914]">◆</div>
                                            <div class="text-[10px] text-slate-500 sm:text-xs">{{ $label }}</div>
                                            <div class="font-display mt-1 text-base font-bold text-[#0F2747] sm:text-lg">{{ $value }}</div>
                                            <div class="mt-2 text-[9px] font-semibold text-emerald-500">↑ 8% vs last week</div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-4 grid gap-4 xl:grid-cols-[1.35fr_.85fr]">
                                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <div class="flex items-center justify-between"><div class="font-display text-sm font-bold">Sales Overview</div><div class="rounded-lg border px-2 py-1 text-[9px] text-slate-500">This Week⌄</div></div>
                                        <div class="mt-5 flex h-36 items-end gap-2 border-b border-l border-slate-200 px-2 pb-2">
                                            @foreach ([28,48,39,58,44,55,78] as $height)
                                                <div class="flex-1 rounded-t-md bg-gradient-to-t from-red-100 to-[#E50914]" style="height: {{ $height }}%"></div>
                                            @endforeach
                                        </div>
                                        <div class="mt-2 grid grid-cols-7 text-center text-[8px] text-slate-400"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <div class="font-display text-sm font-bold">Top Products</div>
                                        <div class="mt-4 space-y-3 text-[10px] sm:text-xs">
                                            @foreach ([['Classic T-Shirt','432 sold'],['Wireless Mouse','318 sold'],['Ceramic Mug','274 sold'],['Laptop Stand','251 sold']] as [$name,$sold])
                                                <div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2"><span class="flex h-7 w-7 items-center justify-center rounded-md bg-slate-100">●</span><span>{{ $name }}</span></div><span class="text-slate-400">{{ $sold }}</span></div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="device-shadow absolute bottom-0 right-[4%] w-[72%] max-w-[680px] -rotate-[1.3deg] rounded-[30px] border-[10px] border-[#172437] bg-[#F8FAFC] p-3 sm:p-4 lg:right-[8%]">
                        <div class="grid gap-3 rounded-[16px] bg-white p-3 sm:grid-cols-[1.05fr_.95fr] sm:p-4">
                            <div>
                                <div class="flex gap-2 text-[9px]"><span class="rounded-md bg-[#E50914] px-5 py-2 font-bold text-white">Sale</span><span class="rounded-md bg-slate-100 px-5 py-2">Returns</span><span class="rounded-md bg-slate-100 px-5 py-2">Orders</span></div>
                                <div class="mt-3 rounded-lg border border-slate-200 px-3 py-2 text-[9px] text-slate-400">⌕ Search products...</div>
                                <div class="mt-3 grid grid-cols-3 gap-2">
                                    @foreach ([['T-Shirt','₱15.00'],['Mug','₱8.00'],['Mouse','₱25.00'],['Cap','₱12.00'],['Bottle','₱18.00'],['Earbuds','₱35.00']] as [$name,$price])
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-center"><div class="mx-auto mb-1 h-8 w-10 rounded bg-slate-200"></div><div class="text-[8px]">{{ $name }}</div><div class="text-[8px] font-bold">{{ $price }}</div></div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-3">
                                <div class="space-y-3 text-[9px]">
                                    <div class="flex justify-between"><span>Classic T-Shirt × 1</span><strong>₱15.00</strong></div>
                                    <div class="flex justify-between"><span>Ceramic Mug × 2</span><strong>₱16.00</strong></div>
                                </div>
                                <div class="mt-6 border-t pt-3 text-[9px]"><div class="flex justify-between text-slate-500"><span>Subtotal</span><span>₱31.00</span></div><div class="mt-1 flex justify-between text-slate-500"><span>Tax</span><span>₱2.48</span></div><div class="font-display mt-2 flex justify-between text-sm font-bold"><span>Total</span><span>₱33.48</span></div></div>
                                <div class="mt-3 rounded-lg bg-[#E50914] py-3 text-center text-[10px] font-bold text-white">Complete Sale →</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mx-auto grid max-w-[1540px] grid-cols-1 gap-5 border-t border-slate-100 px-5 py-8 sm:grid-cols-3 sm:px-8 lg:px-12">
                <div class="flex items-center gap-4"><div class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-50 text-xl">✓</div><div><div class="text-xs text-slate-500">Trusted by</div><div class="font-display text-lg font-bold">Growing Businesses</div></div></div>
                <div class="flex items-center gap-4"><div class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-50 text-xl">◎</div><div><div class="text-xs text-slate-500">Built across</div><div class="font-display text-lg font-bold">Multiple Industries</div></div></div>
                <div class="flex items-center gap-4"><div class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-50 text-xl">★</div><div><div class="text-xs text-slate-500">Designed for</div><div class="font-display text-lg font-bold">Growth & Control</div></div></div>
            </div>
        </section>

        <section id="solutions" class="bg-[#F8FAFC] py-20">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="text-xs font-bold uppercase tracking-[.28em] text-[#E50914]">Built for daily operations</span>
                    <h2 class="font-display mt-4 text-3xl font-extrabold tracking-tight text-[#0F2747] sm:text-4xl">Everything you need to sell smarter.</h2>
                    <p class="mt-4 text-[#64748B]">SniperPOS keeps your cashier workflow fast while giving administrators clear control over products, stock, users, sales history, and reporting.</p>
                </div>
                <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ([['Fast Checkout','Barcode, SKU, cart, cash payment, change, and receipt.'],['Inventory Control','Stock in, stock out, adjustment, movement history, and low-stock visibility.'],['Secure Access','Admin and cashier roles with server-side authorization and active-account enforcement.'],['Clear Reporting','Daily sales, monthly sales, top products, inventory value, and sales history.']] as [$title,$copy])
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_4px_16px_rgba(15,39,71,.06)]"><div class="mb-4 h-2 w-10 rounded-full bg-[#E50914]"></div><h3 class="font-display text-lg font-bold">{{ $title }}</h3><p class="mt-3 text-sm leading-6 text-[#64748B]">{{ $copy }}</p></div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="pricing" class="bg-white py-20">
            <div class="mx-auto max-w-5xl px-5 text-center sm:px-8">
                <span class="text-xs font-bold uppercase tracking-[.28em] text-[#E50914]">Simple by design</span>
                <h2 class="font-display mt-4 text-3xl font-extrabold text-[#0F2747] sm:text-4xl">Start with the core. Scale when you’re ready.</h2>
                <p class="mx-auto mt-4 max-w-2xl text-[#64748B]">SniperPOS V1 focuses on the essentials of reliable selling and inventory management without unnecessary complexity.</p>
                <div class="mx-auto mt-10 max-w-xl rounded-3xl border-2 border-[#0F2747] bg-white p-8 text-left shadow-[0_18px_50px_rgba(15,39,71,.10)]">
                    <div class="flex items-start justify-between gap-5"><div><div class="font-display text-2xl font-extrabold">SniperPOS V1</div><div class="mt-2 text-sm text-[#64748B]">Core point-of-sale platform</div></div><span class="rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-[#E50914]">Current Build</span></div>
                    <div class="mt-6 grid grid-cols-2 gap-3 text-sm text-[#0F2747]"><span>✓ POS Checkout</span><span>✓ Inventory</span><span>✓ Products</span><span>✓ Reports</span><span>✓ Sales History</span><span>✓ User Roles</span></div>
                    @auth
                        <a href="{{ route('dashboard') }}" class="mt-8 block rounded-xl bg-[#E50914] px-6 py-4 text-center font-display font-bold text-white">Go to Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="mt-8 block rounded-xl bg-[#E50914] px-6 py-4 text-center font-display font-bold text-white">Log In to SniperPOS</a>
                    @endauth
                </div>
            </div>
        </section>

        <section id="resources" class="bg-[#0F2747] py-16 text-white">
            <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-8 px-5 sm:px-8 lg:flex-row lg:items-center">
                <div>
                    <div class="font-display text-3xl font-extrabold">From products to profits.</div>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-slate-300">A focused POS experience designed for speed, accuracy, and clear business control.</p>
                </div>
                @auth
                    <a href="{{ route('pos') }}" class="rounded-xl bg-[#E50914] px-7 py-4 font-display font-bold text-white">Open POS →</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-xl bg-[#E50914] px-7 py-4 font-display font-bold text-white">Get Started →</a>
                @endauth
            </div>
        </section>
    </main>

    <footer class="bg-[#08111F] py-7 text-sm text-slate-400">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-5 sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <div><span class="font-display font-bold text-white">Sniper<span class="text-[#E50914]">POS</span></span> — Precision in Every Sale.</div>
            <div>© {{ date('Y') }} SniperPOS. All rights reserved.</div>
        </div>
    </footer>
</body>
</html>
