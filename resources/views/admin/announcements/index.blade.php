<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">SCHOOL COMMUNICATION</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Announcements</h1>
        </div>
    </x-slot>

    <div class="bg-slate-50 px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <div class="mx-auto max-w-3xl space-y-6">
            @if (session('status'))
                <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-lg font-bold text-slate-900">Send an announcement</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">Your announcement will appear in the notification area on every student dashboard.</p>

                <form method="POST" action="{{ route('admin.announcements.store') }}" class="mt-6 space-y-5">
                    @csrf
                    <div>
                        <label for="title" class="block text-sm font-semibold text-slate-700">Title</label>
                        <input id="title" name="title" type="text" maxlength="120" required value="{{ old('title') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="message" class="block text-sm font-semibold text-slate-700">Announcement</label>
                        <textarea id="message" name="message" rows="6" maxlength="5000" required class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('message') }}</textarea>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <a href="{{ route('admin.dashboard') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Back to dashboard</a>
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Send to students</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
