<?php

namespace Tests\Unit;

use App\Services\CosFileService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CosFileServiceTest extends TestCase
{
    /**
     * 之前 delete() 没有 try/catch：COS 失败时抛的是它自己的异常类
     * （Overtrue\CosClient\Exceptions\*），跟 Laravel FilesystemAdapter::delete() 期待
     * 捕获的 League\Flysystem\UnableToDeleteFile 对不上，会直接冒泡。如果调用方是在
     * 循环里删多个文件、后面还要做数据库清理（比如 VersionController::purgeFiles()），
     * 一次网络抖动就会导致数据库清理被跳过、状态卡在不一致。这个测试锁定"任何异常都
     * 应该被吞掉、记日志，不应该往外抛"。
     */
    public function test_delete_swallows_any_storage_exception_and_logs_instead_of_throwing(): void
    {
        Log::spy();
        Storage::shouldReceive('disk')->once()->andReturnUsing(function () {
            $disk = \Mockery::mock(Filesystem::class);
            $disk->shouldReceive('delete')->once()->andThrow(new \RuntimeException('simulated COS failure, not an UnableToDeleteFile'));

            return $disk;
        });

        $service = new CosFileService;
        $service->delete('projects/1/subcategories/1/versions/1/zh/dwg/some-uuid.dwg');

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_delete_does_nothing_when_path_is_null(): void
    {
        Storage::shouldReceive('disk')->never();

        $service = new CosFileService;
        $service->delete(null);
    }

    public function test_delete_actually_deletes_the_file_on_the_happy_path(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('fake/regression-check.dwg', 'bytes');

        $service = new CosFileService;
        $service->delete('fake/regression-check.dwg');

        Storage::disk('local')->assertMissing('fake/regression-check.dwg');
    }

    public function test_content_disposition_header_includes_ascii_fallback_and_utf8_filename(): void
    {
        $service = new CosFileService;

        $header = $service->contentDispositionHeader('attachment', '变更说明-V2.docx');

        $this->assertStringStartsWith('attachment; filename="', $header);
        $this->assertStringContainsString("filename*=UTF-8''", $header);
        $this->assertStringContainsString(rawurlencode('变更说明-V2.docx'), $header);
    }

    public function test_content_disposition_header_keeps_ascii_names_readable(): void
    {
        $service = new CosFileService;

        $header = $service->contentDispositionHeader('attachment', 'Zone transfo Maj 050326.dwg');

        $this->assertStringContainsString('filename="Zone transfo Maj 050326.dwg"', $header);
    }
}
