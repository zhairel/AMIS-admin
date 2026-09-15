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
        if (! Schema::hasTable('roster_locks')) {
            Schema::create('roster_locks', function (Blueprint $table) {
                $table->id();
                $table->enum('roster_type', ['f2f', 'section'])->index();
                $table->string('roster_identifier')->index(); // Grade level string or Section ID
                $table->string('school_year', 20)->default('2026-2027')->index();
                $table->enum('status', ['draft', 'locked'])->default('draft')->index();
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('unlocked_at')->nullable();
                $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('unlock_reason')->nullable();
                $table->timestamps();

                $table->unique(['roster_type', 'roster_identifier', 'school_year'], 'roster_lock_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_locks');
    }
};
