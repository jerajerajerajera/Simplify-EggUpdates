<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('simplify_egg_updates') || Schema::hasColumn('simplify_egg_updates', 'test_log')) {
            return;
        }

        // The last lines of the test server's log when a test failed, so Explain can explain them.
        Schema::table('simplify_egg_updates', function (Blueprint $table) {
            $table->text('test_log')->nullable();
        });
    }

    /**
     * Uninstalling keeps the data: Pelican rolls back a plugin's migrations when it is uninstalled, and the settings,
     * the kept previous versions (Put back) and the test results must still be there when it is installed again.
     */
    public function down(): void {}
};
