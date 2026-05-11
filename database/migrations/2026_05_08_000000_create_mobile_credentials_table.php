<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_credentials', function (Blueprint $table): void {
            $table->id();
            $table->text('plain_text_token')->nullable();
            $table->json('user')->nullable();
            $table->boolean('biometrics_enabled')->default(false);
            $table->boolean('locked')->default(false);
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_credentials');
    }
};
