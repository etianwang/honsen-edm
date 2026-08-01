<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 一行 = 一份 DWG 原图或一份 PDF 图纸（每次变更可能不止一份，比如每层一张），
        // 可以随时给某个语言追加新文件、单独删除某一份，互不影响；DWG 才有 dxf 转换相关字段
        Schema::create('version_drawings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('versions')->cascadeOnDelete();
            $table->enum('language', ['zh', 'fr', 'en']);
            $table->enum('kind', ['dwg', 'pdf']);
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('original_name')->nullable();
            $table->string('dxf_path')->nullable();
            // null=不适用（pdf）、pending=转换中、ready=已生成、failed=转换失败或当前环境不支持
            $table->string('dxf_status')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 把 version_files 里现有的 dwg 数据原样搬过去，一条语言记录变成一条 kind=dwg 的附件记录
        DB::table('version_files')->whereNotNull('dwg_path')->orderBy('id')->get()->each(function ($row) {
            DB::table('version_drawings')->insert([
                'version_id' => $row->version_id,
                'language' => $row->language,
                'kind' => 'dwg',
                'file_path' => $row->dwg_path,
                'file_size' => $row->dwg_size,
                'dxf_path' => $row->dxf_path,
                'dxf_status' => $row->dxf_status,
                'uploaded_by' => $row->uploaded_by,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        });

        Schema::table('version_files', function (Blueprint $table) {
            $table->dropColumn(['dwg_path', 'dwg_size', 'dxf_path', 'dxf_status']);
        });
    }

    public function down(): void
    {
        Schema::table('version_files', function (Blueprint $table) {
            $table->string('dwg_path')->nullable();
            $table->unsignedBigInteger('dwg_size')->nullable();
            $table->string('dxf_path')->nullable();
            $table->string('dxf_status')->nullable();
        });

        DB::table('version_drawings')->where('kind', 'dwg')->orderBy('id')->get()->each(function ($row) {
            DB::table('version_files')
                ->where('version_id', $row->version_id)
                ->where('language', $row->language)
                ->update([
                    'dwg_path' => $row->file_path,
                    'dwg_size' => $row->file_size,
                    'dxf_path' => $row->dxf_path,
                    'dxf_status' => $row->dxf_status,
                ]);
        });

        Schema::dropIfExists('version_drawings');
    }
};
