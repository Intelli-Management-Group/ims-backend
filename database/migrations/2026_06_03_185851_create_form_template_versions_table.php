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
        Schema::create('form_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('form_templates')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->json('json_schema');
            $table->json('ui_schema');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version_number');
            $table->timestamps();

            $table->unique(['template_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_template_versions');
    }
};
