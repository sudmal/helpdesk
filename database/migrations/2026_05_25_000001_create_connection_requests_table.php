<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connection_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('address_string');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'scheduled', 'rejected', 'closed'])->default('pending');
            $table->dateTime('scheduled_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('act_number')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('connection_request_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('material_name');
            $table->string('material_code')->nullable();
            $table->string('material_unit')->nullable();
            $table->decimal('price_at_time', 10, 2)->nullable();
            $table->decimal('quantity', 10, 3);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connection_request_materials');
        Schema::dropIfExists('connection_requests');
    }
};
