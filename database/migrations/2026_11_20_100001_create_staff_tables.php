<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->char('color', 7);
            $table->boolean('is_bookable')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'is_bookable']);
        });

        Schema::create('service_staff', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();

            $table->primary(['service_id', 'staff_profile_id']);
            $table->index('staff_profile_id');
        });

        // Weekly schedule in the tenant's wall-clock time. A day may have
        // several intervals (09:00-12:00 and 13:00-18:00).
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday')->comment('0 = Sunday ... 6 = Saturday');
            $table->time('starts_at');
            $table->time('ends_at');

            $table->index(['staff_profile_id', 'weekday']);
            $table->index('tenant_id');
        });

        DB::statement('ALTER TABLE working_hours ADD CONSTRAINT working_hours_weekday_check CHECK (weekday BETWEEN 0 AND 6)');
        DB::statement('ALTER TABLE working_hours ADD CONSTRAINT working_hours_range_check CHECK (ends_at > starts_at)');

        Schema::create('time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason', 255)->nullable();
            $table->timestampsTz();

            $table->index(['staff_profile_id', 'starts_at', 'ends_at']);
            $table->index('tenant_id');
        });

        DB::statement('ALTER TABLE time_offs ADD CONSTRAINT time_offs_range_check CHECK (ends_at > starts_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_offs');
        Schema::dropIfExists('working_hours');
        Schema::dropIfExists('service_staff');
        Schema::dropIfExists('staff_profiles');
    }
};
