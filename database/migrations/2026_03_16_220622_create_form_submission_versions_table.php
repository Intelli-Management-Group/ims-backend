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
        Schema::create('form_submission_versions', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $blueprint->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $blueprint->string('form_name');
            $blueprint->json('content');
            $blueprint->unsignedInteger('version_number');
            $blueprint->timestamps();

            $blueprint->unique(['submission_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_submission_versions');
    }
};
