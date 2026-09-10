<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drip_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('drip_campaign_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                ->constrained('drip_campaigns')
                ->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('status');
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->string('channel');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['campaign_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drip_campaign_steps');
        Schema::dropIfExists('drip_campaigns');
    }
};
