<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mobile_credentials', function (Blueprint $table): void {
            $table->string('locale', 8)->nullable()->after('access');
            $table->text('site_config')->nullable()->after('locale');
            $table->timestamp('site_config_fetched_at')->nullable()->after('site_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mobile_credentials', function (Blueprint $table): void {
            $table->dropColumn(['locale', 'site_config', 'site_config_fetched_at']);
        });
    }
};
