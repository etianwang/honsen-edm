<?php

namespace Tests\Unit;

use App\Services\DwgConverter;
use Tests\TestCase;

class DwgConverterTest extends TestCase
{
    public function test_is_available_returns_false_when_no_path_configured(): void
    {
        config(['cad.oda_converter_path' => null]);

        $this->assertFalse((new DwgConverter)->isAvailable());
    }

    public function test_is_available_returns_false_when_configured_path_does_not_exist(): void
    {
        config(['cad.oda_converter_path' => 'C:\\nonexistent\\ODAFileConverter.exe']);

        $this->assertFalse((new DwgConverter)->isAvailable());
    }

    public function test_is_available_returns_false_when_xvfb_required_but_missing(): void
    {
        // 这台测试机上确实存在配置文件本身（用当前测试脚本当"假的转换器"），
        // 但要求了 xvfb 而这台机器（不管 Windows 还是没装 xvfb 的 Linux）大概率没有 xvfb-run
        $fakeConverter = tempnam(sys_get_temp_dir(), 'oda');
        config(['cad.oda_converter_path' => $fakeConverter, 'cad.oda_use_xvfb' => true]);

        if (PHP_OS_FAMILY !== 'Windows' && trim(shell_exec('command -v xvfb-run 2>/dev/null') ?? '') !== '') {
            $this->markTestSkipped('这台机器上装了 xvfb-run，跳过"缺失"场景的测试');
        }

        $this->assertFalse((new DwgConverter)->isAvailable());

        unlink($fakeConverter);
    }

    public function test_convert_to_dxf_returns_null_when_converter_unavailable(): void
    {
        config(['cad.oda_converter_path' => null]);

        $dwg = tempnam(sys_get_temp_dir(), 'dwg');
        file_put_contents($dwg, 'not a real dwg');

        $this->assertNull((new DwgConverter)->convertToDxf($dwg));

        unlink($dwg);
    }
}
