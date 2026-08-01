<?php

namespace Tests\Feature;

use App\Jobs\ConvertVersionDrawingDxf;
use App\Models\User;
use App\Models\VersionDrawing;
use App\Services\DwgConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

/**
 * DWG→DXF 交互式预览功能已停用（图纸预览改走 PDF），上传不再触发这个任务。
 * ConvertVersionDrawingDxf / DwgConverter 代码保留但不再被调用，这里只验证：
 * 1) 上传不会再自动排队转换任务；2) 手动调用这个任务本身仍然可以正常工作（万一以后要恢复）。
 */
class DwgAsyncConversionTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_uploading_dwg_files_no_longer_queues_any_conversion_job(): void
    {
        Queue::fake();

        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $response = $this->actingAs($designer)->post("/subcategories/{$subcategory->id}/versions", [
            'version_no' => 'V1',
            'publish_date' => now()->toDateString(),
            'description' => '首版发布',
            'zh_dwg' => [
                UploadedFile::fake()->create('1F.dwg', 100),
                UploadedFile::fake()->create('2F.dwg', 100),
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('version_drawings', 2);

        Queue::assertNothingPushed();
    }

    public function test_the_conversion_job_still_works_when_invoked_manually(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);

        Storage::disk('local')->put('projects/1/subcategories/1/versions/1/zh/dwg/a.dwg', 'fake dwg content');
        $drawing = VersionDrawing::create([
            'version_id' => $version->id,
            'language' => 'zh',
            'kind' => VersionDrawing::KIND_DWG,
            'file_path' => 'projects/1/subcategories/1/versions/1/zh/dwg/a.dwg',
        ]);

        $tmpDxf = tempnam(sys_get_temp_dir(), 'dxf');
        file_put_contents($tmpDxf, "0\nSECTION\n0\nENDSEC\n0\nEOF");

        $this->mock(DwgConverter::class, function ($mock) use ($tmpDxf) {
            $mock->shouldReceive('convertToDxf')->once()->andReturn($tmpDxf);
        });

        (new ConvertVersionDrawingDxf($drawing->id))->handle(app(\App\Services\CosFileService::class), app(DwgConverter::class));

        $drawing->refresh();
        $this->assertSame(VersionDrawing::DXF_READY, $drawing->dxf_status);
        $this->assertNotNull($drawing->dxf_path);
    }
}
