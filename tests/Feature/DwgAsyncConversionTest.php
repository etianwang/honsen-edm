<?php

namespace Tests\Feature;

use App\Jobs\ConvertVersionFileDxf;
use App\Models\User;
use App\Models\VersionFile;
use App\Services\DwgConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class DwgAsyncConversionTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_uploading_a_version_marks_dxf_pending_and_queues_conversion_without_blocking_the_response(): void
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
            'zh_dwg' => UploadedFile::fake()->create('a.dwg', 100),
            'zh_doc' => UploadedFile::fake()->create('a.pdf', 50),
        ]);

        $response->assertRedirect();

        $file = VersionFile::where('language', 'zh')->firstOrFail();
        $this->assertSame(VersionFile::DXF_PENDING, $file->dxf_status);
        $this->assertNull($file->dxf_path);

        Queue::assertPushed(ConvertVersionFileDxf::class, fn ($job) => $job->versionFileId === $file->id);
    }

    public function test_conversion_job_marks_dxf_ready_when_the_converter_succeeds(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);

        Storage::disk('local')->put('projects/1/subcategories/1/versions/1/zh/a.dwg', 'fake dwg content');
        $file = VersionFile::create([
            'version_id' => $version->id,
            'language' => 'zh',
            'dwg_path' => 'projects/1/subcategories/1/versions/1/zh/a.dwg',
            'dxf_status' => VersionFile::DXF_PENDING,
        ]);

        $tmpDxf = tempnam(sys_get_temp_dir(), 'dxf');
        file_put_contents($tmpDxf, "0\nSECTION\n0\nENDSEC\n0\nEOF");

        $this->mock(DwgConverter::class, function ($mock) use ($tmpDxf) {
            $mock->shouldReceive('convertToDxf')->once()->andReturn($tmpDxf);
        });

        (new ConvertVersionFileDxf($file->id))->handle(app(\App\Services\CosFileService::class), app(DwgConverter::class));

        $file->refresh();
        $this->assertSame(VersionFile::DXF_READY, $file->dxf_status);
        $this->assertNotNull($file->dxf_path);
        $this->assertTrue(Storage::disk('local')->exists($file->dxf_path));
    }

    public function test_conversion_job_marks_dxf_failed_when_the_converter_returns_nothing(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);

        Storage::disk('local')->put('projects/1/subcategories/1/versions/1/zh/a.dwg', 'fake dwg content');
        $file = VersionFile::create([
            'version_id' => $version->id,
            'language' => 'zh',
            'dwg_path' => 'projects/1/subcategories/1/versions/1/zh/a.dwg',
            'dxf_status' => VersionFile::DXF_PENDING,
        ]);

        $this->mock(DwgConverter::class, function ($mock) {
            $mock->shouldReceive('convertToDxf')->once()->andReturn(null);
        });

        (new ConvertVersionFileDxf($file->id))->handle(app(\App\Services\CosFileService::class), app(DwgConverter::class));

        $file->refresh();
        $this->assertSame(VersionFile::DXF_FAILED, $file->dxf_status);
        $this->assertNull($file->dxf_path);
    }
}
