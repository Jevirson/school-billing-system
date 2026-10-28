<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SchoolAnnouncementNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $students = User::where('role', User::ROLE_STUDENT)->get();

        if ($students->isEmpty()) {
            return back()->withErrors(['message' => 'There are no student accounts to receive this announcement.'])->withInput();
        }

        DB::transaction(function () use ($students, $data): void {
            foreach ($students as $student) {
                $student->notify(new SchoolAnnouncementNotification($data['title'], $data['message']));
            }
        });

        return redirect()->route('admin.announcements.index')->with(
            'status',
            'Announcement sent to '.number_format($students->count()).' student'.($students->count() === 1 ? '' : 's').'.'
        );
    }
}
