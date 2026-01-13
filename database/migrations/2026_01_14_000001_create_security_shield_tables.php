<?php

declare(strict_types=1);

use Plugs\Database\Blueprint;
use Plugs\Database\Migration;
use Plugs\Database\Schema;

class CreateSecurityShieldTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Security Attempts Table (for Rate Limiting)
        Schema::create('security_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->index(); // IP or Email
            $table->string('type', 20)->index();   // 'ip', 'email', 'token'
            $table->string('endpoint')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes for fast lookups
            $table->index(['identifier', 'type']);
            $table->index(['created_at']);
        });

        // Security Logs Table (for Audit & Analysis)
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->index();
            $table->string('email')->nullable()->index();
            $table->string('endpoint');
            $table->float('risk_score')->default(0);
            $table->string('decision', 20); // 'allowed', 'denied', 'challenged'
            $table->text('details')->nullable(); // JSON details
            $table->timestamp('created_at')->useCurrent();
        });

        // Whitelisted IPs
        Schema::create('whitelisted_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        // Blacklisted IPs
        Schema::create('blacklisted_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->index();
            $table->string('reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ip', 'active']);
        });

        // Blocked Fingerprints
        Schema::create('blocked_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint')->unique();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_fingerprints');
        Schema::dropIfExists('blacklisted_ips');
        Schema::dropIfExists('whitelisted_ips');
        Schema::dropIfExists('security_logs');
        Schema::dropIfExists('security_attempts');
    }
}
