<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">PAYMENT CORRECTION</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-900">Edit payment</h2>
            </div>
            <a href="{{ route('admin.bills.show', $payment->bill) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to bill</a>
        </div>
    </x-slot>

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl space-y-6">
            @if ($errors->any())
                <div class="rounded-xl bg-red-50 p-4 text-sm text-red-800">
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm text-slate-500">{{ $payment->bill->invoice_number }} · {{ $payment->bill->schoolTerm->display_name }}</p>
                <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ $payment->bill->studentProfile->invoice_name }}</h3>
                <p class="mt-1 text-sm text-slate-600">Student ID: {{ $payment->bill->studentProfile->student_number }} · Current balance: ₱{{ number_format((float) $payment->bill->remaining_balance, 2) }}</p>

                <form method="POST" action="{{ route('admin.payments.update', $payment) }}" class="mt-6 grid gap-4 sm:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <label class="text-sm font-medium text-slate-700">Amount (₱)
                        <input name="amount" type="number" min="0.01" step="0.01" max="{{ number_format((float) $payment->amount + (float) $payment->bill->remaining_balance, 2, '.', '') }}" value="{{ old('amount', $payment->amount) }}" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Payment date
                        <input name="paid_at" type="datetime-local" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('paid_at', $payment->paid_at->format('Y-m-d\TH:i')) }}" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Method
                        <select name="method" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                            @foreach (['Cash', 'Bank transfer', 'GCash', 'Check', 'Other'] as $method)
                                <option value="{{ $method }}" @selected(old('method', $payment->method) === $method)>{{ $method }}</option>
                            @endforeach
                            @if (!in_array($payment->method, ['Cash', 'Bank transfer', 'GCash', 'Check', 'Other'], true))
                                <option value="{{ $payment->method }}" selected>{{ $payment->method }}</option>
                            @endif
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Reference (optional)
                        <input name="reference" value="{{ old('reference', $payment->reference) }}" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700 sm:col-span-2">Notes (optional)
                        <textarea name="notes" rows="3" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">{{ old('notes', $payment->notes) }}</textarea>
                    </label>
                    <div class="flex justify-end gap-3 sm:col-span-2">
                        <a href="{{ route('admin.bills.show', $payment->bill) }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                        <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Save correction</button>
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-slate-900">Correction audit history</h3>
                <p class="mt-1 text-sm text-slate-500">Each saved change is recorded with its previous and corrected values.</p>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse ($payment->revisions->sortByDesc('created_at') as $revision)
                        <div class="py-4 first:pt-0">
                            <p class="text-xs text-slate-500">{{ $revision->created_at->format('M j, Y g:i A') }} · edited by {{ $revision->editedBy->name }}</p>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <p class="text-xs font-semibold uppercase text-slate-500">Previous values</p>
                                    <p class="mt-1 text-sm">₱{{ number_format((float) $revision->old_values['amount'], 2) }} · {{ $revision->old_values['method'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $revision->old_values['paid_at'] }} · {{ $revision->old_values['reference'] ?: 'No reference' }}</p>
                                </div>
                                <div class="rounded-lg bg-indigo-50 p-3">
                                    <p class="text-xs font-semibold uppercase text-indigo-700">Corrected values</p>
                                    <p class="mt-1 text-sm">₱{{ number_format((float) $revision->new_values['amount'], 2) }} · {{ $revision->new_values['method'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $revision->new_values['paid_at'] }} · {{ $revision->new_values['reference'] ?: 'No reference' }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-slate-500">No corrections have been made to this payment.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </main>
</x-app-layout>
