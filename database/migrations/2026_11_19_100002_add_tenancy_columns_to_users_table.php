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
        Schema::table('users', function (Blueprint $table) {
            // Nullable only for platform super admins, who belong to no tenant.
            $table->foreignUlid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('phone', 32)->nullable();
            $table->string('avatar_path')->nullable();
            $table->boolean('is_super_admin')->default(false);

            $table->dropUnique(['email']);

            // NULLS NOT DISTINCT keeps super admin emails (tenant_id IS NULL) unique too.
            $table->unique(['tenant_id', 'email'])->nullsNotDistinct();
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_tenant_or_super_admin CHECK ((tenant_id IS NULL) = is_super_admin)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_tenant_or_super_admin');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['phone', 'avatar_path', 'is_super_admin']);
            $table->unique('email');
        });
    }
};
