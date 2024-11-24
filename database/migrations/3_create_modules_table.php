<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('filament-modular-subscriptions.tables.module'), function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('class');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_persistent')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('filament-modular-subscriptions.tables.module'));
    }
};
