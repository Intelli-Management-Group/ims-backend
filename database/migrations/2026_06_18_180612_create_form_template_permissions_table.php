<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_template_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_template_id')->constrained()->cascadeOnDelete();
            $table->string('action'); // FormPermissionAction enum value: view|create|edit
            $table->string('permissible_type'); // App\Models\Role | Department | Team
            $table->unsignedBigInteger('permissible_id');
            $table->timestamps();

            // Prevent duplicate grants for the same subject+action+template
            $table->unique(
                ['form_template_id', 'action', 'permissible_type', 'permissible_id'],
                'ftp_unique_grant'
            );
            // Fast reverse lookup: "which templates can this role/dept/team access?"
            $table->index(['permissible_type', 'permissible_id'], 'ftp_permissible_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_template_permissions');
    }
};
