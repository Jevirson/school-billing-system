<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ trim($__env->yieldContent('title', 'Billing System')) }} · {{ config('app.name', 'Billing System') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="min-h-screen bg-slate-50 font-sans antialiased text-slate-900">
        <div class="min-h-screen" x-data="{ navigationOpen: false }">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex min-h-20 flex-wrap items-center justify-between gap-x-8 gap-y-3 py-4">
                        <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-3" aria-label="Billing System home">
                            <img src="{{ asset('images/school-logo.jfif') }}" alt="" class="h-11 w-11 rounded-xl border border-slate-200 bg-white object-contain p-0.5 shadow-sm">
                            <span>
                                <span class="block text-lg font-bold leading-tight tracking-tight text-slate-900">Billing System</span>
                                <span class="mt-0.5 block text-[10px] font-semibold tracking-[0.16em] text-slate-500">SCHOOL BILLING</span>
                            </span>
                        </a>

                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 lg:hidden"
                            :aria-expanded="navigationOpen"
                            :aria-label="navigationOpen ? 'Close navigation' : 'Open navigation'"
                            @click="navigationOpen = !navigationOpen"
                        >
                            <svg x-show="!navigationOpen" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linecap="round" d="M3 5h14M3 10h14M3 15h14"/>
                            </svg>
                            <svg x-cloak x-show="navigationOpen" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linecap="round" d="m5 5 10 10M15 5 5 15"/>
                            </svg>
                            <span x-text="navigationOpen ? 'Close' : 'Menu'">Menu</span>
                        </button>

                        <div class="order-2 ml-auto flex items-center gap-3 sm:order-3">
                            <div class="hidden text-right sm:block">
                                <p class="text-sm font-semibold text-slate-800">{{ Auth::user()->name }}</p>
                                <p class="text-xs capitalize text-slate-500">{{ Auth::user()->role }}</p>
                            </div>
                            <div class="relative" x-data="{ open: false }" @click.away="open = false" @keydown.escape.window="open = false">
                                <button
                                    type="button"
                                    class="flex items-center gap-2 rounded-full p-1 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    aria-label="Open account menu"
                                    aria-haspopup="true"
                                    :aria-expanded="open"
                                    @click="open = !open"
                                >
                                    @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                                        <img src="{{ Auth::user()->profile_photo_url }}" alt="" class="h-10 w-10 rounded-full border border-slate-200 object-cover">
                                    @else
                                        <span class="grid h-10 w-10 place-items-center rounded-full bg-indigo-100 font-semibold text-indigo-700">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
                                    @endif
                                    <svg class="h-4 w-4 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
                                    </svg>
                                </button>

                                <div
                                    x-cloak
                                    x-show="open"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="transform opacity-0 scale-95"
                                    x-transition:enter-end="transform opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-100"
                                    x-transition:leave-start="transform opacity-100 scale-100"
                                    x-transition:leave-end="transform opacity-0 scale-95"
                                    class="absolute right-0 z-50 mt-3 w-72 origin-top-right rounded-2xl border border-slate-200 bg-white p-2 shadow-xl ring-1 ring-black/5"
                                    role="menu"
                                    aria-label="Account menu"
                                >
                                    <div class="flex items-center gap-3 rounded-xl p-3">
                                        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                                            <img src="{{ Auth::user()->profile_photo_url }}" alt="" class="h-12 w-12 shrink-0 rounded-full border border-slate-200 object-cover">
                                        @else
                                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-indigo-100 text-lg font-semibold text-indigo-700">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ Auth::user()->name }}</p>
                                            <p class="mt-0.5 text-xs capitalize text-slate-500">{{ Auth::user()->role }}</p>
                                        </div>
                                    </div>

                                    <div class="my-1 border-t border-slate-100"></div>

                                    <a href="{{ route('profile.show') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:bg-slate-50 focus:outline-none" role="menuitem" @click="open = false">
                                        <span class="grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-slate-600">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17.9 17.9 0 0 1 12 21.75c-2.67 0-5.2-.58-7.5-1.65Z"/>
                                            </svg>
                                        </span>
                                        <span>
                                            <span class="block font-semibold">Profile</span>
                                            <span class="block text-xs text-slate-500">Edit your account information</span>
                                        </span>
                                    </a>

                                    <div class="my-1 border-t border-slate-100"></div>

                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-slate-700 transition hover:bg-red-50 hover:text-red-700 focus:bg-red-50 focus:outline-none" role="menuitem">
                                            <span class="grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-slate-600 group-hover:bg-red-100 group-hover:text-red-700">
                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 8.25V5.5A1.75 1.75 0 0 0 14 3.75H6.5A1.75 1.75 0 0 0 4.75 5.5v13A1.75 1.75 0 0 0 6.5 20.25H14a1.75 1.75 0 0 0 1.75-1.75v-2.75M10 12h9.25m0 0-3.5-3.5m3.5 3.5-3.5 3.5"/>
                                                </svg>
                                            </span>
                                            <span>Log out</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="mx-auto grid max-w-screen-2xl grid-cols-1 lg:grid-cols-[15rem_minmax(0,1fr)]">
                <aside id="side-navigation" :class="navigationOpen ? 'block' : 'hidden'" class="border-b border-slate-200 bg-white lg:block lg:border-b-0 lg:border-r">
                    <nav aria-label="Main navigation" @click="navigationOpen = false" class="space-y-1 px-3 py-4 sm:px-4 lg:sticky lg:top-0 lg:min-h-[calc(100vh-5rem)] lg:py-5">
                        <a href="{{ route('dashboard') }}" @class(['flex rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('dashboard', 'admin.dashboard', 'student.dashboard'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('dashboard', 'admin.dashboard', 'student.dashboard')]) aria-current="{{ request()->routeIs('dashboard', 'admin.dashboard', 'student.dashboard') ? 'page' : 'false' }}">Overview</a>
                        @if (Auth::user()->isAdmin())
                            <a href="{{ route('admin.students.index') }}" @class(['flex rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('admin.students.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('admin.students.*')])>Students</a>
                            <a href="{{ route('admin.enrollments.index') }}" @class(['flex rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('admin.enrollments.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('admin.enrollments.*')])>Enrollment</a>
                            <a href="{{ route('admin.bills.index') }}" @class(['flex rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('admin.bills.*', 'admin.payments.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('admin.bills.*', 'admin.payments.*')])>Bills &amp; payments</a>
                            <a href="{{ route('admin.payment-requests.index') }}" @class(['flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('admin.payment-requests.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('admin.payment-requests.*')])><span>Payment requests</span>@if (($pendingPaymentRequestCount ?? 0) > 0)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">{{ $pendingPaymentRequestCount }}</span>@endif</a>
                            <a href="{{ route('admin.subject-withdrawals.index') }}" @class(['flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('admin.subject-withdrawals.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('admin.subject-withdrawals.*')])><span>Withdrawal requests</span>@if (($pendingSubjectWithdrawalCount ?? 0) > 0)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">{{ $pendingSubjectWithdrawalCount }}</span>@endif</a>
                            <a href="{{ route('admin.promissory-letters.index') }}" @class(['flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('admin.promissory-letters.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('admin.promissory-letters.*')])><span>Promissory letters</span>@if (($pendingPromissoryCount ?? 0) > 0)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">{{ $pendingPromissoryCount }}</span>@endif</a>
                            <a href="{{ route('admin.catalog.index') }}" @class(['flex rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('admin.catalog.*', 'admin.courses.*', 'admin.terms.*', 'admin.fees.*', 'admin.scholarships.*', 'admin.subjects.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('admin.catalog.*', 'admin.courses.*', 'admin.terms.*', 'admin.fees.*', 'admin.scholarships.*', 'admin.subjects.*')])>Billing setup</a>
                            <a href="{{ route('admin.users.index') }}" @class(['flex rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('admin.users.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('admin.users.*')])>Users</a>
                        @else
                            <a href="{{ route('student.enrollment.index') }}" @class(['flex rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('student.enrollment.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('student.enrollment.*')])>My subjects</a>
                            <a href="{{ route('student.bills.index') }}" @class(['flex rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('student.bills.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('student.bills.*')])>My bills</a>
                            <a href="{{ route('student.payments.index') }}" @class(['flex rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('student.payments.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('student.payments.*')])>Payment history</a>
                            <a href="{{ route('student.promissory-letters.index') }}" @class(['flex rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('student.promissory-letters.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('student.promissory-letters.*')])>Promissory letters</a>
                        @endif
                        <a href="{{ route('notifications.index') }}" @class(['flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition', 'bg-indigo-50 text-indigo-700' => request()->routeIs('notifications.*'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('notifications.*')])><span>Notifications</span>@if (($unreadNotificationCount ?? 0) > 0)<span class="rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-bold text-white">{{ $unreadNotificationCount }}</span>@endif</a>
                    </nav>
                </aside>
                <div class="min-w-0">
            @if (isset($header))
                <div class="border-b border-slate-200 bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </div>
            @endif

            <main class="min-h-[calc(100vh-10rem)]">
                {{ $slot }}
            </main>

            <footer class="border-t border-slate-200 bg-white">
                <div class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-5 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                    <span class="font-semibold text-slate-600">{{ config('app.name', 'Billing System') }}</span>
                    <span>Secure student accounts and billing management</span>
                </div>
            </footer>
                </div>
            </div>
        </div>

        @stack('modals')

        @livewireScripts
    </body>
</html>
