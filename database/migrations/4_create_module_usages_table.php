<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('module_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->integer('usage');
            $table->decimal('pricing', 10, 2)->default(0);
            $table->timestamp('calculated_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('module_usages');
    }
};
