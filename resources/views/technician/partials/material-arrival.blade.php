@if ($state['materialArrivalPerItem'])
    <div class="rounded-md border border-brand-100 bg-brand-50 p-3 text-xs leading-5 text-brand-800 dark:border-brand-900 dark:bg-brand-950/30 dark:text-brand-200">
        Wajib minimal 1 foto untuk setiap item material BOQ Plan. Bisa upload beberapa foto per item. Item jasa tidak memerlukan foto Material Tiba.
    </div>
    @forelse ($state['materialArrivalItems'] as $arrivalItem)
        @if ($arrivalItem->designator_id)
            <x-technician-evidence-uploader :lop="$lop" category="material_arrival" :designator-id="$arrivalItem->designator_id"
                :title="'Material tiba · '.$arrivalItem->designator_code"
                :description="$arrivalItem->item_name.' · Plan '.number_format((float) $arrivalItem->qty, 0, ',', '.').' '.$arrivalItem->unit"
                :existing="$evidenceFor('material_arrival', $arrivalItem->designator_id)"
                :allow-upload="! $arrivalReadOnly" :allow-replace="$arrivalAllowReplace" :initially-collapsed="true" />
        @else
            <p class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">{{ $arrivalItem->designator_code }} · {{ $arrivalItem->item_name }} — belum terhubung ke designator. Hubungi admin untuk memperbaiki BOQ Plan agar evidence dapat diupload.</p>
        @endif
    @empty
        <p class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">Belum ada item material ber-volume pada BOQ Plan. Hubungi admin untuk melengkapi BOQ Plan sebelum melanjutkan Material Tiba.</p>
    @endforelse
    @foreach ($evidenceFor('material_arrival')->filter(fn ($e) => ! $e->designator_id || ! $state['materialArrivalItems']->contains('designator_id', $e->designator_id))->groupBy('designator_id') as $oldArrival)
        <x-technician-evidence-uploader :lop="$lop" category="material_arrival" title="Material tiba · Dokumentasi lama"
            description="Tetap dapat dilihat, tetapi tidak memenuhi checklist per item BOQ Plan. Upload pada item yang sesuai di atas."
            :existing="$oldArrival" :allow-upload="false" :allow-replace="false" :initially-collapsed="true" />
    @endforeach
@else
    <x-technician-evidence-uploader :lop="$lop" category="material_arrival" title="Material tiba"
        description="Foto material yang sudah tersedia di lokasi. Minimal 1 foto."
        :existing="$evidenceFor('material_arrival')" :allow-upload="! $arrivalReadOnly" :allow-replace="$arrivalAllowReplace" />
@endif
