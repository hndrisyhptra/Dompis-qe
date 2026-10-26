@php($baReviewDocument = \App\Support\BaBongkarRules::document($lop))
@if ($baReviewDocument?->status === \App\Enums\EvidenceStatus::PENDING && auth()->user()->can('reviewBaBongkar', $lop))
    <form method="POST" action="{{ route('evidence-approval.ba-bongkar.review', $lop) }}" class="space-y-4 rounded-md border border-ink-200 bg-white p-4 dark:border-ink-700 dark:bg-ink-900" x-data="{ saving: false }" @submit="saving = true">
        @csrf
        <input type="hidden" name="revision" value="{{ data_get($lop->ba_bongkar_data, 'revision') }}">
        <input type="hidden" name="decision" value="approve" x-ref="decision">
        <p class="text-sm font-bold">Review BA Bongkar</p>
        <p class="text-xs text-ink-500">Periksa PDF dan foto material bongkaran sebagai satu paket sebelum menyetujui atau mengembalikannya.</p>
        @error('revision')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
        <label class="block text-sm font-semibold">Catatan / alasan penolakan
            <textarea name="review_note" rows="3" maxlength="1000" class="mt-2 w-full rounded-md border border-ink-300 bg-white p-3 focus:border-brand-500 focus:ring-brand-500 dark:border-ink-600 dark:bg-ink-800">{{ old('review_note') }}</textarea>
        </label>
        @error('review_note')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
        <div class="flex flex-wrap gap-3">
            <button type="submit" @click="$refs.decision.value = 'approve'" :disabled="saving" class="min-h-11 rounded-md bg-emerald-600 px-4 text-sm font-bold text-white disabled:opacity-50">Setujui BA Bongkar</button>
            <button type="submit" @click="$refs.decision.value = 'reject'" :disabled="saving" class="min-h-11 rounded-md border border-red-300 px-4 text-sm font-bold text-red-700 disabled:opacity-50">Tolak / kembalikan</button>
            <span x-show="saving" role="status" class="self-center text-xs">Menyimpan keputusan…</span>
        </div>
    </form>
@endif
