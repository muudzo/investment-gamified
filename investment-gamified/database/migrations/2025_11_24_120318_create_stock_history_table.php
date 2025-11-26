<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->decimal('open_price', 10, 2);
            $table->decimal('high_price', 10, 2);
            $table->decimal('low_price', 10, 2);
            $table->decimal('close_price', 10, 2);
            $table->timestamps();

            $table->unique(['stock_id', 'date']);
            $table->index(['stock_id', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_history');
    }
};
