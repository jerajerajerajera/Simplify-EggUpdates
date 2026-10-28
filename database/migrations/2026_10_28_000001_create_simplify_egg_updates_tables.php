<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simplify_egg_updates', function (Blueprint $table) {
            $table->unsignedInteger('egg_id')->primary();
            $table->string('found_hash', 32)->nullable();
            $table->timestamp('found_at')->nullable();
            $table->boolean('held')->default(false);
            $table->string('applied_hash', 32)->nullable();
            $table->timestamps();

            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
        });

        Schema::create('simplify_egg_update_backups', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('egg_id')->index();
            $table->longText('definition');
            $table->timestamp('created_at')->nullable();

            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
        });

        Schema::create('simplify_egg_update_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simplify_egg_update_settings');
        Schema::dropIfExists('simplify_egg_update_backups');
        Schema::dropIfExists('simplify_egg_updates');
    }
};
