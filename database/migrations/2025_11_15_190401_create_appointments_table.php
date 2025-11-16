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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('creator_id')->nullable();
            
            // Unique hash for secure identification
            $table->string('unique_hash', 50)->nullable();
            
            // Appointment details
            $table->string('title');
            $table->text('description')->nullable();
            $table->datetime('appointment_date');
            $table->integer('duration_minutes')->default(30);
            $table->enum('status', ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->enum('type', ['consultation', 'follow_up', 'treatment', 'emergency', 'other'])->default('consultation');
            
            // Contact information (override)
            $table->string('patient_name')->nullable();
            $table->string('patient_phone')->nullable();
            $table->string('patient_email')->nullable();
            
            // Medical information
            $table->text('chief_complaint')->nullable();
            $table->text('notes')->nullable();
            $table->text('preparation_instructions')->nullable();
            
            // Reminder settings
            $table->boolean('send_reminder')->default(true);
            $table->integer('reminder_hours_before')->default(24);
            $table->timestamp('reminder_sent_at')->nullable();
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['appointment_date', 'status']);
            $table->index(['customer_id', 'appointment_date']);
            $table->index(['company_id', 'appointment_date']);
            $table->index('reminder_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
