<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VersionFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class DwgPreviewTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    public function test_dxf_endpoint_returns_404_when_no_dxf_is_available(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $user = $this->makeUser(User::ROLE_CONSTRUCTION, [$project], teams: [$specialty->team]);

        // 有 dwg 但没转出 dxf（比如没装 ODA File Converter），预览接口应该优雅地 404 而不是报错
        VersionFile::create(['version_id' => $version->id, 'language' => 'zh', 'dwg_path' => 'fake/a.dwg']);

        $response = $this->actingAs($user)->get("/versions/{$version->id}/files/zh/dxf");

        $response->assertNotFound();
    }

    public function test_dxf_endpoint_returns_content_when_available(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $user = $this->makeUser(User::ROLE_CONSTRUCTION, [$project], teams: [$specialty->team]);

        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('demo.dxf', '0\nSECTION\n0\nENDSEC\n0\nEOF');
        VersionFile::create(['version_id' => $version->id, 'language' => 'zh', 'dwg_path' => 'fake/a.dwg', 'dxf_path' => 'demo.dxf']);

        $response = $this->actingAs($user)->get("/versions/{$version->id}/files/zh/dxf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/dxf');
    }

    public function test_dxf_endpoint_respects_team_isolation(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty('机电', '给排水');
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        // 没有分配这个团队的权限
        $outsider = $this->makeUser(User::ROLE_CONSTRUCTION, [$project]);

        VersionFile::create(['version_id' => $version->id, 'language' => 'zh', 'dwg_path' => 'fake/a.dwg', 'dxf_path' => 'demo.dxf']);

        $response = $this->actingAs($outsider)->get("/versions/{$version->id}/files/zh/dxf");

        $response->assertForbidden();
    }
}
