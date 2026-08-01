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

class DwgAsyncConversionTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_uploading_a_version_marks_each_dwg_pending_and_queues_conversion_without_blocking_the_response(): void
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

        $drawings = VersionDrawing::where('language', 'zh')->where('kind', VersionDrawing::KIND_DWG)->get();
        $this->assertCount(2, $drawings);
        $this->assertTrue($drawings->every(fn ($d) => $d->dxf_status === VersionDrawing::DXF_PENDING));
        $this->assertTrue($drawings->every(fn ($d) => $d->dxf_path === null));

        Queue::assertPushed(ConvertVersionDrawingDxf::class, 2);
    }

    public function test_conversion_job_marks_dxf_ready_when_the_converter_succeeds(): void
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
            'dxf_status' => VersionDrawing::DXF_PENDING,
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
        $this->assertTrue(Storage::disk('local')->exists($drawing->dxf_path));
    }

    public function test_conversion_job_marks_dxf_failed_when_the_converter_returns_nothing(): void
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
            'dxf_status' => VersionDrawing::DXF_PENDING,
        ]);

        $this->mock(DwgConverter::class, function ($mock) {
            $mock->shouldReceive('convertToDxf')->once()->andReturn(null);
        });

        (new ConvertVersionDrawingDxf($drawing->id))->handle(app(\App\Services\CosFileService::class), app(DwgConverter::class));

        $drawing->refresh();
        $this->assertSame(VersionDrawing::DXF_FAILED, $drawing->dxf_status);
        $this->assertNull($drawing->dxf_path);
    }

    public function test_pdf_attachments_are_not_queued_for_conversion(): void
    {
        Queue::fake();

        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $designer = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $this->actingAs($designer)->post("/subcategories/{$subcategory->id}/versions", [
            'version_no' => 'V1',
            'publish_date' => now()->toDateString(),
            'description' => '首版发布',
            'zh_dwg' => [UploadedFile::fake()->create('1F.dwg', 100)],
            'zh_pdf' => [UploadedFile::fake()->create('1F.pdf', 100)],
        ]);

        $pdf = VersionDrawing::where('kind', VersionDrawing::KIND_PDF)->firstOrFail();
        $this->assertNull($pdf->dxf_status);

        Queue::assertPushed(ConvertVersionDrawingDxf::class, 1);
    }
}
