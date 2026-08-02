<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 之前下载说明文件用的是 basename(doc_path)，而存储路径是 UUID 文件名，
        // 导致下载下来的文件名对不上原始文件名。这里补一个字段记住上传时的原始文件名。
        Schema::table('version_files', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('doc_path');
        });
    }

    public function down(): void
    {
        Schema::table('version_files', function (Blueprint $table) {
            $table->dropColumn('original_name');
        });
    }
};
