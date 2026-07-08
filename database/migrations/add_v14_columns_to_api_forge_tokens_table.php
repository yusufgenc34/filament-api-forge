<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_forge_tokens', function (Blueprint $table) {
            $table->string('refresh_token_hash', 64)->nullable()->unique()->after('token_prefix');
            $table->timestamp('expiry_notified_at')->nullable()->after('last_used_at');
            $table->string('tenant_id')->nullable()->index()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('api_forge_tokens', function (Blueprint $table) {
            $table->dropColumn(['refresh_token_hash', 'expiry_notified_at', 'tenant_id']);
        });
    }
};
