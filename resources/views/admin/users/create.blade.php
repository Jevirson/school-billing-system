<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-bold text-slate-900">Create administrator account</h1>
            <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-indigo-600 hover:underline">Back to users</a>
        </div>
    </x-slot>
    <div class="bg-slate-50 px-4 py-8 sm:px-6">
        <div class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @if ($errors->any())
                <div class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <p class="mb-5 text-sm leading-6 text-slate-600">Student accounts are created and managed from the Students section so each one is linked to a student profile.</p>
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5">
                @csrf
                <label class="block text-sm font-medium text-slate-700">Full name
                    <input name="name" value="{{ old('name') }}" required maxlength="255" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">Username
                        <input name="username" value="{{ old('username') }}" required minlength="3" maxlength="30" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Email
                        <input name="email" type="email" value="{{ old('email') }}" required maxlength="255" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">Password
                        <input name="password" type="password" required minlength="8" autocomplete="new-password" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Confirm password
                        <input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="{{ route('admin.users.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
                    <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Create admin</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
