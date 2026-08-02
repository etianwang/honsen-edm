<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VersionDrawing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

/**
 * 下载文件名之前踩过两次坑：一次是 downloadName 参数根本没被用上（一直显示 UUID），
 * 一次是想通过 COS 对象元数据设置 Content-Disposition 时，复杂的头部值（带分号/引号）
 * 让 qcloud-cos-client 的签名算法算错，导致上传直接 403 SignatureDoesNotMatch。
 * 这几个测试锁定"下载响应头带正确原始文件名"这个行为，不经过 COS 签名。
 */
class FileDownloadFilenameTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_uploading_a_dwg_with_a_chinese_filename_does_not_error(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $response = $this->actingAs($designer)->post("/versions/{$version->id}/drawings/zh/dwg", [
            'files' => [UploadedFile::fake()->create('电力干线方案.dwg', 100)],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('version_drawings', ['version_id' => $version->id, 'original_name' => '电力干线方案.dwg']);
    }

    public function test_downloading_a_drawing_streams_with_the_original_filename_in_content_disposition(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        Storage::disk('local')->put('fake/path.dwg', 'dwg-bytes');
        $drawing = VersionDrawing::create([
            'version_id' => $version->id,
            'language' => 'zh',
            'kind' => 'dwg',
            'file_path' => 'fake/path.dwg',
            'original_name' => 'Zone transfo Maj 050326.dwg',
        ]);

        $response = $this->actingAs($designer)->get("/version-drawings/{$drawing->id}/download");

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('Zone transfo Maj 050326.dwg', $response->headers->get('Content-Disposition'));
    }
}
