<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class CountryProjectAdminTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    public function test_an_admin_can_create_a_country_and_a_project(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);

        $this->actingAs($admin)->post('/admin/countries', ['name' => '科特迪瓦'])->assertRedirect();
        $country = Country::where('name', '科特迪瓦')->firstOrFail();

        $this->actingAs($admin)->post("/admin/countries/{$country->id}/projects", ['name' => 'Bethel办公楼'])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', ['name' => 'Bethel办公楼', 'country_id' => $country->id]);
    }

    public function test_a_designer_cannot_create_a_country(): void
    {
        $designer = $this->makeUser(User::ROLE_DESIGNER);

        $response = $this->actingAs($designer)->post('/admin/countries', ['name' => '科特迪瓦']);

        $response->assertForbidden();
        $this->assertDatabaseMissing('countries', ['name' => '科特迪瓦']);
    }

    public function test_deleting_a_country_requires_the_correct_password_and_soft_deletes_it(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN, password: 'correct-password');
        $country = Country::create(['name' => '科特迪瓦']);

        $wrong = $this->actingAs($admin)->delete("/admin/countries/{$country->id}", ['password' => 'nope']);
        $wrong->assertSessionHasErrors('password');
        $this->assertNotSoftDeleted($country);

        $this->actingAs($admin)->delete("/admin/countries/{$country->id}", ['password' => 'correct-password'])
            ->assertRedirect();

        $this->assertSoftDeleted($country);
    }

    public function test_deleting_a_project_soft_deletes_it_and_hides_it_from_route_binding(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN, password: 'correct-password');
        $country = Country::create(['name' => '科特迪瓦']);
        $project = Project::create(['country_id' => $country->id, 'name' => 'Bethel办公楼']);

        $this->actingAs($admin)->delete("/admin/projects/{$project->id}", ['password' => 'correct-password'])
            ->assertRedirect();

        $this->assertSoftDeleted($project);

        // 软删除之后，路由模型绑定应该找不到它了（对用户来说等于消失）
        $this->actingAs($admin)->get("/projects/{$project->id}")->assertNotFound();
    }

    public function test_a_super_admin_can_rename_a_project(): void
    {
        $admin = $this->makeUser(User::ROLE_SUPER_ADMIN);
        $country = Country::create(['name' => '科特迪瓦']);
        $project = Project::create(['country_id' => $country->id, 'name' => '旧名字']);

        $this->actingAs($admin)->patch("/admin/projects/{$project->id}", ['name' => '新名字'])
            ->assertRedirect();

        $this->assertSame('新名字', $project->fresh()->name);
    }
}
