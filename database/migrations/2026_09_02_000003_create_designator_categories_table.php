<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master kategori designator (ODP/ODC/Kabel/...), menggantikan text
     * bebas. Lookup table -> seed inline. Audit created_by/updated_by +
     * soft delete.
     */
    public function up(): void
    {
        Schema::create('designator_categories', function (Blueprint $table) {
            $table->id('id_designator_category');

            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->foreignId('created_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();
        $rows = [
            ['code' => 'ODP', 'name' => 'ODP'],
            ['code' => 'ODC', 'name' => 'ODC'],
            ['code' => 'KABEL', 'name' => 'Kabel'],
            ['code' => 'MATERIAL', 'name' => 'Material'],
            ['code' => 'JASA', 'name' => 'Jasa'],
            ['code' => 'TESTING', 'name' => 'Testing'],
            ['code' => 'DISMANTLE', 'name' => 'Dismantle'],
        ];

        DB::table('designator_categories')->insert(array_map(fn ($r) => [
            ...$r, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], $rows));
    }

    public function down(): void
    {
        Schema::dropIfExists('designator_categories');
    }
};
