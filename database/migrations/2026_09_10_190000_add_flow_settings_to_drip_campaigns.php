<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drip_campaigns', function (Blueprint $table) {
            $table->string('entry_status')->default('dormant');
            $table->boolean('stop_on_reply')->default(true);
            $table->jsonb('handoff_intents')->nullable();
            $table->jsonb('field_intents')->nullable();
            $table->jsonb('suppress_intents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('drip_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'entry_status',
                'stop_on_reply',
                'handoff_intents',
                'field_intents',
                'suppress_intents',
            ]);
        });
    }
};
