<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('reply_task_id');
            $table->string('sender_type');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('direction');
            $table->string('channel')->default('email');
            $table->text('body');
            $table->boolean('ai_generated')->default(false);
            $table->string('provider')->nullable();
            $table->string('external_message_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['reply_task_id', 'created_at']);
            $table->index(['tenant_id', 'reply_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
