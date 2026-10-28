<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PromissoryLetter;
use App\Models\PromissoryLetterEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->with('studentProfile')->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $data['name'],
            'username' => strtolower($data['username']),
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ])->forceFill([
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
        ])->save();

        return redirect()->route('admin.users.index')->with('status', 'Administrator account created.');
    }

    public function edit(User $user): View|RedirectResponse
    {
        if ($user->studentProfile()->exists()) {
            return redirect()->route('admin.students.index')->with('error', 'Edit student account details from Student management.');
        }

        return view('admin.users.edit', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->studentProfile()->exists()) {
            return back()->withErrors([
                'user' => 'Edit student account details from Student management.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $normalizedEmail = strtolower($data['email']);
        $user->fill([
            'name' => $data['name'],
            'username' => strtolower($data['username']),
            'email' => $normalizedEmail,
        ]);
        if ($user->getOriginal('email') !== $normalizedEmail) {
            $user->email_verified_at = null;
        }
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return redirect()->route('admin.users.index')->with('status', 'User account updated.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_STUDENT])],
        ]);

        $this->ensureRoleChangeAllowed($user, $data['role']);

        $user->forceFill(['role' => $data['role']])->save();

        return back()->with('status', 'User role updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete your own account.');
        }
        if ($user->studentProfile()->exists()) {
            return redirect()->route('admin.users.index')->with('error', 'Student accounts must be deleted from Student management so billing protections are applied.');
        }
        if (
            Payment::where('recorded_by', $user->id)->exists()
            || PromissoryLetter::where('reviewed_by', $user->id)->exists()
            || PromissoryLetterEvent::where('changed_by', $user->id)->exists()
        ) {
            return redirect()->route('admin.users.index')->with('error', 'This account is linked to payment or promissory audit history and cannot be deleted.');
        }
        if ($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return redirect()->route('admin.users.index')->with('error', 'The last administrator account cannot be deleted.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User account deleted.');
    }

    private function ensureRoleChangeAllowed(User $user, string $role): void
    {
        if ($role === User::ROLE_STUDENT && ! $user->studentProfile()->exists()) {
            throw ValidationException::withMessages(['role' => 'Create student accounts from Student management so they have a student profile.']);
        }

        if ($user->is(auth()->user()) && $role !== User::ROLE_ADMIN) {
            throw ValidationException::withMessages(['role' => 'You cannot remove your own administrator access.']);
        }

        if ($user->isAdmin() && $role !== User::ROLE_ADMIN
            && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'The last administrator cannot be demoted.']);
        }
    }
}
