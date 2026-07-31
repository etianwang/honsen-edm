<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_files', function (Blueprint $table) {
            // DWG 上传后自动转换出的 DXF 副本，用于浏览器端交互式看图（dxf-viewer）；转换失败时为 null，退化为只能下载
            $table->string('dxf_path')->nullable()->after('dwg_size');
        });
    }

    public function down(): void
    {
        Schema::table('version_files', function (Blueprint $table) {
            $table->dropColumn('dxf_path');
        });
    }
};
