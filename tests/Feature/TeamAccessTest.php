<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class TeamAccessTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    public function test_a_designer_cannot_view_a_subcategory_in_a_team_they_are_not_assigned_to(): void
    {
        $project = $this->makeProject();
        $mep = $this->makeSpecialty('机电', '给排水');
        $subcategory = $this->makeSubcategory($project, $mep);
        // 只分配了项目权限，没有分配机电这个团队
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project]);

        $response = $this->actingAs($designer)->get("/projects/{$project->id}/subcategories/{$subcategory->id}");

        $response->assertForbidden();
    }

    public function test_a_designer_can_view_a_subcategory_in_a_team_they_are_assigned_to(): void
    {
        $project = $this->makeProject();
        $mep = $this->makeSpecialty('机电', '给排水');
        $subcategory = $this->makeSubcategory($project, $mep);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$mep->team]);

        $response = $this->actingAs($designer)->get("/projects/{$project->id}/subcategories/{$subcategory->id}");

        $response->assertOk();
    }

    public function test_the_project_tree_only_shows_teams_the_user_is_assigned_to(): void
    {
        $project = $this->makeProject();
        $civil = $this->makeSpecialty('土建', '结构')->team;
        $mep = $this->makeSpecialty('机电', '给排水')->team;
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$civil]);

        $tree = \App\Services\ProjectTree::build($project, $designer);

        $this->assertTrue($tree->pluck('id')->contains($civil->id));
        $this->assertFalse($tree->pluck('id')->contains($mep->id));
    }

    public function test_an_admin_sees_every_team_regardless_of_bindings(): void
    {
        $project = $this->makeProject();
        $civil = $this->makeSpecialty('土建', '结构')->team;
        $mep = $this->makeSpecialty('机电', '给排水')->team;
        $admin = $this->makeUser(User::ROLE_ADMIN);

        $tree = \App\Services\ProjectTree::build($project, $admin);

        $this->assertTrue($tree->pluck('id')->contains($civil->id));
        $this->assertTrue($tree->pluck('id')->contains($mep->id));
    }
}
