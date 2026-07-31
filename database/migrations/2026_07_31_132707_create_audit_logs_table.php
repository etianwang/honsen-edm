<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 操作日志：重命名 / 删除等敏感操作留痕，便于追溯误删（配合软删除使用）
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20); // create / rename / delete / restore / replace_file 等
            $table->string('entity_type', 20); // team / specialty / subcategory / version / version_file
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
