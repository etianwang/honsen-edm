<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    public function test_a_user_can_update_their_own_name(): void
    {
        $user = $this->makeUser(User::ROLE_DESIGNER);

        $response = $this->actingAs($user)->patch('/profile', ['name' => '新名字']);

        $response->assertRedirect();
        $this->assertSame('新名字', $user->fresh()->name);
    }

    public function test_a_user_can_change_their_own_password_with_the_correct_current_password(): void
    {
        $user = $this->makeUser(User::ROLE_DESIGNER, password: 'old-password');

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'current_password' => 'old-password',
            'new_password' => 'brand-new-password',
            'new_password_confirmation' => 'brand-new-password',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_changing_password_fails_with_the_wrong_current_password(): void
    {
        $user = $this->makeUser(User::ROLE_DESIGNER, password: 'old-password');

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'current_password' => 'not-the-real-password',
            'new_password' => 'brand-new-password',
            'new_password_confirmation' => 'brand-new-password',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_login_id_cannot_be_changed_through_the_profile_form(): void
    {
        $user = $this->makeUser(User::ROLE_DESIGNER);
        $originalLoginId = $user->login_id;

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'login_id' => 'HACKED',
        ]);

        $this->assertSame($originalLoginId, $user->fresh()->login_id);
    }
}
