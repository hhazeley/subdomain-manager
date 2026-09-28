<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_subdomains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('managed_domain_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label', 63);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['managed_domain_id', 'label']);
            $table->index('label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_subdomains');
    }
};
