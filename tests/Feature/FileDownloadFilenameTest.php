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

    /**
     * 之前踩的坑：只在"追加/替换"这条支线（VersionFileController::store）记了 original_name，
     * 漏了"发布变更"这条主干流程（VersionController::store）——用户平时发布新版本走的
     * 正是这条主干路径，所以线上还是看不到正确文件名。这次把 zh/fr/en 三种语言、
     * DWG/PDF/说明文件三种类型在主干发布流程里全部覆盖到。
     */
    public function test_publishing_a_version_stores_original_filenames_for_every_language_and_file_kind(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $response = $this->actingAs($designer)->post("/subcategories/{$subcategory->id}/versions", [
            'version_no' => 'V1',
            'publish_date' => now()->toDateString(),
            'description' => '三语言全量测试',
            'zh_dwg' => [UploadedFile::fake()->create('中文图纸.dwg', 100)],
            'zh_pdf' => [UploadedFile::fake()->create('中文图纸.pdf', 100)],
            'zh_doc' => UploadedFile::fake()->create('中文说明.docx', 50),
            'fr_dwg' => [UploadedFile::fake()->create('Plan francais.dwg', 100)],
            'fr_pdf' => [UploadedFile::fake()->create('Plan francais.pdf', 100)],
            'fr_doc' => UploadedFile::fake()->create('Note francaise.docx', 50),
            'en_dwg' => [UploadedFile::fake()->create('English drawing.dwg', 100)],
            'en_pdf' => [UploadedFile::fake()->create('English drawing.pdf', 100)],
            'en_doc' => UploadedFile::fake()->create('English note.docx', 50),
        ]);

        $response->assertRedirect();

        foreach (['zh' => '中文图纸', 'fr' => 'Plan francais', 'en' => 'English drawing'] as $lang => $stem) {
            $this->assertDatabaseHas('version_drawings', ['language' => $lang, 'kind' => 'dwg', 'original_name' => "{$stem}.dwg"]);
            $this->assertDatabaseHas('version_drawings', ['language' => $lang, 'kind' => 'pdf', 'original_name' => "{$stem}.pdf"]);
        }

        $this->assertDatabaseHas('version_files', ['language' => 'zh', 'original_name' => '中文说明.docx']);
        $this->assertDatabaseHas('version_files', ['language' => 'fr', 'original_name' => 'Note francaise.docx']);
        $this->assertDatabaseHas('version_files', ['language' => 'en', 'original_name' => 'English note.docx']);
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

    public function test_downloading_a_description_document_streams_with_the_original_filename(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        Storage::disk('local')->put('fake/note.docx', 'doc-bytes');
        \App\Models\VersionFile::create([
            'version_id' => $version->id,
            'language' => 'fr',
            'doc_path' => 'fake/note.docx',
            'original_name' => 'Note explicative francaise.docx',
        ]);

        $response = $this->actingAs($designer)->get("/versions/{$version->id}/files/fr/download");

        $response->assertOk();
        $this->assertStringContainsString('Note explicative francaise.docx', $response->headers->get('Content-Disposition'));
    }
}
