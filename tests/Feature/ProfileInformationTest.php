<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_profile_information_is_available(): void
    {
        $this->actingAs($user = User::factory()->create());

        $component = Livewire::test(UpdateProfileInformationForm::class);

        $this->assertEquals($user->name, $component->state['name']);
        $this->assertEquals($user->username, $component->state['username']);
        $this->assertEquals($user->email, $component->state['email']);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', ['name' => 'Test Name', 'username' => 'testname', 'email' => 'test@example.com'])
            ->call('updateProfileInformation');

        $this->assertEquals('Test Name', $user->fresh()->name);
        $this->assertEquals('testname', $user->fresh()->username);
        $this->assertEquals('test@example.com', $user->fresh()->email);
    }

    public function test_updating_profile_information_does_not_change_the_user_role(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', ['name' => 'Updated Student', 'username' => $user->username, 'email' => $user->email])
            ->call('updateProfileInformation');

        $this->assertSame(User::ROLE_STUDENT, $user->fresh()->role);
    }

    public function test_authenticated_header_has_an_account_dropdown_with_profile_and_logout_actions(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => User::ROLE_ADMIN])->save();
        $this->actingAs($user);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Open account menu')
            ->assertSee('Account menu')
            ->assertSee('Edit your account information')
            ->assertSee('Log out')
            ->assertSee(route('logout'), false);
    }

    public function test_students_and_admins_can_upload_and_remove_their_own_profile_photos(): void
    {
        Storage::fake('public');

        foreach ([User::ROLE_STUDENT, User::ROLE_ADMIN] as $role) {
            $user = User::factory()->create();
            $username = $role === User::ROLE_ADMIN ? 'profile_admin' : 'profile_student';
            $user->forceFill(['role' => $role, 'username' => $username])->save();
            $this->actingAs($user);

            $this->get(route('profile.show'))
                ->assertOk()
                ->assertSee('Select A New Photo')
                ->assertSee('alt="'.$user->name.'"', false);

            $component = Livewire::test(UpdateProfileInformationForm::class)
                ->set('photo', UploadedFile::fake()->create('profile.jpg', 2048, 'image/jpeg'))
                ->call('updateProfileInformation')
                ->assertHasNoErrors();

            $photoPath = $user->fresh()->profile_photo_path;
            $this->assertNotNull($photoPath);
            Storage::disk('public')->assertExists($photoPath);
            $this->assertSame($role, $user->fresh()->role);
            $this->get(route('dashboard'))
                ->assertRedirect(route($role === User::ROLE_ADMIN ? 'admin.dashboard' : 'student.dashboard'));

            Livewire::test(UpdateProfileInformationForm::class)
                ->call('deleteProfilePhoto');

            $this->assertNull($user->fresh()->profile_photo_path);
            Storage::disk('public')->assertMissing($photoPath);
        }
    }
}
