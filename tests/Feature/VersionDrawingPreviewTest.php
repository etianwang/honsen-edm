<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VersionDrawing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class VersionDrawingPreviewTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_pdf_preview_redirects_to_a_signed_view_url(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $user = $this->makeUser(User::ROLE_CONSTRUCTION, [$project], teams: [$specialty->team]);

        Storage::disk('local')->put('a.pdf', '%PDF-1.4 fake');
        $pdf = VersionDrawing::create(['version_id' => $version->id, 'language' => 'zh', 'kind' => 'pdf', 'file_path' => 'a.pdf']);

        $response = $this->actingAs($user)->get("/version-drawings/{$pdf->id}/preview");

        $response->assertRedirect();
    }

    public function test_dwg_files_cannot_use_the_preview_route(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $user = $this->makeUser(User::ROLE_CONSTRUCTION, [$project], teams: [$specialty->team]);

        $dwg = VersionDrawing::create(['version_id' => $version->id, 'language' => 'zh', 'kind' => 'dwg', 'file_path' => 'a.dwg']);

        $response = $this->actingAs($user)->get("/version-drawings/{$dwg->id}/preview");

        $response->assertNotFound();
    }

    public function test_preview_respects_team_isolation(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty('机电', '给排水');
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $outsider = $this->makeUser(User::ROLE_CONSTRUCTION, [$project]);

        Storage::disk('local')->put('a.pdf', '%PDF-1.4 fake');
        $pdf = VersionDrawing::create(['version_id' => $version->id, 'language' => 'zh', 'kind' => 'pdf', 'file_path' => 'a.pdf']);

        $response = $this->actingAs($outsider)->get("/version-drawings/{$pdf->id}/preview");

        $response->assertForbidden();
    }
}
