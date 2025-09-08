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
        Schema::create('supplier_fetch_logs', function (Blueprint $table) {
            $table->id();
            $table->string('supplier', 50);
            $table->string('endpoint', 50); // e.g. "consumptions", "events"
            $table->string('status', 50); // e.g. "consumptions", "events"
            $table->integer('page')->nullable();
            $table->integer('count')->nullable();
            $table->timestamp('last_fetched_at')->nullable();

            $table->unique(['supplier', 'endpoint']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_fetch_logs');
    }
};
