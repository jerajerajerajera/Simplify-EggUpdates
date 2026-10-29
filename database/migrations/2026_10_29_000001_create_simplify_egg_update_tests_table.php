<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kept when the plugin is reinstalled or takes over an older copy.
        if (Schema::hasTable('simplify_egg_update_tests')) {
            return;
        }

        // One row per egg whose update waits for, or is in, a test on a temporary server.
        Schema::create('simplify_egg_update_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('egg_id')->unique();
            $table->string('stage', 16)->default('queued');
            $table->unsignedInteger('copy_egg_id')->nullable();
            $table->unsignedInteger('server_id')->nullable();
            $table->unsignedInteger('allocation_id')->nullable();
            $table->longText('content')->nullable();
            $table->string('format', 8)->nullable();
            $table->string('hash', 32)->nullable();
            $table->timestamp('stage_at')->nullable();
            $table->timestamps();

            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
        });

        // How the last test of an egg ended.
        Schema::table('simplify_egg_updates', function (Blueprint $table) {
            $table->boolean('test_ok')->nullable();
            $table->text('test_reason')->nullable();
            $table->timestamp('tested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('simplify_egg_updates', function (Blueprint $table) {
            $table->dropColumn(['test_ok', 'test_reason', 'tested_at']);
        });
        Schema::dropIfExists('simplify_egg_update_tests');
    }
};
