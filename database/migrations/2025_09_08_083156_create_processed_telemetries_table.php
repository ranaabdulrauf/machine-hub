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
        Schema::create('processed_telemetries', function (Blueprint $table) {
            $table->id();
            $table->string('supplier', 50);
            $table->string('event_id');
            $table->string('type')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->timestamp('forwarded_at')->nullable();

            $table->unique(['supplier', 'event_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_telemetries');
    }
};
