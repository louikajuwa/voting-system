<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('photo')->nullable();      // storage path, served via /storage
            $table->string('position')->nullable();
            $table->string('party')->nullable();
            $table->text('bio')->nullable();
            $table->text('manifesto')->nullable();
            $table->string('status')->default('approved'); // approved | pending | withdrawn
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
