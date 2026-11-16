{{-- Tabel LOP dipakai bersama oleh detail LOP manual dan detail tiket yang sudah memiliki LOP. --}}
<div role="region" aria-label="Daftar LOP" tabindex="0" class="overflow-x-auto rounded-md border border-ink-100 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-800">
    <table class="incident-ticket-table w-full min-w-[56rem] table-fixed text-left text-xs">
        <colgroup><col style="width: 9rem"><col style="width: 18rem"><col style="width: 9rem"><col style="width: 14rem"><col style="width: 8rem"><col style="width: 11rem"><col style="width: 7rem"></colgroup>
        <thead class="bg-ink-50 text-[11px] font-bold uppercase tracking-wide text-ink-500 dark:bg-ink-800 dark:text-ink-300">
            <tr>
                <th scope="col" class="px-3 py-3 text-center">Incident</th>
                <th scope="col" class="px-3 py-3 text-center">Nama LOP</th>
                <th scope="col" class="px-3 py-3 text-center">Program</th>
                <th scope="col" class="px-3 py-3 text-center">Branch / STO</th>
                <th scope="col" class="px-3 py-3 text-center">Tanggal Laporan</th>
                <th scope="col" class="px-3 py-3 text-center">Status</th>
                <th scope="col" class="px-3 py-3 text-center">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
            @foreach ($rows as $row)
                <tr class="align-middle hover:bg-brand-50/40 dark:hover:bg-ink-800/50">
                    <td class="whitespace-nowrap px-3 py-3 font-mono font-semibold text-ink-900 dark:text-white">{{ $row['incident'] }}</td>
                    <td class="px-3 py-3"><span class="block truncate font-medium" title="{{ $row['nama_lop'] }}">{{ $row['nama_lop'] }}</span></td>
                    <td class="whitespace-nowrap px-3 py-3">{{ $row['program'] }}</td>
                    <td class="px-3 py-3">
                        <span class="block truncate">{{ $row['branch'] }}</span>
                        <span class="mt-0.5 block truncate text-[11px] text-ink-500 dark:text-ink-400">{{ $row['workzone'] }}</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-3 text-center">{{ $row['reported_date'] }}</td>
                    <td class="whitespace-nowrap px-3 py-3 text-center">
                        @if ($row['status_label'])
                            <x-badge :variant="$row['status_variant']">{{ $row['status_label'] }}</x-badge>
                        @else
                            <span class="text-ink-400 dark:text-ink-500">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-3 text-center">
                        @if ($row['detail_url'])
                            <a href="{{ $row['detail_url'] }}" class="inline-flex min-h-9 items-center font-semibold text-brand-600 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-brand-300">Detail LOP</a>
                        @else
                            <span class="text-ink-400 dark:text-ink-500">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
