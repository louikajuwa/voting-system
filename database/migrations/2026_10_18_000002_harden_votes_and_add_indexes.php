<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Voting records must never vanish with a candidate: RESTRICT instead of CASCADE.
        Schema::table('votes', function (Blueprint $table) {
            $table->dropForeign(['candidate_id']);
            $table->foreign('candidate_id')->references('id')->on('candidates')->restrictOnDelete();
            $table->index(['election_id', 'candidate_id']);
            $table->index('created_at');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->index(['election_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropIndex(['election_id', 'status']);
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex(['election_id', 'candidate_id']);
            $table->dropIndex(['created_at']);
            $table->dropForeign(['candidate_id']);
            $table->foreign('candidate_id')->references('id')->on('candidates')->cascadeOnDelete();
        });
    }
};
