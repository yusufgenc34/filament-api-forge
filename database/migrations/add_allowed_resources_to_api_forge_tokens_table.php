<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_forge_tokens', function (Blueprint $table) {
            $table->json('allowed_resources')->nullable()->after('scopes');
        });
    }

    public function down(): void
    {
        Schema::table('api_forge_tokens', function (Blueprint $table) {
            $table->dropColumn('allowed_resources');
        });
    }
};
