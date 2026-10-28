<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class StudentRegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        return $request->wantsJson()
            ? new JsonResponse('', 201)
            : redirect()->route('student.dashboard');
    }
}
