<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_forge_resource_settings', function (Blueprint $table) {
            $table->id();
            $table->string('resource_class')->unique()->index();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('rate_limit')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->json('disabled_methods')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_forge_resource_settings');
    }
};
