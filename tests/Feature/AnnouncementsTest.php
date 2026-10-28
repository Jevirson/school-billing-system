<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SchoolAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_announcement_notifications_to_all_students(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $students = User::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertSee('Send an announcement');

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'Campus update',
                'message' => 'The library will open at 8 AM.',
            ])
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('status', 'Announcement sent to 2 students.');

        foreach ($students as $student) {
            $notification = $student->notifications()->firstOrFail();

            $this->assertSame(SchoolAnnouncementNotification::class, $notification->type);
            $this->assertSame('Campus update', $notification->data['title']);
            $this->assertSame('The library will open at 8 AM.', $notification->data['message']);

            $this->actingAs($student)
                ->get(route('student.dashboard'))
                ->assertOk()
                ->assertSee('Campus update')
                ->assertSee('The library will open at 8 AM.')
                ->assertSee('View all');
        }

        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_students_cannot_send_announcements(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->post(route('admin.announcements.store'), [
                'title' => 'Unauthorized',
                'message' => 'This should not be sent.',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_send_an_announcement_without_student_recipients(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->actingAs($admin)
            ->from(route('admin.announcements.index'))
            ->post(route('admin.announcements.store'), [
                'title' => 'Campus update',
                'message' => 'The library will open at 8 AM.',
            ])
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('notifications', 0);
    }
}
