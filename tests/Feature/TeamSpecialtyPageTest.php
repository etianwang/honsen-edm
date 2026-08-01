<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class TeamSpecialtyPageTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    public function test_a_designer_can_view_a_team_page_for_a_team_they_are_assigned_to(): void
    {
        $project = $this->makeProject();
        $mep = $this->makeSpecialty('机电', '给排水');
        $subcategory = $this->makeSubcategory($project, $mep);
        $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$mep->team]);

        $response = $this->actingAs($designer)->get("/projects/{$project->id}/teams/{$mep->team->id}");

        $response->assertOk();
        $response->assertSee('机电');
        $response->assertSee('给排水');
    }

    public function test_a_designer_cannot_view_a_team_page_for_a_team_they_are_not_assigned_to(): void
    {
        $project = $this->makeProject();
        $mep = $this->makeSpecialty('机电', '给排水');
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project]);

        $response = $this->actingAs($designer)->get("/projects/{$project->id}/teams/{$mep->team->id}");

        $response->assertForbidden();
    }

    public function test_a_designer_can_view_a_specialty_page_for_a_specialty_they_are_assigned_to(): void
    {
        $project = $this->makeProject();
        $mep = $this->makeSpecialty('机电', '给排水');
        $subcategory = $this->makeSubcategory($project, $mep);
        $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$mep->team]);

        $response = $this->actingAs($designer)->get("/projects/{$project->id}/specialties/{$mep->id}");

        $response->assertOk();
        $response->assertSee('给排水');
        $response->assertSee($subcategory->name);
    }

    public function test_a_designer_cannot_view_a_specialty_page_outside_their_team(): void
    {
        $project = $this->makeProject();
        $mep = $this->makeSpecialty('机电', '给排水');
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project]);

        $response = $this->actingAs($designer)->get("/projects/{$project->id}/specialties/{$mep->id}");

        $response->assertForbidden();
    }

    public function test_the_team_page_stats_only_count_that_teams_versions(): void
    {
        $project = $this->makeProject();
        $mep = $this->makeSpecialty('机电', '给排水');
        $civil = $this->makeSpecialty('土建', '结构');
        $mepSub = $this->makeSubcategory($project, $mep, '机电分类');
        $civilSub = $this->makeSubcategory($project, $civil, '土建分类');
        $this->makeVersion($mepSub, 'V1');
        $this->makeVersion($civilSub, 'V1');
        $admin = $this->makeUser(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->get("/projects/{$project->id}/teams/{$mep->team->id}");

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['subcategories'] === 1 && $stats['versions'] === 1);
    }

    public function test_the_specialty_page_stats_only_count_that_specialtys_versions(): void
    {
        $project = $this->makeProject();
        $mep = $this->makeSpecialty('机电', '给排水');
        $otherMep = $mep->team->specialties()->create(['name' => '强电', 'code' => 'EL', 'sort_order' => 2]);
        $sub1 = $this->makeSubcategory($project, $mep, '给排水分类');
        $sub2 = $this->makeSubcategory($project, $otherMep, '强电分类');
        $this->makeVersion($sub1, 'V1');
        $this->makeVersion($sub2, 'V1');
        $admin = $this->makeUser(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->get("/projects/{$project->id}/specialties/{$mep->id}");

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['subcategories'] === 1 && $stats['versions'] === 1);
    }

    public function test_the_subcategory_breadcrumb_links_to_the_real_team_and_specialty_pages(): void
    {
        $project = $this->makeProject();
        $mep = $this->makeSpecialty('机电', '给排水');
        $subcategory = $this->makeSubcategory($project, $mep);
        $admin = $this->makeUser(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->get("/projects/{$project->id}/subcategories/{$subcategory->id}");

        $response->assertOk();
        $response->assertSee("/projects/{$project->id}/teams/{$mep->team->id}", false);
        $response->assertSee("/projects/{$project->id}/specialties/{$mep->id}", false);
    }
}
