<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">STUDENT PORTAL</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">My bills and balances</h1>
                <p class="mt-1 text-sm text-slate-500">Review your invoices, payments, and any requests waiting for school review.</p>
            </div>
            <a href="{{ route('student.promissory-letters.index') }}" class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">
                Promissory letters
            </a>
        </div>
    </x-slot>

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-6">
            @if (session('status'))
                <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">Please check the following:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                <p class="font-semibold">Submitting a payment request does not pay your bill yet.</p>
                <p class="mt-1">The school must confirm receipt first. Your balance changes only after the request is approved.</p>
            </div>

            @if ($student)
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-label="Student information">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Student</p>
                    <h2 class="mt-1 text-xl font-semibold text-slate-900">{{ $student->invoice_name }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $student->student_number }} · {{ $student->course?->name ?? 'Course not assigned' }} · {{ $student->year_level ?? 'Year level not assigned' }}</p>
                </section>
            @endif

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Bills">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-5 py-4">Invoice</th>
                                <th scope="col" class="px-5 py-4">School term</th>
                                <th scope="col" class="px-5 py-4">Total fees</th>
                                <th scope="col" class="px-5 py-4">Discount</th>
                                <th scope="col" class="px-5 py-4">Latest payment</th>
                                <th scope="col" class="px-5 py-4">Due this period</th>
                                <th scope="col" class="px-5 py-4">Status</th>
                                <th scope="col" class="px-5 py-4"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($bills as $bill)
                                @php($pendingPaymentRequest = $bill->paymentRequests->firstWhere('status', 'Pending'))
                                <tr class="align-top">
                                    <td class="px-5 py-4 font-semibold text-slate-900">{{ $bill->invoice_number }}</td>
                                    <td class="px-5 py-4">
                                        {{ $bill->schoolTerm->display_name }}
                                        <span class="block text-xs text-slate-500">{{ $bill->billing_period }} · Due {{ $bill->due_date?->format('M j, Y') ?? 'not set' }}</span>
                                    </td>
                                    <td class="px-5 py-4">₱{{ number_format((float) $bill->total_fees, 2) }}</td>
                                    <td class="px-5 py-4">₱{{ number_format((float) $bill->scholarship_discount, 2) }}</td>
                                    <td class="px-5 py-4">
                                        @if ($bill->latestPayment)
                                            ₱{{ number_format((float) $bill->latestPayment->amount, 2) }}
                                            <span class="block text-xs text-slate-500">{{ $bill->latestPayment->paid_at->format('M j, Y') }}</span>
                                        @else
                                            <span class="text-slate-500">No payments yet</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 font-semibold">
                                        ₱{{ number_format((float) $bill->period_balance, 2) }}
                                        @if ($bill->incomingExtensionBalance > 0)
                                            <span class="mt-1 block text-xs font-normal text-amber-800">Includes ₱{{ number_format((float) $bill->incomingExtensionBalance, 2) }} carried from an earlier period</span>
                                        @endif
                                        @if ($bill->extensions->isNotEmpty())
                                            @php($outgoingExtension = $bill->extensions->first())
                                            <span class="mt-1 block text-xs font-normal text-slate-500">₱{{ number_format((float) $bill->currentPeriodBalance, 2) }} remains due now. ₱{{ number_format((float) $outgoingExtension->amount_at_approval, 2) }} was moved to {{ $outgoingExtension->next_billing_period ?? 'a later period' }}.</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">{{ $bill->payment_status }}</td>
                                    <td class="px-5 py-4">
                                        <a class="font-semibold text-indigo-600 hover:underline" href="{{ route('student.bills.statement', $bill) }}">Statement</a>
                                        <a class="mt-1 block text-xs font-medium text-slate-600 hover:underline" href="{{ route('student.bills.invoice', $bill) }}">Invoice</a>

                                        @if ((float) $bill->remaining_balance > 0)
                                            @if ($pendingPaymentRequest)
                                                <p class="mt-2 rounded-lg bg-amber-50 p-2 text-xs leading-5 text-amber-900">
                                                    Payment request pending: ₱{{ number_format((float) $pendingPaymentRequest->amount, 2) }}. The school is reviewing it.
                                                </p>
                                                <a class="mt-1 block text-xs font-semibold text-indigo-600 hover:underline" href="{{ route('student.payments.index') }}">View request history</a>
                                            @else
                                                <details class="mt-2 min-w-56">
                                                    <summary class="cursor-pointer whitespace-nowrap text-xs font-semibold text-emerald-700 hover:underline">Submit a payment request</summary>
                                                    <form method="POST" action="{{ route('student.bills.payments.store', $bill) }}" x-data="{ confirming: false, paymentAmount: @js(old('amount')), paymentReference: @js(old('reference')) }" class="mt-2 space-y-2 rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                                                        @csrf
                                                        <label class="block text-xs font-medium text-slate-700">
                                                            Amount (up to ₱{{ number_format((float) $bill->remaining_balance, 2) }})
                                                            <input name="amount" type="number" min="0.01" max="{{ $bill->remaining_balance }}" step="0.01" value="{{ old('amount') }}" x-model="paymentAmount" required class="mt-1 block w-full rounded border-slate-300 text-sm">
                                                        </label>
                                                        <label class="block text-xs font-medium text-slate-700">
                                                            Payment reference (optional)
                                                            <input name="reference" maxlength="100" value="{{ old('reference') }}" x-model="paymentReference" class="mt-1 block w-full rounded border-slate-300 text-sm">
                                                        </label>
                                                        <p class="text-xs leading-5 text-slate-600">This sends a request to the school; it does not record a payment or change your balance.</p>
                                                        <button type="button" @click="if ($el.form.reportValidity()) confirming = true" class="w-full rounded bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Review payment request</button>
                                                        <div x-cloak x-show="confirming" role="status" class="space-y-2 rounded-lg border border-emerald-200 bg-white p-3">
                                                            <p class="font-semibold text-slate-900">Confirm request for ₱<span x-text="Number(paymentAmount || 0).toFixed(2)"></span></p>
                                                            <p class="text-xs leading-5 text-slate-600">This request will be sent to the school for verification. Your bill will not be marked paid until the school confirms receipt.</p>
                                                            <div class="flex gap-2">
                                                                <button type="button" @click="confirming = false" class="flex-1 rounded border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">Go back</button>
                                                                <button type="submit" class="flex-1 rounded bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">Confirm and send request</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </details>
                                            @endif
                                        @endif

                                        @php($letter = $bill->promissoryLetters->sortByDesc('submitted_at')->first())
                                        @if ($letter)
                                            <a class="mt-2 block text-xs font-semibold text-indigo-600 hover:underline" href="{{ route('student.promissory-letters.show', $letter) }}">Promissory request · {{ $letter->status }}</a>
                                        @elseif ((float) $bill->remaining_balance > 0 && $bill->extensions->isEmpty())
                                            <a class="mt-2 block whitespace-nowrap text-xs font-semibold text-amber-700 hover:underline" href="{{ route('student.promissory-letters.create', $bill) }}">Request a payment extension</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-slate-500">
                                        <p class="font-semibold text-slate-700">No bills are available yet.</p>
                                        <p class="mt-1">When the school issues an invoice, it will appear here.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($carryForwards->isNotEmpty())
                <section class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm" aria-label="Carried-forward balances">
                    <div class="border-b border-amber-100 bg-amber-50/70 px-5 py-4">
                        <h2 class="font-bold text-amber-950">Carried-forward balances</h2>
                        <p class="mt-1 text-sm text-amber-900">Each balance appears in its next payment period and stays linked to the original invoice. It is counted only once; payments on the original invoice reduce it.</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($carryForwards as $extension)
                            <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $extension->next_billing_period ?? 'Next payment period' }} · {{ $extension->nextSchoolTerm->display_name }}</p>
                                    <p class="mt-1 text-sm text-slate-600">
                                        Original invoice
                                        <a class="font-semibold text-indigo-600 hover:underline" href="{{ route('student.bills.invoice', $extension->sourceBill) }}">{{ $extension->sourceBill->invoice_number }}</a>
                                        · {{ $extension->sourceBill->billing_period }}
                                    </p>
                                    @if ($extension->destinationBill)
                                        <p class="mt-1 text-xs text-slate-500">Included in invoice {{ $extension->destinationBill->invoice_number }} with that period's fees.</p>
                                    @endif
                                </div>
                                <p class="text-right">
                                    <span class="block text-xs text-slate-500">Current original balance</span>
                                    <span class="font-bold text-slate-900">₱{{ number_format((float) $extension->remaining_balance, 2) }}</span>
                                </p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </main>
</x-app-layout>
