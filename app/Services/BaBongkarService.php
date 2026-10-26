<?php

namespace App\Services;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceStatus;
use App\Enums\LopStatus;
use App\Models\QeLop;
use App\Models\User;
use App\Notifications\TechnicianActivityNotification;
use App\Support\BaBongkarRules;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BaBongkarService
{
    public function __construct(private readonly EvidenceService $evidence, private readonly LopService $lops, private readonly BaBongkarPdfService $pdf) {}

    public function save(QeLop $lop, User $actor, array $input, array $photos, ?array $signature): void
    {
        if (! Schema::hasColumn('qe_lops', 'ba_bongkar_data')) {
            throw ValidationException::withMessages(['workflow' => 'Migration BA Bongkar belum dijalankan. Hubungi administrator.']);
        }
        $this->transaction(function (array &$written) use ($lop, $actor, $input, $photos, $signature) {
            $lop = QeLop::query()->lockForUpdate()->findOrFail($lop->getKey());
            Gate::forUser($actor)->authorize('editBaBongkar', $lop);
            $old = $lop->ba_bongkar_data ?? [];
            $this->assertRevision($old['revision'] ?? null, $input['revision'] ?? null);
            $state = app(TechnicianWorkflowService::class)->state($lop);
            if (! $state['step2Complete'] || ! $state['step3Complete'] || ! $state['step4Complete'] || ! $state['step5Complete']) {
                throw ValidationException::withMessages(['workflow' => 'Lengkapi lima tahap pekerjaan sebelum mengisi BA Bongkar.']);
            }
            $required = (bool) $input['required'];
            if (! $required && (! empty($old['submitted_at']) || ! empty($old['ever_submitted'])) && $lop->ba_bongkar_required === true) {
                throw ValidationException::withMessages(['required' => 'BA yang sudah diajukan wajib diperbaiki; pilihan Tidak tidak dapat melewati review Admin.']);
            }
            $revision = (string) Str::uuid();
            $oldPhotos = $lop->evidences->where('category', EvidenceCategory::BA_BONGKAR_PHOTO)->keyBy('id_evidence');
            $keep = collect($input['keep_photo_ids'] ?? []);
            if ($keep->diff($oldPhotos->keys())->isNotEmpty() || $keep->count() + count($photos) > 12) {
                throw ValidationException::withMessages(['photos' => 'Foto tidak berasal dari BA ini atau jumlahnya melebihi 12.']);
            }
            $lop->evidences()->whereIn('category', [EvidenceCategory::BA_BONGKAR, EvidenceCategory::BA_BONGKAR_PHOTO])->delete();
            $data = ['revision' => $revision, 'ready' => false, 'submitted_at' => null, 'ever_submitted' => ! empty($old['submitted_at']) || ! empty($old['ever_submitted'])];
            if ($required) {
                $data['form'] = $input['form'] ?? [];
                $data['signatures'] = [];
                if ($signature) {
                    $data['signatures']['technician'] = $this->signature($lop, $actor, $signature, $written);
                } elseif ((int) data_get($old, 'signatures.technician.user_id') === (int) $actor->getKey()) {
                    $data['signatures']['technician'] = $old['signatures']['technician'];
                }
                $savedPhotos = collect();
                foreach ($keep as $id) {
                    $copy = $oldPhotos->get($id)->replicate();
                    $copy->forceFill(['status' => EvidenceStatus::PENDING, 'uploaded_by' => $actor->getKey(), 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null, 'metadata' => [...$copy->metadata, 'revision' => $revision]]);
                    $copy->save();
                    $savedPhotos->push($copy);
                }
                foreach ($photos as $photo) {
                    $savedPhotos->push($this->evidence->storeBaFile($lop, $actor, EvidenceCategory::BA_BONGKAR_PHOTO, $photo->get(), $photo->getClientOriginalName(), $photo->getMimeType(), ['revision' => $revision], $written));
                }
                if ($input['action'] === 'generate') {
                    if ($savedPhotos->isEmpty() || ! data_get($data, 'signatures.technician.path')) {
                        throw ValidationException::withMessages(['workflow' => 'Minimal satu foto dan TTD teknisi wajib tersedia sebelum membuat PDF BA.']);
                    }
                    $metadata = ['revision' => $revision, 'form' => $data['form'], 'signatures' => $data['signatures'], 'photo_ids' => $savedPhotos->pluck('id_evidence')->all()];
                    $this->evidence->storeBaFile($lop, $actor, EvidenceCategory::BA_BONGKAR, $this->pdf->render($lop, $data['form'], $data['signatures'], $savedPhotos), $this->filename($lop), 'application/pdf', $metadata, $written);
                    $data['ready'] = true;
                }
            }
            $lop->update(['ba_bongkar_required' => $required, 'ba_bongkar_data' => $data]);
            $this->lops->recordBaBongkarEvent($lop, $actor, 'ba_bongkar_saved', ['revision' => $revision, 'required' => $required, 'ready' => $data['ready']]);
        });
    }

    public function review(QeLop $lop, User $actor, string $revision, bool $approved, ?string $note): void
    {
        $this->transaction(function (array &$written) use ($lop, $actor, $revision, $approved, $note) {
            $lop = QeLop::query()->lockForUpdate()->findOrFail($lop->getKey());
            Gate::forUser($actor)->authorize('reviewBaBongkar', $lop);
            $this->assertRevision(data_get($lop->ba_bongkar_data, 'revision'), $revision);
            $lop->load('evidences');
            $doc = BaBongkarRules::document($lop);
            if (! $doc || $doc->status !== EvidenceStatus::PENDING || ! data_get($lop->ba_bongkar_data, 'submitted_at')) {
                throw ValidationException::withMessages(['review' => 'BA belum diajukan atau sudah direview. Muat ulang halaman.']);
            }
            $metadata = $doc->metadata;
            if (! $approved && blank($note)) {
                throw ValidationException::withMessages(['review_note' => 'Isi alasan penolakan BA.']);
            }
            $lop->evidences()->whereIn('category', [EvidenceCategory::BA_BONGKAR, EvidenceCategory::BA_BONGKAR_PHOTO])->update([
                'status' => $approved ? EvidenceStatus::APPROVED->value : EvidenceStatus::REJECTED->value,
                'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'review_note' => $approved ? null : $note,
            ]);
            if (! $approved && $lop->status_lop === LopStatus::WAITING_APPROVAL) {
                $this->lops->transitionStatus($lop, LopStatus::REJECTED, $actor, 'BA Bongkar ditolak Admin: '.$note);
            }
            $this->lops->recordBaBongkarEvent($lop, $actor, $approved ? 'ba_bongkar_approved' : 'ba_bongkar_rejected', ['revision' => $revision, 'note' => $note]);
            $technicianId = data_get($metadata, 'signatures.technician.user_id');
            DB::afterCommit(fn () => rescue(fn () => User::find($technicianId)?->notify(new TechnicianActivityNotification($approved ? 'BA Bongkar disetujui' : 'BA Bongkar perlu diperbaiki', $approved ? "BA {$lop->incident} disetujui Admin." : "BA {$lop->incident} ditolak: {$note}", $lop->getKey(), $approved ? 'success' : 'danger'))));
        });
    }

    private function signature(QeLop $lop, User $actor, array $signature, array &$written): array
    {
        $path = 'ba-bongkar/'.$lop->getKey().'/signatures/'.Str::uuid().'.'.$signature['extension'];
        if (! Storage::disk('local')->put($path, $signature['bytes'])) {
            throw new \RuntimeException('Penyimpanan TTD gagal.');
        }
        $written[] = $path;

        return ['path' => $path, 'user_id' => $actor->getKey(), 'name' => $actor->name, 'nik' => $actor->nik, 'phone' => $actor->phone, 'source' => $signature['source'], 'signed_at' => now()->toIso8601String()];
    }

    private function assertRevision(?string $current, ?string $expected): void
    {
        if ($current !== $expected) {
            throw ValidationException::withMessages(['revision' => 'BA telah berubah. Muat ulang halaman sebelum menyimpan atau mereview.']);
        }
    }

    private function filename(QeLop $lop): string
    {
        return 'BA Bongkar-'.Str::limit(preg_replace('/[^\pL\pN _.-]/u', '-', $lop->incident.'-'.$lop->nama_lop), 160, '').'.pdf';
    }

    private function transaction(Closure $callback): mixed
    {
        $written = [];
        try {
            return DB::transaction(function () use ($callback, &$written) {
                return $callback($written);
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($written);
            throw $error;
        }
    }
}
