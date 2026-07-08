<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_forge_request_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('token_id')->nullable()->index();
            $table->string('resource_class')->nullable()->index();
            $table->string('action', 64)->nullable();
            $table->string('method', 10);
            $table->string('path', 2048);
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_forge_request_logs');
    }
};
