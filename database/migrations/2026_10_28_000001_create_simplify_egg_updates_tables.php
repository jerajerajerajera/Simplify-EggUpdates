<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kept when the plugin is reinstalled or takes over an older copy, so the settings and backups stay.
        if (Schema::hasTable('simplify_egg_updates')) {
            return;
        }

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

    /**
     * Uninstalling keeps the data: Pelican rolls back a plugin's migrations when it is uninstalled, and the settings,
     * the kept previous versions (Put back) and the test results must still be there when it is installed again.
     */
    public function down(): void {}
};
