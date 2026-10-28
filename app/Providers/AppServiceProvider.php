<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());
        Gate::define('student', fn (User $user): bool => $user->isStudent());

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();
            if (! $user) {
                return;
            }

            $view->with('unreadNotificationCount', $user->unreadNotifications()->count());

            if ($user->isAdmin()) {
                $view->with(
                    'pendingPaymentRequestCount',
                    \App\Models\PaymentRequest::where('status', 'Pending')->count()
                );
                $view->with(
                    'pendingPromissoryCount',
                    \App\Models\PromissoryLetter::where('status', 'Pending')->count()
                );
                $view->with(
                    'pendingSubjectWithdrawalCount',
                    \App\Models\SubjectWithdrawalRequest::where('status', 'Pending')->count()
                );
            }
        });
    }
}
