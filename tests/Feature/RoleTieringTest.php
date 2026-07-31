<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class RoleTieringTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    public function test_a_regular_admin_cannot_create_an_admin_account(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => '新管理员',
            'login_id' => 'NEWADMIN',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['login_id' => 'NEWADMIN']);
    }

    public function test_a_regular_admin_cannot_create_a_super_admin_account(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => '新超管',
            'login_id' => 'NEWSUPER',
            'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['login_id' => 'NEWSUPER']);
    }

    public function test_a_super_admin_can_create_an_admin_account(): void
    {
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($superAdmin)->post('/admin/users', [
            'name' => '新管理员',
            'login_id' => 'NEWADMIN',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['login_id' => 'NEWADMIN', 'role' => User::ROLE_ADMIN]);
    }

    public function test_a_regular_admin_cannot_edit_an_existing_admin_account(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $otherAdmin = $this->makeUser(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->patch("/admin/users/{$otherAdmin->id}", [
            'name' => '改名了',
            'role' => User::ROLE_ADMIN,
        ]);

        $response->assertForbidden();
        $this->assertNotSame('改名了', $otherAdmin->fresh()->name);
    }

    public function test_a_regular_admin_can_still_manage_designer_and_construction_accounts(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $designer = $this->makeUser(User::ROLE_DESIGNER);

        $response = $this->actingAs($admin)->patch("/admin/users/{$designer->id}", [
            'name' => '改名了',
            'role' => User::ROLE_DESIGNER,
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        $this->assertSame('改名了', $designer->fresh()->name);
    }
}
