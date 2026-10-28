<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_is_redirected_to_the_student_dashboard(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_admin_is_redirected_to_the_admin_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_dashboard_displays_account_summaries(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        User::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Good day, '.$admin->name.'.')
            ->assertSee('At a glance')
            ->assertSee('Student profiles')
            ->assertSee('3')
            ->assertSee('images/school-logo.jfif');
    }

    public function test_student_dashboard_displays_the_school_logo(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('images/school-logo.jfif');
    }

    public function test_students_cannot_access_admin_pages(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admins_cannot_access_the_student_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->actingAs($admin)
            ->get(route('student.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_change_another_users_role(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        $student = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $student), ['role' => User::ROLE_ADMIN])
            ->assertRedirect();

        $this->assertSame(User::ROLE_ADMIN, $student->fresh()->role);
    }

    public function test_admin_cannot_demote_the_last_administrator(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $admin), ['role' => User::ROLE_STUDENT])
            ->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    public function test_admin_cannot_remove_their_own_administrator_access(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        User::factory()->create()->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $admin), ['role' => User::ROLE_STUDENT])
            ->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    public function test_admin_can_create_update_and_delete_administrator_accounts_safely(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

        $this->actingAs($admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Create administrator account');
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Billing Admin',
            'username' => 'billing-admin',
            'email' => 'billing-admin@example.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('admin.users.index'));

        $createdUser = User::where('username', 'billing-admin')->firstOrFail();
        $this->assertSame(User::ROLE_ADMIN, $createdUser->role);
        $this->assertTrue(Hash::check('secret-password', $createdUser->password));

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $createdUser))
            ->assertOk()
            ->assertSee('Edit administrator account');
        $this->actingAs($admin)->put(route('admin.users.update', $createdUser), [
            'name' => 'Updated Billing Admin',
            'username' => 'billing-admin-updated',
            'email' => 'billing-admin-updated@example.test',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $createdUser->id,
            'name' => 'Updated Billing Admin',
            'username' => 'billing-admin-updated',
            'email' => 'billing-admin-updated@example.test',
        ]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $createdUser))
            ->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $createdUser->id]);
    }
}
