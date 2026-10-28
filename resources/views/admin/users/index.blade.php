<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-600">ADMINISTRATION</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-gray-900">User accounts</h2>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                &larr; Back to dashboard
            </a>
            <a href="{{ route('admin.users.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                Create admin account
            </a>
        </div>
    </x-slot>

    <div class="min-h-[calc(100vh-9rem)] bg-slate-50 px-4 py-10 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            @if (session('status'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h1 class="font-semibold text-slate-900">Registered users</h1>
                    <p class="mt-1 text-sm text-slate-500">Create administrator accounts here; create and edit student accounts in Student management.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">User</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Current role</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($users as $user)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <p class="text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $user->email }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize',
                                            'bg-indigo-50 text-indigo-700' => $user->isAdmin(),
                                            'bg-slate-100 text-slate-700' => $user->isStudent(),
                                        ])>{{ $user->role }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <form method="POST" action="{{ route('admin.users.role', $user) }}" class="flex justify-end gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <label for="role-{{ $user->id }}" class="sr-only">Role for {{ $user->name }}</label>
                                            <select id="role-{{ $user->id }}" name="role" class="rounded-lg border-slate-300 py-2 pl-3 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="student" @selected($user->isStudent()) @disabled(($user->id === auth()->id() && $user->isAdmin()) || ($user->isAdmin() && !$user->studentProfile))>Student</option>
                                                <option value="admin" @selected($user->isAdmin())>Admin</option>
                                            </select>
                                            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                                Save
                                            </button>
                                        </form>
                                        <div class="mt-2 flex justify-end gap-3 text-sm">
                                            @if ($user->studentProfile)
                                                <a href="{{ route('admin.students.edit', $user->studentProfile) }}" class="font-semibold text-indigo-600 hover:underline">Edit student</a>
                                            @else
                                                <a href="{{ route('admin.users.edit', $user) }}" class="font-semibold text-indigo-600 hover:underline">Edit account</a>
                                                @if ($user->id !== auth()->id())
                                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this account? Accounts with student records or payment audit history cannot be deleted.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="font-semibold text-rose-600 hover:underline">Delete</button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-12 text-center text-sm text-slate-500">No accounts have registered yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="border-t border-slate-200 px-6 py-4">
                        {{ $users->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
