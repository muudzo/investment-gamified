<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;



return new class extends Migration
{
    public function up()
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('stock_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(0);
            $table->decimal('average_price', 10, 2);
            $table->timestamps();

            // Ensure one entry per user per stock
            $table->unique(['user_id', 'stock_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('portfolios');
    }
};

