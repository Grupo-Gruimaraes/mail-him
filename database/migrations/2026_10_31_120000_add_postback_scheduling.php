<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->decimal('dispatch_credit', 10, 4)->default(0);
            // Cópia dos leads em JSON: duplicava a tabela leads e passava do limite do MySQL em listas grandes
            $table->dropColumn('leads');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('postback_status', 10)->default('pending');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->index(['campaign_id', 'postback_status']);
            $table->index(['campaign_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['campaign_id', 'postback_status']);
            $table->dropIndex(['campaign_id', 'sent_at']);
            $table->dropColumn(['postback_status', 'queued_at', 'sent_at']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at', 'dispatch_credit']);
            $table->json('leads')->nullable();
        });
    }
};
