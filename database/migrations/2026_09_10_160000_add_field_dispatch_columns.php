<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });

        Schema::table('reply_tasks', function (Blueprint $table) {
            $table->string('intent')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'city',
                'region',
                'postal_code',
                'latitude',
                'longitude',
            ]);
        });

        Schema::table('reply_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'intent',
                'confidence',
                'assignee_id',
                'campaign_id',
                'notes',
            ]);
        });
    }
};
