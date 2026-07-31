<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class ProjectAccessTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    public function test_construction_can_view_a_project_they_are_assigned_to(): void
    {
        $project = $this->makeProject();
        $user = $this->makeUser(User::ROLE_CONSTRUCTION, [$project]);

        $response = $this->actingAs($user)->get("/projects/{$project->id}");

        $response->assertOk();
    }

    public function test_construction_cannot_view_a_project_they_are_not_assigned_to(): void
    {
        $assigned = $this->makeProject('科特迪瓦', 'Bethel办公楼');
        $other = $this->makeProject('科特迪瓦', 'Kerene别墅');
        $user = $this->makeUser(User::ROLE_CONSTRUCTION, [$assigned]);

        $response = $this->actingAs($user)->get("/projects/{$other->id}");

        $response->assertForbidden();
    }

    public function test_designer_cannot_view_a_project_they_are_not_assigned_to(): void
    {
        $assigned = $this->makeProject('科特迪瓦', 'Bethel办公楼');
        $other = $this->makeProject('科特迪瓦', 'Kerene别墅');
        $user = $this->makeUser(User::ROLE_DESIGNER, [$assigned]);

        $response = $this->actingAs($user)->get("/projects/{$other->id}");

        $response->assertForbidden();
    }

    public function test_admin_can_view_any_project_without_being_assigned(): void
    {
        $project = $this->makeProject();
        $admin = $this->makeUser(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->get("/projects/{$project->id}");

        $response->assertOk();
    }

    public function test_home_redirects_construction_to_their_first_assigned_project(): void
    {
        $project = $this->makeProject();
        $user = $this->makeUser(User::ROLE_CONSTRUCTION, [$project]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('project.show', $project));
    }

    public function test_home_aborts_for_a_user_with_no_assigned_projects(): void
    {
        $this->makeProject();
        $user = $this->makeUser(User::ROLE_CONSTRUCTION, []);

        $response = $this->actingAs($user)->get('/');

        $response->assertForbidden();
    }
}
