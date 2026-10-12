@if ($userImportBatches->isNotEmpty())
    <section class="mt-6 overflow-hidden rounded-md border border-ink-200 bg-white dark:border-ink-700 dark:bg-ink-900">
        <div class="flex items-center justify-between gap-3 border-b border-ink-100 px-4 py-3 dark:border-ink-800">
            <h2 class="text-sm font-semibold text-ink-900 dark:text-white">History Upload User</h2><span class="text-xs text-ink-400">5 upload terakhir</span>
        </div>
        <div class="divide-y divide-ink-100 dark:divide-ink-800">
            @foreach ($userImportBatches as $batch)
                <a href="{{ route('users.imports.show', $batch) }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 transition hover:bg-ink-50 dark:hover:bg-ink-800/50">
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-medium text-ink-900 dark:text-white">{{ $batch->original_name }}</p><p class="mt-1 text-xs text-ink-500">{{ $batch->uploader?->name ?? 'User terhapus' }} · {{ $batch->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p></div>
                    <div class="flex items-center gap-3 text-xs"><span class="text-ink-500">{{ $batch->success_rows }} berhasil · {{ $batch->failed_rows }} gagal</span><x-badge :variant="match ($batch->status) { 'completed' => 'success', 'partial' => 'warning', 'failed' => 'danger', default => 'info' }">{{ match ($batch->status) { 'completed' => 'Selesai', 'partial' => 'Sebagian berhasil', 'failed' => 'Gagal', 'processing' => 'Diproses', default => 'Antrean' } }}</x-badge></div>
                </a>
            @endforeach
        </div>
    </section>
@endif

<x-modal id="user-template-modal" title="Download Template User" size="lg">
    <form method="GET" action="{{ route('users.imports.template') }}" class="space-y-4">
        <p class="text-sm leading-6 text-ink-500 dark:text-ink-400">Pilih contoh scope. Semua template menggunakan kolom yang sama, sehingga satu file dapat berisi beberapa role dan scope.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="user_template_scope" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-200">Contoh scope</label>
                <select id="user_template_scope" name="scope" class="w-full rounded-md border border-ink-300 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-600 dark:bg-ink-800 dark:text-white">
                    <option value="all">Semua scope + Teknisi</option><option value="area">Admin Area</option><option value="region">Admin Region</option><option value="branch">Admin Branch</option><option value="service_area">Admin Service Area</option><option value="non_admin">Role selain Admin</option>
                </select>
            </div>
            <div><label for="user_template_format" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-200">Format file</label>
                <select id="user_template_format" name="format" class="w-full rounded-md border border-ink-300 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-600 dark:bg-ink-800 dark:text-white"><option value="xlsx">Excel (.xlsx) + panduan dan referensi lokasi</option><option value="csv">CSV (.csv)</option></select>
            </div>
        </div>
        <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/20 dark:text-amber-300">Ganti atau hapus baris CONTOH sebelum upload. Password contoh sengaja kosong dan status inactive. Isi password awal setiap akun dan pilih active jika akun boleh login.</div>
        <div class="flex justify-end gap-2"><button type="button" onclick="document.getElementById('user-template-modal').close()" class="rounded-md border border-ink-200 px-4 py-2.5 text-sm dark:border-ink-700">Batal</button><button type="submit" class="rounded-md bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Download Template</button></div>
    </form>
</x-modal>

