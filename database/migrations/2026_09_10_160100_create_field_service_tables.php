<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('region');
            $table->jsonb('services');
            $table->time('work_starts_at')->default('08:00');
            $table->time('work_ends_at')->default('18:00');
            $table->unsignedInteger('daily_capacity')->default(5);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'region']);
        });

        Schema::create('field_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('field_teams')->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->default('technician');
            $table->jsonb('skills')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('field_service_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('reply_task_id')->nullable();
            $table->string('type');
            $table->string('status')->default('new');
            $table->string('source');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('requested_window')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('assigned_team_id')->nullable();
            $table->timestamps();

            $table->unique('reply_task_id');
            $table->index(['tenant_id', 'status']);
            $table->index('client_id');
        });

        Schema::create('field_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('field_service_request_id')
                ->constrained('field_service_requests')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('team_id');
            $table->timestamp('scheduled_at');
            $table->string('status')->default('scheduled');
            $table->decimal('distance_km', 8, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'scheduled_at']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_visits');
        Schema::dropIfExists('field_service_requests');
        Schema::dropIfExists('field_team_members');
        Schema::dropIfExists('field_teams');
    }
};
