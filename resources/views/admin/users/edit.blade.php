<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-bold text-slate-900">Edit administrator account</h1>
            <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-indigo-600 hover:underline">Back to users</a>
        </div>
    </x-slot>
    <div class="bg-slate-50 px-4 py-8 sm:px-6">
        <div class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @if ($errors->any())
                <div class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5">
                @csrf
                @method('PUT')
                <label class="block text-sm font-medium text-slate-700">Full name
                    <input name="name" value="{{ old('name', $user->name) }}" required maxlength="255" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">Username
                        <input name="username" value="{{ old('username', $user->username) }}" required minlength="3" maxlength="30" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Email
                        <input name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                </div>
                <p class="text-sm text-slate-500">Leave password fields blank to keep the current password.</p>
                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">New password
                        <input name="password" type="password" minlength="8" autocomplete="new-password" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Confirm new password
                        <input name="password_confirmation" type="password" minlength="8" autocomplete="new-password" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="{{ route('admin.users.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
                    <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Save account</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
