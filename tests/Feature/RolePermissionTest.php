<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    public function test_construction_cannot_create_a_subcategory(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $user = $this->makeUser(User::ROLE_CONSTRUCTION, [$project]);

        $response = $this->actingAs($user)->post("/projects/{$project->id}/specialties/{$specialty->id}/subcategories", [
            'name' => '消防水',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('subcategories', ['name' => '消防水']);
    }

    public function test_designer_can_create_a_subcategory(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $user = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $response = $this->actingAs($user)->post("/projects/{$project->id}/specialties/{$specialty->id}/subcategories", [
            'name' => '消防水',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subcategories', ['name' => '消防水', 'project_id' => $project->id]);
    }

    public function test_designer_without_team_access_cannot_create_a_subcategory_in_a_team_they_are_not_assigned_to(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty('机电', '给排水');
        // 设计师没有被分配到这个专业所属的团队（机电），即使有项目权限也应该被拦下
        $user = $this->makeUser(User::ROLE_DESIGNER, [$project]);

        $response = $this->actingAs($user)->post("/projects/{$project->id}/specialties/{$specialty->id}/subcategories", [
            'name' => '消防水',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_has_designer_level_content_permissions(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $admin = $this->makeUser(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->post("/projects/{$project->id}/specialties/{$specialty->id}/subcategories", [
            'name' => '消防水',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subcategories', ['name' => '消防水', 'project_id' => $project->id]);
    }

    public function test_construction_cannot_delete_a_version(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $user = $this->makeUser(User::ROLE_CONSTRUCTION, [$project]);

        $response = $this->actingAs($user)->delete("/versions/{$version->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('versions', ['id' => $version->id, 'deleted_at' => null]);
    }

    public function test_only_admin_can_reach_the_admin_panel(): void
    {
        $designer = $this->makeUser(User::ROLE_DESIGNER);
        $construction = $this->makeUser(User::ROLE_CONSTRUCTION);
        $admin = $this->makeUser(User::ROLE_ADMIN);

        $this->actingAs($designer)->get('/admin')->assertForbidden();
        $this->actingAs($construction)->get('/admin')->assertForbidden();
        $this->actingAs($admin)->get('/admin')->assertOk();
    }
}
