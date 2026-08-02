<?php

namespace Tests\Unit;

use App\Services\CosFileService;
use Tests\TestCase;

class CosFileServiceTest extends TestCase
{
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
