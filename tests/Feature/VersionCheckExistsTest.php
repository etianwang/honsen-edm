<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

/**
 * "发布变更"上传失败后，前端在用户点"重试"之前会先查这个接口，确认上一次是不是
 * 其实已经成功了（比如网络中断导致响应没送达浏览器，但服务器早就提交完事务了），
 * 避免盲目重试凭空多出一条重复的版本记录。
 */
class VersionCheckExistsTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    public function test_reports_exists_true_when_a_version_with_that_version_no_was_already_published(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        Version::create([
            'subcategory_id' => $subcategory->id,
            'version_no' => 'V1',
            'description' => '已发布的版本',
            'publish_date' => now()->toDateString(),
            'uploaded_by' => $designer->id,
        ]);

        $response = $this->actingAs($designer)
            ->getJson("/subcategories/{$subcategory->id}/versions/check?version_no=V1");

        $response->assertOk()->assertJson(['exists' => true]);
    }

    public function test_reports_exists_false_when_no_version_with_that_version_no_exists(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $response = $this->actingAs($designer)
            ->getJson("/subcategories/{$subcategory->id}/versions/check?version_no=V-NEVER-PUBLISHED");

        $response->assertOk()->assertJson(['exists' => false]);
    }

    public function test_a_version_no_that_exists_in_a_different_subcategory_does_not_count(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategoryA = $this->makeSubcategory($project, $specialty);
        $subcategoryB = $this->makeSubcategory($project, $specialty);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        Version::create([
            'subcategory_id' => $subcategoryA->id,
            'version_no' => 'V1',
            'description' => '细分类 A 下的版本',
            'publish_date' => now()->toDateString(),
            'uploaded_by' => $designer->id,
        ]);

        $response = $this->actingAs($designer)
            ->getJson("/subcategories/{$subcategoryB->id}/versions/check?version_no=V1");

        $response->assertOk()->assertJson(['exists' => false]);
    }

    public function test_construction_role_cannot_check_since_they_cannot_publish_versions_either(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $construction = $this->makeUser(User::ROLE_CONSTRUCTION, [$project], teams: [$specialty->team]);

        $response = $this->actingAs($construction)
            ->getJson("/subcategories/{$subcategory->id}/versions/check?version_no=V1");

        $response->assertForbidden();
    }
}
