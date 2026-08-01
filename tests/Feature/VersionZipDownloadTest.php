<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VersionDrawing;
use App\Models\VersionFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class VersionZipDownloadTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_publishing_a_version_only_requires_a_chinese_dwg_everything_else_is_optional(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $response = $this->actingAs($designer)->post("/subcategories/{$subcategory->id}/versions", [
            'version_no' => 'V1',
            'publish_date' => now()->toDateString(),
            'description' => '仅有中文 DWG，没有说明文件和 PDF',
            'zh_dwg' => [UploadedFile::fake()->create('1F.dwg', 100)],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('version_drawings', 1);
        $this->assertDatabaseCount('version_files', 0);
    }

    public function test_publishing_without_any_chinese_dwg_fails_validation(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $response = $this->actingAs($designer)->post("/subcategories/{$subcategory->id}/versions", [
            'version_no' => 'V1',
            'publish_date' => now()->toDateString(),
            'description' => '没有任何 DWG',
        ]);

        $response->assertSessionHasErrors('zh_dwg');
        $this->assertDatabaseCount('versions', 0);
    }

    public function test_language_zip_download_bundles_dwg_pdf_and_doc_together(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $user = $this->makeUser(User::ROLE_CONSTRUCTION, [$project], teams: [$specialty->team]);

        Storage::disk('local')->put('a.dwg', 'dwg content');
        Storage::disk('local')->put('a.pdf', 'pdf content');
        Storage::disk('local')->put('a.docx', 'doc content');

        VersionDrawing::create(['version_id' => $version->id, 'language' => 'zh', 'kind' => 'dwg', 'file_path' => 'a.dwg', 'original_name' => '1F.dwg']);
        VersionDrawing::create(['version_id' => $version->id, 'language' => 'zh', 'kind' => 'pdf', 'file_path' => 'a.pdf', 'original_name' => '1F.pdf']);
        VersionFile::create(['version_id' => $version->id, 'language' => 'zh', 'doc_path' => 'a.docx']);

        $response = $this->actingAs($user)->get("/versions/{$version->id}/languages/zh/zip");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
    }

    public function test_language_zip_download_respects_team_isolation(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty('机电', '给排水');
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $outsider = $this->makeUser(User::ROLE_CONSTRUCTION, [$project]);

        Storage::disk('local')->put('a.dwg', 'dwg content');
        VersionDrawing::create(['version_id' => $version->id, 'language' => 'zh', 'kind' => 'dwg', 'file_path' => 'a.dwg']);

        $response = $this->actingAs($outsider)->get("/versions/{$version->id}/languages/zh/zip");

        $response->assertForbidden();
    }
}
