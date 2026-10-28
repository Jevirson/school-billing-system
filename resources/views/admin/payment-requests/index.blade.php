<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">PAYMENT REVIEW</p><h1 class="mt-1 text-2xl font-bold text-slate-900">Student payment requests</h1></div>
            <a href="{{ route('admin.bills.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Bills</a>
        </div>
    </x-slot>
    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-5">
            @if (session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_200px_auto_auto]">
                <input name="student" value="{{ $filters['student'] ?? '' }}" placeholder="Search student ID or name" class="rounded-lg border-slate-300 text-sm">
                <select name="status" class="rounded-lg border-slate-300 text-sm"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filter</button>
                <a href="{{ route('admin.payment-requests.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700">Clear</a>
            </form>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-4">Student</th><th class="px-5 py-4">Bill / term</th><th class="px-5 py-4">Request</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Decision</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($paymentRequests as $paymentRequest)
                                <tr class="align-top">
                                    <td class="px-5 py-4"><p class="font-semibold text-slate-900">{{ $paymentRequest->studentProfile->invoice_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $paymentRequest->studentProfile->student_number }} · {{ $paymentRequest->studentProfile->course?->code ?? 'No course' }} · {{ $paymentRequest->studentProfile->year_level ?? 'No year' }}</p></td>
                                    <td class="px-5 py-4"><a href="{{ route('admin.bills.show', $paymentRequest->bill) }}" class="font-semibold text-indigo-600 hover:underline">{{ $paymentRequest->bill->invoice_number }}</a><p class="mt-1 text-xs text-slate-500">{{ $paymentRequest->bill->schoolTerm->display_name }} · {{ $paymentRequest->bill->billing_period }}</p><p class="text-xs text-slate-500">Due {{ $paymentRequest->bill->due_date?->format('M j, Y') ?? 'not scheduled' }} · Balance: ₱{{ number_format((float) $paymentRequest->bill->remaining_balance, 2) }}</p></td>
                                    <td class="px-5 py-4"><p class="font-bold text-slate-900">₱{{ number_format((float) $paymentRequest->amount, 2) }}</p><p class="mt-1 text-xs text-slate-500">{{ $paymentRequest->created_at->format('M j, Y g:i A') }}</p>@if($paymentRequest->reference)<p class="mt-1 text-xs text-slate-500">Ref: {{ $paymentRequest->reference }}</p>@endif @if($paymentRequest->student_note)<p class="mt-2 max-w-xs whitespace-pre-line text-xs text-slate-600">{{ $paymentRequest->student_note }}</p>@endif</td>
                                    <td class="px-5 py-4"><span @class(['rounded-full px-2.5 py-1 text-xs font-semibold','bg-amber-50 text-amber-800' => $paymentRequest->status === 'Pending','bg-emerald-50 text-emerald-800' => $paymentRequest->status === 'Approved','bg-rose-50 text-rose-800' => $paymentRequest->status === 'Rejected'])>{{ $paymentRequest->status }}</span>@if($paymentRequest->reviewer)<p class="mt-2 text-xs text-slate-500">Reviewed by {{ $paymentRequest->reviewer->name }} · {{ $paymentRequest->reviewed_at->format('M j, Y') }}</p>@endif @if($paymentRequest->admin_note)<p class="mt-2 max-w-xs whitespace-pre-line text-xs text-slate-600">{{ $paymentRequest->admin_note }}</p>@endif</td>
                                    <td class="px-5 py-4">
                                        @if ($paymentRequest->status === 'Pending')
                                            <div class="space-y-3">
                                                <form method="POST" action="{{ route('admin.payment-requests.decide', $paymentRequest) }}" class="space-y-2 rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                                                    @csrf<input type="hidden" name="decision" value="approve">
                                                    <select name="method" required class="w-full rounded border-slate-300 text-xs"><option value="">Payment method</option><option>Office Payment</option><option>Bank transfer</option><option>GCash</option><option>Check</option><option>Other</option></select>
                                                    <input name="reference" value="{{ $paymentRequest->reference }}" placeholder="Receipt / reference" maxlength="100" class="w-full rounded border-slate-300 text-xs">
                                                    <input name="admin_note" placeholder="Admin note (optional)" maxlength="1000" class="w-full rounded border-slate-300 text-xs">
                                                    <button class="w-full rounded bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Confirm receipt &amp; approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.payment-requests.decide', $paymentRequest) }}" class="space-y-2 rounded-lg border border-rose-100 bg-rose-50 p-3">
                                                    @csrf<input type="hidden" name="decision" value="reject">
                                                    <textarea name="admin_note" required minlength="3" maxlength="1000" rows="2" placeholder="Reason for rejection" class="w-full rounded border-slate-300 text-xs"></textarea>
                                                    <button class="w-full rounded border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100">Reject request</button>
                                                </form>
                                            </div>
                                        @elseif ($paymentRequest->payment_id)
                                            <a href="{{ route('admin.bills.show', $paymentRequest->bill) }}#payments" class="font-semibold text-indigo-600 hover:underline">View recorded payment</a>
                                        @else
                                            <span class="text-xs text-slate-400">No further action</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No payment requests match these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-4">{{ $paymentRequests->links() }}</div>
            </section>
        </div>
    </main>
</x-app-layout>
