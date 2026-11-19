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
        Schema::create('appointments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            // History is kept: professionals and services with appointments
            // cannot be hard-deleted (services are soft-deleted).
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // ends_at includes the service buffer: it is the end of the time
            // blocked in the agenda (ADR 0002).
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status', 20);

            // Snapshots taken at booking time.
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('price_cents');

            $table->text('notes')->nullable();
            // SHA-256 of the customer's management token (never the token).
            $table->char('manage_token_hash', 64)->unique();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestampTz('reminder_sent_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'starts_at']);
            $table->index(['staff_profile_id', 'starts_at']);
            $table->index('customer_id');
        });

        DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_range_check CHECK (ends_at > starts_at)');
        DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_check CHECK (status IN ('pending', 'confirmed', 'completed', 'cancelled', 'no_show'))");

        // The last line of defense against double-booking (ADR 0002): two
        // active appointments of the same professional can never overlap,
        // whatever the application does. Half-open ranges allow back-to-back
        // bookings.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement(<<<'SQL'
            ALTER TABLE appointments
              ADD CONSTRAINT appointments_no_overlap
              EXCLUDE USING gist (
                staff_profile_id WITH =,
                tstzrange(starts_at, ends_at, '[)') WITH &&
              ) WHERE (status IN ('pending', 'confirmed'))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
