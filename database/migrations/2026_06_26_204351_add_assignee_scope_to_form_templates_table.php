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
        Schema::table('form_templates', function (Blueprint $table): void {
            $table->string('assignee_scope')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('form_templates', function (Blueprint $table): void {
            $table->dropColumn('assignee_scope');
        });
    }
};
