<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VersionFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class VersionFileLanguageTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_uploading_a_new_language_creates_exactly_one_row_per_language(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $response = $this->actingAs($designer)->post("/versions/{$version->id}/files/fr", [
            'dwg' => UploadedFile::fake()->create('drawing.dwg', 100),
            'doc' => UploadedFile::fake()->create('note.docx', 50),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('version_files', 1);
        $this->assertDatabaseHas('version_files', ['version_id' => $version->id, 'language' => 'fr']);
    }

    public function test_replacing_an_existing_language_updates_the_same_row_instead_of_duplicating(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $this->actingAs($designer)->post("/versions/{$version->id}/files/fr", [
            'dwg' => UploadedFile::fake()->create('v1.dwg', 100),
        ]);
        $firstPath = VersionFile::where('version_id', $version->id)->where('language', 'fr')->first()->dwg_path;

        $this->actingAs($designer)->post("/versions/{$version->id}/files/fr", [
            'dwg' => UploadedFile::fake()->create('v2.dwg', 200),
        ]);

        $this->assertDatabaseCount('version_files', 1);
        $file = VersionFile::where('version_id', $version->id)->where('language', 'fr')->first();
        // 替换后是新的存储路径（旧文件已被删除），且大小对应新文件
        $this->assertNotEquals($firstPath, $file->dwg_path);
        $this->assertFalse(Storage::disk('local')->exists($firstPath));
        $this->assertEquals(200 * 1024, $file->dwg_size);
    }

    public function test_the_mandatory_chinese_file_cannot_be_removed(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project]);

        VersionFile::create([
            'version_id' => $version->id,
            'language' => 'zh',
            'dwg_path' => 'fake/zh.dwg',
        ]);

        $response = $this->actingAs($designer)->delete("/versions/{$version->id}/files/zh");

        $response->assertForbidden();
        $this->assertDatabaseHas('version_files', ['version_id' => $version->id, 'language' => 'zh']);
    }

    public function test_a_non_mandatory_language_can_be_removed_by_a_designer(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        VersionFile::create([
            'version_id' => $version->id,
            'language' => 'fr',
            'dwg_path' => 'fake/fr.dwg',
        ]);

        $response = $this->actingAs($designer)->delete("/versions/{$version->id}/files/fr");

        $response->assertRedirect();
        $this->assertDatabaseMissing('version_files', ['version_id' => $version->id, 'language' => 'fr']);
    }
}
