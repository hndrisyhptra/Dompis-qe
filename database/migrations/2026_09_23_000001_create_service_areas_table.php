<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_areas', function (Blueprint $table) {
            $table->id('id_service_area');
            $table->string('workzone', 20)->unique();
            $table->string('name', 100);
            $table->foreignId('branch_id')
                ->constrained('branches', 'id_branch')
                ->restrictOnDelete();
            $table->foreignId('region_id')
                ->nullable()
                ->constrained('regions', 'id_region')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id_user')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', 'id_user')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_active']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_areas');
    }
};
