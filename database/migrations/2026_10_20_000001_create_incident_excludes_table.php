<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_excludes', function (Blueprint $table) {
            $table->id('id');
            $table->string('incident', 100)->unique()->index();
            $table->string('keterangan_exclude', 50);
            $table->foreignId('created_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_excludes');
    }
};
