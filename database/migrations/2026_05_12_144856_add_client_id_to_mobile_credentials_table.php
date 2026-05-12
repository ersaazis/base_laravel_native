<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_credentials', function (Blueprint $table): void {
            $table->string('client_id')->nullable()->after('id')->index();
        });

        DB::table('mobile_credentials')
            ->whereNull('client_id')
            ->update(['client_id' => 'default']);
    }

    public function down(): void
    {
        Schema::table('mobile_credentials', function (Blueprint $table): void {
            $table->dropIndex(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};
