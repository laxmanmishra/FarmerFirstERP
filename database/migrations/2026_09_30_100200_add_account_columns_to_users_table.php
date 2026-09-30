<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account security state for SRS v6.1 §2: activation, lockout, forced
 * password change and last-login tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile', 15)->nullable()->unique()->after('email');
            $table->boolean('is_active')->default(true)->after('password');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            $table->unsignedSmallInteger('failed_login_count')->default(0)->after('password_changed_at');
            $table->timestamp('locked_until')->nullable()->after('failed_login_count');
            $table->timestamp('last_login_at')->nullable()->after('locked_until');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->foreignId('current_branch_id')->nullable()->after('last_login_ip')->constrained('branches')->nullOnDelete();
            $table->userstamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_branch_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropUnique(['mobile']);
            $table->dropColumn([
                'mobile', 'is_active', 'must_change_password', 'password_changed_at',
                'failed_login_count', 'locked_until', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
