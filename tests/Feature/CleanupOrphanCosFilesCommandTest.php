<?php

namespace Tests\Feature;

use App\Models\VersionDrawing;
use App\Models\VersionFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class CleanupOrphanCosFilesCommandTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_dry_run_lists_orphans_but_does_not_delete_anything(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);

        $referencedPath = "projects/{$project->id}/subcategories/{$subcategory->id}/versions/{$version->id}/zh/dwg/referenced.dwg";
        Storage::disk('local')->put($referencedPath, 'referenced content');
        VersionDrawing::create([
            'version_id' => $version->id, 'language' => 'zh', 'kind' => 'dwg', 'file_path' => $referencedPath,
        ]);

        $orphanPath = "projects/{$project->id}/subcategories/{$subcategory->id}/versions/{$version->id}/zh/dwg/orphan.dwg";
        Storage::disk('local')->put($orphanPath, 'orphan content');

        $this->artisan('cos:cleanup-orphans --disk=local --min-age-hours=0')->assertExitCode(0);

        Storage::disk('local')->assertExists($referencedPath);
        Storage::disk('local')->assertExists($orphanPath);
    }

    public function test_force_deletes_orphans_but_keeps_referenced_files(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);

        $referencedDwg = "projects/{$project->id}/subcategories/{$subcategory->id}/versions/{$version->id}/zh/dwg/referenced.dwg";
        Storage::disk('local')->put($referencedDwg, 'x');
        VersionDrawing::create([
            'version_id' => $version->id, 'language' => 'zh', 'kind' => 'dwg', 'file_path' => $referencedDwg,
        ]);

        // 历史遗留的 dxf 文件即便预览功能已停用，只要 dxf_path 还在库里指着它，就不该被当成孤儿删掉
        $referencedDxf = "projects/{$project->id}/subcategories/{$subcategory->id}/versions/{$version->id}/zh/dwg/referenced.dxf";
        Storage::disk('local')->put($referencedDxf, 'x');
        VersionDrawing::create([
            'version_id' => $version->id, 'language' => 'zh', 'kind' => 'dwg', 'file_path' => 'placeholder.dwg', 'dxf_path' => $referencedDxf,
        ]);

        $referencedDoc = "projects/{$project->id}/subcategories/{$subcategory->id}/versions/{$version->id}/zh/referenced-doc.docx";
        Storage::disk('local')->put($referencedDoc, 'x');
        VersionFile::create([
            'version_id' => $version->id, 'language' => 'zh', 'doc_path' => $referencedDoc,
        ]);

        $orphanPath = "projects/{$project->id}/subcategories/{$subcategory->id}/versions/{$version->id}/zh/dwg/orphan.dwg";
        Storage::disk('local')->put($orphanPath, 'orphan content');

        $this->artisan('cos:cleanup-orphans --disk=local --min-age-hours=0 --force')->assertExitCode(0);

        Storage::disk('local')->assertMissing($orphanPath);
        Storage::disk('local')->assertExists($referencedDwg);
        Storage::disk('local')->assertExists($referencedDxf);
        Storage::disk('local')->assertExists($referencedDoc);
    }

    public function test_recently_modified_orphans_are_skipped_by_default_min_age(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);

        // 刚刚才产生的孤儿文件，很可能是正在进行中的上传（比如多文件发布时前面几个刚
        // PUT 成功、后面还没提交完事务），默认的 24 小时安全窗口应该先跳过它，不删
        $orphanPath = "projects/{$project->id}/subcategories/{$subcategory->id}/versions/{$version->id}/zh/dwg/fresh-orphan.dwg";
        Storage::disk('local')->put($orphanPath, 'x');

        $this->artisan('cos:cleanup-orphans --disk=local --force')->assertExitCode(0);

        Storage::disk('local')->assertExists($orphanPath);
    }

    public function test_no_orphans_reports_success_without_a_progress_bar(): void
    {
        $this->artisan('cos:cleanup-orphans --disk=local --force')->assertExitCode(0);
    }
}
