<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('honeypot_plus_attacks', function (Blueprint $table) {
            $table->id();
            $table->string('ip');
            $table->string('honeypot_rule');
            $table->string('user_agent')->nullable();
            $table->string('http_method');
            $table->string('path_requested');
            $table->timestamp('reported_at')->nullable();
            $table->string('cf_rule_id')->nullable();
            $table->timestamp('expiration_at')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->boolean('already_reported')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('ip');
            $table->index('is_blocked');
            $table->index('expiration_at');
            $table->index('cf_rule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('honeypot_plus_attacks');
    }
};
