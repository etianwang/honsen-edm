<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 版本文件：按语言拆分，中文必有，法语/英语可后补/替换；(version_id, language) 唯一
        Schema::create('version_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('versions')->cascadeOnDelete();
            $table->enum('language', ['zh', 'fr', 'en']);
            $table->string('dwg_path')->nullable();
            $table->unsignedBigInteger('dwg_size')->nullable();
            $table->string('doc_path')->nullable();
            $table->unsignedBigInteger('doc_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['version_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('version_files');
    }
};
