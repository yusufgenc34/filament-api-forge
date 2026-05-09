<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_forge_resource_settings', function (Blueprint $table) {
            $table->json('method_settings')->nullable()->after('disabled_methods');
        });
    }

    public function down(): void
    {
        Schema::table('api_forge_resource_settings', function (Blueprint $table) {
            $table->dropColumn('method_settings');
        });
    }
};
