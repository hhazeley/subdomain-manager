<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subdomain_id')->constrained()->cascadeOnDelete();
            $table->string('cloudflare_record_id')->nullable()->unique();
            $table->string('type', 20);
            $table->string('name', 253);
            $table->text('content')->nullable();
            $table->unsignedInteger('ttl')->default(1);
            $table->boolean('proxied')->default(false);
            $table->unsignedSmallInteger('priority')->nullable();
            $table->string('comment')->nullable();
            $table->json('data')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->index(['subdomain_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_records');
    }
};
