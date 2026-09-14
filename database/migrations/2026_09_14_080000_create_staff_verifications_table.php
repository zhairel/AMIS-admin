<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_verifications')) {
            Schema::create('staff_verifications', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 100)->unique()->index();
                $table->string('full_name', 255);
                $table->string('academic_title', 255)->nullable();
                $table->string('position', 255);
                $table->string('department', 255)->nullable();
                $table->string('official_email', 255);
                $table->string('phone_number', 50)->nullable();
                $table->text('office_address')->nullable();
                $table->string('photo_url', 500)->nullable();
                $table->string('badge_title', 100)->default('Verified AMIS Official');
                $table->string('official_website', 255)->default('https://amis.edu.ph');
                $table->string('status', 50)->default('active')->index();
                $table->text('bio_notes')->nullable();
                $table->timestamp('verified_since')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_verifications');
    }
};
