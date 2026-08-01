<?php

namespace Tests\Feature;

use App\Models\VersionDrawing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class FixPdfInlineHeadersCommandTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    public function test_it_refuses_to_run_when_the_default_disk_is_not_cos(): void
    {
        // 测试环境默认走 local 磁盘，这个命令只该对着真正的 COS 跑
        $this->artisan('app:fix-pdf-inline-headers')->assertExitCode(1);
    }

    public function test_it_reports_nothing_to_do_when_there_are_no_pdf_drawings(): void
    {
        config(['filesystems.default' => 'cos']);

        $this->artisan('app:fix-pdf-inline-headers')->assertExitCode(0);
    }

    public function test_dry_run_does_not_touch_anything_even_when_pdf_drawings_exist(): void
    {
        config(['filesystems.default' => 'cos']);

        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $version = $this->makeVersion($subcategory);

        VersionDrawing::create([
            'version_id' => $version->id, 'language' => 'zh', 'kind' => 'pdf', 'file_path' => 'a.pdf',
        ]);

        // dry-run 不会真的去调用 COS（没配真实密钥也不会报错），只是列出会处理的数量
        $this->artisan('app:fix-pdf-inline-headers --dry-run')->assertExitCode(0);
    }
}
