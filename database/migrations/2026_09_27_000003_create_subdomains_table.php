<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subdomains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('managed_domain_id')->constrained()->restrictOnDelete();
            $table->string('label', 63);
            $table->string('fqdn', 253)->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->timestamps();
            $table->unique(['managed_domain_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subdomains');
    }
};
