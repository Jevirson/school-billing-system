<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(15);

        $upcomingPayments = Bill::query()
            ->with(['studentProfile.user', 'schoolTerm', 'payments'])
            ->whereNotNull('due_date')
            ->whereRaw('(SELECT COALESCE(SUM(payments.amount), 0) FROM payments WHERE payments.bill_id = bills.id) < bills.net_amount_due')
            ->when($user->isStudent(), fn ($query) => $query->where('student_profile_id', $user->studentProfile?->id ?? 0))
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        return view('notifications.index', compact('notifications', 'upcomingPayments'));
    }

    public function open(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->getKey()
            && $notification->notifiable_type === $request->user()->getMorphClass(), 404);

        $notification->markAsRead();
        $target = $notification->data['url'] ?? null;
        $targetHost = is_string($target) ? parse_url($target, PHP_URL_HOST) : null;

        if (is_string($target) && ($targetHost === null || $targetHost === $request->getHost())) {
            return redirect()->to($target);
        }

        return redirect()->route('notifications.index');
    }
}
