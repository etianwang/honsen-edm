<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VersionDrawing;
use App\Models\VersionFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

/**
 * 删除变更时要把它名下的 DWG/PDF/说明文件从 COS（这里用 fake 本地磁盘代替）一并物理删除，
 * 不然只删数据库记录，存储空间不会释放。
 */
class VersionDeletionCleansCosTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_deleting_a_version_removes_its_files_from_storage(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);
        $admin = $this->makeUser(User::ROLE_ADMIN, [$project], teams: [$specialty->team]);

        Storage::disk('local')->put('a.dwg', 'dwg content');
        Storage::disk('local')->put('a.dxf', 'dxf content');
        Storage::disk('local')->put('a.pdf', 'pdf content');
        Storage::disk('local')->put('a.docx', 'doc content');

        VersionDrawing::create([
            'version_id' => $version->id, 'language' => 'zh', 'kind' => 'dwg',
            'file_path' => 'a.dwg', 'dxf_path' => 'a.dxf',
        ]);
        VersionDrawing::create([
            'version_id' => $version->id, 'language' => 'zh', 'kind' => 'pdf', 'file_path' => 'a.pdf',
        ]);
        VersionFile::create([
            'version_id' => $version->id, 'language' => 'zh', 'doc_path' => 'a.docx',
        ]);

        $this->actingAs($admin)->delete("/versions/{$version->id}")->assertRedirect();

        $this->assertSoftDeleted($version);
        $this->assertDatabaseCount('version_drawings', 0);
        $this->assertDatabaseCount('version_files', 0);

        Storage::disk('local')->assertMissing('a.dwg');
        Storage::disk('local')->assertMissing('a.dxf');
        Storage::disk('local')->assertMissing('a.pdf');
        Storage::disk('local')->assertMissing('a.docx');
    }
}
