<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('simplify_egg_updates') || Schema::hasColumn('simplify_egg_updates', 'link_failures')) {
            return;
        }

        // An update URL that fails on checks in a row: how often, since when and why.
        Schema::table('simplify_egg_updates', function (Blueprint $table) {
            $table->unsignedSmallInteger('link_failures')->default(0);
            $table->timestamp('link_failed_at')->nullable();
            $table->string('link_error', 64)->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('simplify_egg_updates', 'link_failures')) {
            Schema::table('simplify_egg_updates', function (Blueprint $table) {
                $table->dropColumn(['link_failures', 'link_failed_at', 'link_error']);
            });
        }
    }
};
