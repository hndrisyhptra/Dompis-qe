<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qe_boqs', function (Blueprint $table) {
            $table->id('id_boq');
            $table->foreignId('qe_lop_id')->constrained('qe_lops', 'id_qe_lops')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages', 'id_package')->nullOnDelete();
            $table->string('source', 30)->default('manual');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('item_count')->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users', 'id_user')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', 'id_user')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('qe_lop_id');
            $table->index(['status', 'source']);
        });

        Schema::create('qe_boq_items', function (Blueprint $table) {
            $table->id('id_boq_item');
            $table->foreignId('qe_boq_id')->constrained('qe_boqs', 'id_boq')->cascadeOnDelete();
            $table->foreignId('designator_id')->constrained('designators', 'id_designator')->restrictOnDelete();
            $table->string('designator_code');
            $table->string('item_name');
            $table->string('unit', 50);
            $table->string('type', 30);
            $table->decimal('qty', 15, 3);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('total_price', 18, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['qe_boq_id', 'type']);
            $table->index(['qe_boq_id', 'designator_id']);
        });

        Schema::create('qe_boq_histories', function (Blueprint $table) {
            $table->id('id_boq_history');
            $table->foreignId('qe_boq_id')->constrained('qe_boqs', 'id_boq')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', 'id_user')->nullOnDelete();
            $table->string('event_type', 40);
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['qe_boq_id', 'created_at']);
        });

        Schema::create('qe_import_batches', function (Blueprint $table) {
            $table->id('id_import_batch');
            $table->uuid('uuid')->unique();
            $table->string('type', 30);
            $table->string('status', 20)->default('queued');
            $table->string('disk', 50)->default('local');
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users', 'id_user')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
            $table->index(['uploaded_by', 'created_at']);
        });

        Schema::create('qe_import_rows', function (Blueprint $table) {
            $table->id('id_import_row');
            $table->foreignId('import_batch_id')->constrained('qe_import_batches', 'id_import_batch')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status', 20);
            $table->string('reference')->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['import_batch_id', 'status']);
            $table->index(['import_batch_id', 'row_number']);
        });

        // Backfill snapshot BOQ lama agar langsung muncul pada Master Data BOQ.
        DB::table('qe_lops')
            ->whereNotNull('boq_snapshot')
            ->orderBy('id_qe_lops')
            ->chunkById(100, function ($lops) {
                foreach ($lops as $lop) {
                    $snapshot = is_string($lop->boq_snapshot)
                        ? json_decode($lop->boq_snapshot, true)
                        : (array) $lop->boq_snapshot;
                    $rows = is_array($snapshot['rows'] ?? null) ? $snapshot['rows'] : [];

                    if ($rows === []) {
                        continue;
                    }

                    $boqId = DB::table('qe_boqs')->insertGetId([
                        'qe_lop_id' => $lop->id_qe_lops,
                        'package_id' => $lop->package_id,
                        'source' => 'legacy_snapshot',
                        'status' => 'ready',
                        'item_count' => 0,
                        'grand_total' => 0,
                        'created_by' => $lop->created_by,
                        'updated_by' => $lop->created_by,
                        'created_at' => $lop->created_at ?? now(),
                        'updated_at' => now(),
                    ]);

                    $inserted = 0;
                    $grandTotal = 0.0;
                    foreach ($rows as $row) {
                        $code = trim((string) ($row['designator'] ?? ''));
                        $designator = DB::table('designators')->where('code', $code)->first();
                        if ($designator === null) {
                            continue;
                        }

                        $qty = (float) ($row['vol'] ?? 0);
                        $price = (float) ($row['harga'] ?? 0);
                        $total = (float) ($row['total'] ?? ($qty * $price));
                        DB::table('qe_boq_items')->insert([
                            'qe_boq_id' => $boqId,
                            'designator_id' => $designator->id_designator,
                            'designator_code' => $code,
                            'item_name' => (string) ($row['uraian'] ?? $designator->item_name),
                            'unit' => (string) ($row['satuan'] ?? $designator->unit),
                            'type' => strtoupper((string) ($row['type'] ?? 'MATERIAL')),
                            'qty' => $qty,
                            'unit_price' => $price,
                            'total_price' => $total,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $inserted++;
                        $grandTotal += $total;
                    }

                    DB::table('qe_boqs')->where('id_boq', $boqId)->update([
                        'item_count' => $inserted,
                        'grand_total' => $grandTotal,
                    ]);
                }
            }, 'id_qe_lops');
    }

    public function down(): void
    {
        Schema::dropIfExists('qe_import_rows');
        Schema::dropIfExists('qe_import_batches');
        Schema::dropIfExists('qe_boq_histories');
        Schema::dropIfExists('qe_boq_items');
        Schema::dropIfExists('qe_boqs');
    }
};
