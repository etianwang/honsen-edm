<?php

namespace Tests\Unit;

use App\Models\Country;
use App\Models\Project;
use App\Models\Specialty;
use App\Models\Subcategory;
use App\Models\Team;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

/**
 * Country/Project/Team/Specialty/Subcategory/Version 这条链上的外键都设了
 * cascadeOnDelete()，但这些模型都是软删除。今天没有任何代码路径调用 forceDelete()，
 * 但万一以后有（比如"清空回收站"功能，或者谁在 tinker 里手滑），数据库会在 SQL 层面
 * 直接级联硬删所有子记录，完全绕过 VersionController::purgeFiles() 的 COS 清理，
 * 导致云存储文件永久孤儿。这里锁定：forceDelete() 必须直接拒绝，普通的软删除
 * （delete()）必须完全不受影响。
 */
class GuardsAgainstForceDeleteTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    public function test_force_deleting_a_country_throws(): void
    {
        $country = \App\Models\Country::create(['name' => '测试国家']);

        $this->expectException(\RuntimeException::class);
        $country->forceDelete();
    }

    public function test_force_deleting_a_project_throws(): void
    {
        $project = $this->makeProject();

        $this->expectException(\RuntimeException::class);
        $project->forceDelete();
    }

    public function test_force_deleting_a_team_throws(): void
    {
        $team = $this->makeSpecialty()->team;

        $this->expectException(\RuntimeException::class);
        $team->forceDelete();
    }

    public function test_force_deleting_a_specialty_throws(): void
    {
        $specialty = $this->makeSpecialty();

        $this->expectException(\RuntimeException::class);
        $specialty->forceDelete();
    }

    public function test_force_deleting_a_subcategory_throws(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);

        $this->expectException(\RuntimeException::class);
        $subcategory->forceDelete();
    }

    public function test_force_deleting_a_version_throws(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);

        $this->expectException(\RuntimeException::class);
        $version->forceDelete();
    }

    /**
     * 防御性拦截只挡 forceDelete()，正常的软删除（delete()）不应该受任何影响——
     * 这是回归测试，防止改坏了普通删除流程。
     */
    public function test_normal_soft_delete_is_unaffected_by_the_force_delete_guard(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);

        $version->delete();
        $subcategory->delete();
        $specialty->delete();
        $specialty->team->delete();
        $project->delete();

        $this->assertSoftDeleted($version);
        $this->assertSoftDeleted($subcategory);
        $this->assertSoftDeleted($specialty);
        $this->assertSoftDeleted($project);
    }
}
