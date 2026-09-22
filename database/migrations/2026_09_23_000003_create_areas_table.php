<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id('id_area');
            $table->string('code', 10)->unique()->comment('Kode Area, mis. 3');
            $table->string('name', 100)->comment('Nama Area, mis. Area 3');
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id_user')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', 'id_user')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
