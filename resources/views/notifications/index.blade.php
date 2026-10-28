<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">ACCOUNT UPDATES</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Notifications</h1>
        </div>
    </x-slot>

    <div class="bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.8fr)]">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-bold text-slate-900">Payment and account updates</h2>
                    <p class="mt-1 text-sm text-slate-500">Payment confirmations, requests, and billing schedule changes.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($notifications as $notification)
                        <div @class(['flex items-start justify-between gap-4 px-5 py-4', 'bg-indigo-50/60' => $notification->read_at === null])>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-semibold text-slate-900">{{ $notification->data['title'] ?? 'Account update' }}</p>
                                    @if ($notification->read_at === null)
                                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-700">New</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $notification->data['message'] ?? 'There is an update to your account.' }}</p>
                                <p class="mt-2 text-xs text-slate-400">{{ $notification->created_at->format('M j, Y · g:i A') }}</p>
                            </div>
                            @if (! empty($notification->data['url']))
                                <form method="POST" action="{{ route('notifications.open', $notification) }}">
                                    @csrf
                                    <button class="shrink-0 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-white">View</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-slate-500">No payment or account updates yet.</p>
                    @endforelse
                </div>
                @if ($notifications->hasPages())
                    <div class="border-t border-slate-100 px-5 py-4">{{ $notifications->links() }}</div>
                @endif
            </section>

            <aside class="space-y-5">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="font-bold text-slate-900">{{ Auth::user()->isAdmin() ? 'Next student payments' : 'Your next payments' }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Outstanding bills with a scheduled due date.</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse ($upcomingPayments as $bill)
                            <a href="{{ Auth::user()->isAdmin() ? route('admin.bills.show', $bill) : route('student.bills.statement', $bill) }}" class="block px-5 py-4 transition hover:bg-slate-50">
                                @if (Auth::user()->isAdmin())
                                    <p class="text-sm font-semibold text-slate-900">{{ $bill->studentProfile->invoice_name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $bill->studentProfile->student_number }} · {{ $bill->invoice_number }}</p>
                                @else
                                    <p class="text-sm font-semibold text-slate-900">{{ $bill->billing_period }} · {{ $bill->schoolTerm->display_name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $bill->invoice_number }}</p>
                                @endif
                                <div class="mt-2 flex items-center justify-between gap-3 text-xs">
                                    <span @class(['font-semibold', 'text-rose-700' => $bill->due_date->isBefore(today()), 'text-slate-500' => ! $bill->due_date->isBefore(today())])>
                                        {{ $bill->due_date->isBefore(today()) ? 'Past due' : 'Due' }} {{ $bill->due_date->format('M j, Y') }}
                                    </span>
                                    <span class="font-bold text-slate-800">₱{{ number_format((float) $bill->remaining_balance, 2) }}</span>
                                </div>
                            </a>
                        @empty
                            <p class="px-5 py-8 text-sm leading-6 text-slate-500">No outstanding bills currently have a payment due date. A due date will appear here as soon as one is scheduled.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
