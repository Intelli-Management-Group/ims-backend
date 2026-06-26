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
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->string('assignee_type')->nullable()->after('current_version_id');
            $table->unsignedBigInteger('assignee_id')->nullable()->after('assignee_type');
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->dropColumn(['assignee_type', 'assignee_id']);
        });
    }
};
