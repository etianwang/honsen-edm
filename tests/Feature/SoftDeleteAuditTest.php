<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class SoftDeleteAuditTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    public function test_deleting_a_subcategory_requires_the_correct_password(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], password: 'correct-password', teams: [$specialty->team]);

        $response = $this->actingAs($designer)->delete("/subcategories/{$subcategory->id}", [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertNotSoftDeleted($subcategory);
    }

    public function test_deleting_a_subcategory_soft_deletes_it_and_writes_an_audit_log(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], password: 'correct-password', teams: [$specialty->team]);

        $response = $this->actingAs($designer)->delete("/subcategories/{$subcategory->id}", [
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('project.show', $project));

        // 软删除：数据库里记录还在，只是打了 deleted_at
        $this->assertSoftDeleted($subcategory);
        $this->assertDatabaseCount('subcategories', 1);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $designer->id,
            'action' => 'delete',
            'entity_type' => 'subcategory',
            'entity_id' => $subcategory->id,
        ]);
    }

    public function test_soft_deleted_subcategory_no_longer_appears_in_the_project_tree(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $subcategory->delete();

        $tree = \App\Services\ProjectTree::build($project);
        $subIds = $tree->flatMap(fn ($team) => $team->specialties)->flatMap(fn ($spec) => $spec->subcategories)->pluck('id');

        $this->assertFalse($subIds->contains($subcategory->id));
    }
}
