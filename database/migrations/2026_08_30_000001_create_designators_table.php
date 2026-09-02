<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master katalog item/material untuk modul Master Designator. Beda dari
     * roles/permissions (lookup tetap) - designators tumbuh & dikelola lewat
     * UI, jadi pakai custom PK (id_designator) sama seperti branches
     * (id_branch), bukan id polos. Audit trail pakai created_by/updated_by +
     * soft delete (bukan tabel histori terpisah seperti qe_lop_histories) -
     * ini master data katalog, bukan entitas dengan state machine.
     */
    public function up(): void
    {
        Schema::create('designators', function (Blueprint $table) {
            $table->id('id_designator');

            $table->string('code')->unique();
            $table->string('item_name');
            $table->string('unit');
            $table->enum('type', ['material', 'jasa']);

            $table->foreignId('created_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designators');
    }
};
