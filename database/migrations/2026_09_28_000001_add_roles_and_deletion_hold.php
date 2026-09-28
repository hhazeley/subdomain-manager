<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('is_admin')->index();
        });

        DB::table('users')->whereNull('email_verified_at')->update([
            'email_verified_at' => DB::raw('CURRENT_TIMESTAMP'),
        ]);

        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);

        Schema::table('subdomains', function (Blueprint $table) {
            $table->string('deletion_previous_status', 20)->nullable()->after('renewed_at');
            $table->timestamp('deletion_requested_at')->nullable()->after('renewed_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('subdomains', function (Blueprint $table) {
            $table->dropColumn(['deletion_previous_status', 'deletion_requested_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
