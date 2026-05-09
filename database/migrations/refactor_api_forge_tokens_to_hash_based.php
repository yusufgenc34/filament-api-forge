<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing Sanctum-bridged tokens are invalid after this change — clear them
        DB::table('api_forge_tokens')->truncate();

        Schema::table('api_forge_tokens', function (Blueprint $table) {
            // Remove Sanctum bridge columns
            if (Schema::hasColumn('api_forge_tokens', 'sanctum_token_id')) {
                $table->dropIndex(['sanctum_token_id']);
                $table->dropColumn('sanctum_token_id');
            }

            if (Schema::hasColumn('api_forge_tokens', 'token_preview')) {
                $table->dropColumn('token_preview');
            }

            // Hash-based auth columns
            $table->string('token_hash', 64)->unique()->after('name');
            $table->string('token_prefix', 16)->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('api_forge_tokens', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
            $table->dropColumn(['token_hash', 'token_prefix']);

            $table->unsignedBigInteger('sanctum_token_id')->nullable()->after('name');
            $table->string('token_preview', 20)->nullable()->after('sanctum_token_id');
            $table->index('sanctum_token_id');
        });
    }
};
