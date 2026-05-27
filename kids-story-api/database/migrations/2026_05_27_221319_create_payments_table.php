<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | USER
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | PAYMENT DETAILS
            |--------------------------------------------------------------------------
            */

            $table->string('payment_id')
                ->nullable();

            $table->string('transaction_id')
                ->nullable();

            $table->decimal(
                'amount',
                10,
                2
            );

            $table->string('currency')
                ->default('GBP');

            /*
            |--------------------------------------------------------------------------
            | PAYMENT METHOD
            |--------------------------------------------------------------------------
            */

            $table->enum('payment_method', [

                'stripe',
                'paypal',
                'apple_pay',
                'google_pay',

            ]);

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [

                'pending',
                'paid',
                'failed',
                'refunded',

            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | SUBSCRIPTION
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_subscription')
                ->default(false);

            $table->timestamp('start_date')
                ->nullable();

            $table->timestamp('end_date')
                ->nullable();

        

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};