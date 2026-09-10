<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional outbox.
 *
 * A row is written in the same database transaction as the state change that
 * produced the event. The `outbox:relay` worker later publishes pending rows
 * to NATS JetStream and marks them published. This is what gives us
 * "state change and event publish happen together, or not at all" without a
 * distributed transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');

            // NATS subject, e.g. "events.reply.processed"
            $table->string('subject');
            // value for the Nats-Msg-Id header -> JetStream de-duplication
            $table->string('dedupe_key')->nullable();
            $table->jsonb('payload');

            // pending -> published | failed
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            // the relay's claim query filters on exactly this
            $table->index(['status', 'available_at']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
