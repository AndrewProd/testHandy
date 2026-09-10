<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_teams', function (Blueprint $table) {
            $table->unsignedInteger('lead_cap')->default(20);
        });
    }

    public function down(): void
    {
        Schema::table('field_teams', function (Blueprint $table) {
            $table->dropColumn('lead_cap');
        });
    }
};
