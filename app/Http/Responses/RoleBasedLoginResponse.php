<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class RoleBasedLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();
        $dashboard = $user instanceof User && $user->isAdmin()
            ? 'admin.dashboard'
            : 'student.dashboard';

        return redirect()->route($dashboard);
    }
}
