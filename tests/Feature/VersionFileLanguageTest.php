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

    public function test_uploading_a_doc_file_creates_exactly_one_row_per_language(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $response = $this->actingAs($designer)->post("/versions/{$version->id}/files/fr", [
            'doc' => UploadedFile::fake()->create('note.docx', 50),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('version_files', 1);
        $this->assertDatabaseHas('version_files', ['version_id' => $version->id, 'language' => 'fr']);
    }

    public function test_replacing_an_existing_doc_updates_the_same_row_instead_of_duplicating(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $this->actingAs($designer)->post("/versions/{$version->id}/files/fr", [
            'doc' => UploadedFile::fake()->create('v1.docx', 50),
        ]);
        $firstPath = VersionFile::where('version_id', $version->id)->where('language', 'fr')->first()->doc_path;

        $this->actingAs($designer)->post("/versions/{$version->id}/files/fr", [
            'doc' => UploadedFile::fake()->create('v2.docx', 80),
        ]);

        $this->assertDatabaseCount('version_files', 1);
        $file = VersionFile::where('version_id', $version->id)->where('language', 'fr')->first();
        $this->assertNotEquals($firstPath, $file->doc_path);
        $this->assertFalse(Storage::disk('local')->exists($firstPath));
        $this->assertEquals(80 * 1024, $file->doc_size);
    }

    public function test_a_doc_file_can_be_removed_for_any_language_including_chinese(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        VersionFile::create(['version_id' => $version->id, 'language' => 'zh', 'doc_path' => 'fake/zh.docx']);

        $response = $this->actingAs($designer)->delete("/versions/{$version->id}/files/zh");

        $response->assertRedirect();
        $this->assertDatabaseMissing('version_files', ['version_id' => $version->id, 'language' => 'zh']);
    }

    public function test_appending_multiple_dwg_files_creates_independent_rows(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $response = $this->actingAs($designer)->post("/versions/{$version->id}/drawings/zh/dwg", [
            'files' => [
                UploadedFile::fake()->create('1F.dwg', 100),
                UploadedFile::fake()->create('2F.dwg', 100),
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('version_drawings', 2);
    }

    public function test_deleting_the_only_remaining_chinese_dwg_is_forbidden(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $drawing = \App\Models\VersionDrawing::create([
            'version_id' => $version->id, 'language' => 'zh', 'kind' => 'dwg', 'file_path' => 'fake/zh.dwg',
        ]);

        $response = $this->actingAs($designer)->delete("/version-drawings/{$drawing->id}");

        $response->assertSessionHasErrors('drawing');
        $this->assertDatabaseHas('version_drawings', ['id' => $drawing->id]);
    }

    public function test_deleting_a_chinese_dwg_is_allowed_when_another_one_remains(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        \App\Models\VersionDrawing::create(['version_id' => $version->id, 'language' => 'zh', 'kind' => 'dwg', 'file_path' => 'fake/1.dwg']);
        $second = \App\Models\VersionDrawing::create(['version_id' => $version->id, 'language' => 'zh', 'kind' => 'dwg', 'file_path' => 'fake/2.dwg']);

        $response = $this->actingAs($designer)->delete("/version-drawings/{$second->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('version_drawings', ['id' => $second->id]);
    }

    public function test_a_non_mandatory_language_pdf_can_be_removed_by_a_designer(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $drawing = \App\Models\VersionDrawing::create([
            'version_id' => $version->id, 'language' => 'fr', 'kind' => 'pdf', 'file_path' => 'fake/fr.pdf',
        ]);

        $response = $this->actingAs($designer)->delete("/version-drawings/{$drawing->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('version_drawings', ['id' => $drawing->id]);
    }
}
