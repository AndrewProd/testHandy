<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable audit record of every LLM call.
 *
 * One row per request: the exact prompt used, the prompt version, token counts,
 * cost, latency, the raw request/response payloads and the parsed structured
 * output. Powers the "AI Requests" screen (debugging, cost control, prompt
 * quality). Optionally linked to the business entity it was run for via the
 * polymorphic `subject`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();

            $table->string('provider', 50);          // openai, anthropic, ollama…
            $table->string('model', 100);            // gpt-5, claude-sonnet-5, qwen3…
            $table->string('purpose', 100);          // reply_classification, sentiment_analysis…
            $table->string('prompt_version', 50)->nullable();

            $table->text('system_prompt')->nullable();
            $table->text('user_prompt')->nullable();

            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);

            $table->decimal('temperature', 4, 2)->nullable();
            $table->unsignedInteger('max_tokens')->nullable();

            $table->jsonb('request_payload')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->jsonb('structured_output')->nullable();

            $table->string('status', 30)->default('pending');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('cost_usd', 12, 8)->nullable();

            // business entity this call was made for (App\Models\ReplyTask, …)
            $table->nullableMorphs('subject');

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['purpose', 'created_at']);
            $table->index(['model', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['prompt_version', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
