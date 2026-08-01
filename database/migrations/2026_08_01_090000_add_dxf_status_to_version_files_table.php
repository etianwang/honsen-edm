<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_files', function (Blueprint $table) {
            // null=不适用（没有 dwg），pending=转换排队/进行中，ready=已生成，failed=转换失败或当前环境不支持
            $table->string('dxf_status')->nullable()->after('dxf_path');
        });

        DB::table('version_files')->whereNotNull('dxf_path')->update(['dxf_status' => 'ready']);
    }

    public function down(): void
    {
        Schema::table('version_files', function (Blueprint $table) {
            $table->dropColumn('dxf_status');
        });
    }
};