<x-modal id="bulk-user-modal" title="Bulk Import User" size="xl">
    <form method="POST" action="{{ route('users.imports.store') }}" enctype="multipart/form-data" class="space-y-4" x-data="{ submitting: false, fileName: '' }" @submit="submitting = true">
        @csrf
        <div class="rounded-md border border-ink-200 bg-ink-50 p-3 text-sm leading-6 text-ink-600 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-300">Buat banyak akun dari satu file. Baris valid akan dibuat; baris gagal ditampilkan beserta alasannya. Username, NIK, atau email yang sudah ada tidak akan ditimpa.</div>
        @if ($errors->userImport->any())
            <div role="alert" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">@foreach ($errors->userImport->all() as $message)<p>{{ $message }}</p>@endforeach</div>
        @endif
        <div class="rounded-md border border-ink-300 p-4 dark:border-ink-600">
            <label for="bulk_user_file" class="mb-2 block text-sm font-semibold text-ink-900 dark:text-white">File Excel atau CSV</label>
            <input id="bulk_user_file" type="file" name="file" accept=".xlsx,.xls,.csv" required @change="fileName = $event.target.files[0]?.name || ''" class="block w-full rounded-md border border-ink-300 bg-white text-sm text-ink-500 file:mr-3 file:border-0 file:bg-ink-100 file:px-3 file:py-2.5 file:font-semibold file:text-ink-700 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:border-ink-600 dark:bg-ink-900 dark:text-ink-400 dark:file:bg-ink-700 dark:file:text-ink-200">
            <p class="mt-2 text-xs text-ink-400">Maksimal 5 MB dan 1000 user per file. Username, NIK, dan nomor telepon harus berformat teks agar nol depan tetap tersimpan.</p>
            <p x-show="fileName" x-cloak class="mt-2 truncate text-xs font-medium text-brand-600 dark:text-brand-400" x-text="fileName"></p>
        </div>
        <div class="overflow-hidden rounded-md border border-ink-200 dark:border-ink-700">
            <div class="border-b border-ink-100 px-3 py-2.5 dark:border-ink-800"><h3 class="text-sm font-semibold">Format lokasi sesuai scope</h3><p class="mt-1 text-xs text-ink-500">Role Admin memakai admin_scope_type. Lokasi memakai kode pada master, bukan ID database.</p></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="bg-ink-50 text-ink-500 dark:bg-ink-800"><tr><th class="px-3 py-2">Role / Scope</th><th class="px-3 py-2">Kolom wajib lokasi</th><th class="px-3 py-2">Contoh</th></tr></thead><tbody class="divide-y divide-ink-100 dark:divide-ink-800"><tr><td class="px-3 py-2.5">ADMIN / area</td><td class="px-3 py-2.5 font-mono">area</td><td class="px-3 py-2.5">3</td></tr><tr><td class="px-3 py-2.5">ADMIN / region</td><td class="px-3 py-2.5 font-mono">region</td><td class="px-3 py-2.5">JATIM</td></tr><tr><td class="px-3 py-2.5">ADMIN / branch</td><td class="px-3 py-2.5 font-mono">branch</td><td class="px-3 py-2.5">SDA / SIDOARJO</td></tr><tr><td class="px-3 py-2.5">ADMIN / service_area</td><td class="px-3 py-2.5 font-mono">service_areas</td><td class="px-3 py-2.5">SDA|SDB (kode STO, sesuaikan master)</td></tr><tr><td class="px-3 py-2.5">TEKNISI / role lain</td><td class="px-3 py-2.5">Branch opsional</td><td class="px-3 py-2.5">Kosongkan scope dan lokasi lainnya</td></tr></tbody></table></div>
        </div>
        <p class="text-xs leading-5 text-ink-500 dark:text-ink-400">Wajib: name, username, password minimal 8 karakter, dan role. NIK, email, phone opsional. File berisi password disimpan privat, terenkripsi, lalu dihapus setelah proses selesai.</p>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ink-100 pt-4 dark:border-ink-800">
            <button type="button" onclick="document.getElementById('user-template-modal').showModal()" class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-400">Belum punya template? Download di sini</button>
            <div class="flex gap-2"><button type="button" :disabled="submitting" onclick="document.getElementById('bulk-user-modal').close()" class="rounded-md border border-ink-200 px-4 py-2.5 text-sm disabled:opacity-50 dark:border-ink-700">Batal</button><button type="submit" :disabled="submitting" class="rounded-md bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-wait disabled:opacity-60" x-text="submitting ? 'Mengunggah...' : 'Masukkan ke Antrean'">Masukkan ke Antrean</button></div>
        </div>
    </form>
</x-modal>
@if ($errors->userImport->any())
    <script>document.addEventListener('DOMContentLoaded', () => document.getElementById('bulk-user-modal')?.showModal());</script>
@endif
