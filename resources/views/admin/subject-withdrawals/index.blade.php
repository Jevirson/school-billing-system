<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">STUDENT MANAGEMENT</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Subject withdrawal requests</h1>
        </div>
    </x-slot>

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-5">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <section class="space-y-4">
                @forelse ($withdrawalRequests as $withdrawalRequest)
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-bold text-slate-900">{{ $withdrawalRequest->studentProfile->invoice_name }}</h2>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $withdrawalRequest->studentProfile->course?->code ?? 'No course' }} · {{ $withdrawalRequest->studentProfile->year_level ?? 'Year not set' }}</span>
                                </div>
                                <p class="mt-1 text-sm font-semibold text-indigo-800">{{ $withdrawalRequest->enrollment->subject->code }} · {{ $withdrawalRequest->enrollment->subject->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $withdrawalRequest->enrollment->schoolTerm->display_name }} · {{ $withdrawalRequest->enrollment->units }} units · Requested {{ $withdrawalRequest->created_at->format('M j, Y · g:i A') }}</p>
                                @if ($withdrawalRequest->student_note)<p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $withdrawalRequest->student_note }}</p>@endif
                            </div>
                            <span @class(['rounded-full px-3 py-1 text-xs font-bold', 'bg-amber-100 text-amber-800' => $withdrawalRequest->status === 'Pending', 'bg-emerald-100 text-emerald-800' => $withdrawalRequest->status === 'Approved', 'bg-rose-100 text-rose-800' => $withdrawalRequest->status === 'Rejected'])>{{ $withdrawalRequest->status }}</span>
                        </div>

                        @if ($withdrawalRequest->status === 'Pending')
                            <form method="POST" action="{{ route('admin.subject-withdrawals.decide', $withdrawalRequest) }}" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-[1fr_auto_auto]">@csrf
                                <input name="admin_note" maxlength="1000" placeholder="Decision note (required when rejecting)" class="rounded-lg border-slate-300 text-sm">
                                <button name="decision" value="approve" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700" onclick="return confirm('Approve this subject withdrawal and adjust any matching billed subject fee?')">Approve withdrawal</button>
                                <button name="decision" value="reject" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50" onclick="return confirm('Reject this withdrawal request?')">Reject</button>
                            </form>
                        @else
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 text-sm">
                                <p class="text-slate-600">Reviewed {{ $withdrawalRequest->reviewed_at?->format('M j, Y · g:i A') }}{{ $withdrawalRequest->reviewer ? ' by '.$withdrawalRequest->reviewer->name : '' }}{{ $withdrawalRequest->admin_note ? ' · '.$withdrawalRequest->admin_note : '' }}</p>
                                @if ($withdrawalRequest->credited_amount > 0)
                                    <span class="font-semibold text-slate-700">Bill reduced by ₱{{ number_format((float) $withdrawalRequest->credited_amount, 2) }}</span>
                                @endif
                            </div>
                        @endif

                        @if ($withdrawalRequest->refund)
                            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div><p class="font-semibold text-amber-950">Cash refund · ₱{{ number_format((float) $withdrawalRequest->refund->amount, 2) }}</p><p class="mt-1 text-xs text-amber-800">Status: {{ $withdrawalRequest->refund->status }}{{ $withdrawalRequest->refund->bill ? ' · '.$withdrawalRequest->refund->bill->invoice_number : '' }}</p></div>
                                    @if ($withdrawalRequest->refund->status === 'Pending')
                                        <form method="POST" action="{{ route('admin.subject-withdrawals.refunds.paid', $withdrawalRequest->refund) }}" class="flex flex-wrap items-center gap-2">@csrf
                                            <select name="method" required class="rounded-lg border-amber-300 bg-white text-sm"><option value="">Refund method</option><option>Cash</option><option>Bank transfer</option><option>Other</option></select>
                                            <input name="reference" maxlength="100" placeholder="Reference (optional)" class="rounded-lg border-amber-300 bg-white text-sm">
                                            <button class="rounded-lg bg-amber-700 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-800" onclick="return confirm('Confirm the cash refund has actually been paid?')">Record paid</button>
                                        </form>
                                    @else
                                        <p class="text-xs text-amber-900">Paid {{ $withdrawalRequest->refund->paid_at?->format('M j, Y · g:i A') }} by {{ $withdrawalRequest->refund->recordedBy?->name }}{{ $withdrawalRequest->refund->reference ? ' · Ref '.$withdrawalRequest->refund->reference : '' }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center">
                        <p class="font-semibold text-slate-800">No withdrawal requests</p>
                        <p class="mt-1 text-sm text-slate-500">Student requests will appear here for review.</p>
                    </div>
                @endforelse
            </section>
            @if ($withdrawalRequests->hasPages())<div class="rounded-xl border border-slate-200 bg-white p-4">{{ $withdrawalRequests->links() }}</div>@endif
        </div>
    </main>
</x-app-layout>
