@php
    $rows = collect($form['materials'] ?? [])->groupBy('group');
    $primary = [];
    $extraRows = collect();
    foreach ($groups as $key => $spec) {
        $items = $rows->get($key, collect());
        $primary[$key] = $items->first() ?? [];
        $extraRows = $extraRows->concat($items->slice($key === 'drop_core' ? 5 : 1));
        $covered = match ($key) {
            'feeder', 'distribusi' => ['length', 'year'],
            'odc' => ['brand', 'year', 'quantity'],
            'odp' => ['year', 'quantity'],
            'closure' => ['year', 'brand'],
            'tiang' => ['quantity', 'year', 'segments', 'condition'],
            default => ['length', 'quantity', 'year'],
        };
        foreach ($items->take($key === 'drop_core' ? 5 : 1) as $index => $item) {
            $fields = $covered;
            if ($key === 'drop_core' && $index > 0 && ($item['year'] ?? null) !== ($primary[$key]['year'] ?? null)) {
                $fields = array_diff($fields, ['year']);
            }
            $additional = array_diff_key($item, array_flip(['group', 'type', ...$fields]));
            if (collect($additional)->contains(static fn ($value) => filled($value))) {
                $extraRows->push(['group' => $key, 'type' => $item['type'] ?? '', ...$additional]);
            }
        }
    }
    $value = static fn (array $item, string $field, string $blank = '__________') => filled($item[$field] ?? null) ? (string) $item[$field] : $blank;
    $location = (string) ($form['lokasi'] ?? '');
    $longLocation = mb_strlen($location) > 175;
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
@font-face { font-family: Carlito; font-style: normal; font-weight: normal; src: url("file://{{ resource_path('fonts/carlito/Carlito-Regular.ttf') }}") format('truetype'); }
@font-face { font-family: Carlito; font-style: normal; font-weight: bold; src: url("file://{{ resource_path('fonts/carlito/Carlito-Bold.ttf') }}") format('truetype'); }
@font-face { font-family: Carlito; font-style: italic; font-weight: normal; src: url("file://{{ resource_path('fonts/carlito/Carlito-Italic.ttf') }}") format('truetype'); }
@page { margin: 0; }
body { margin: 0; font-family: Carlito, sans-serif; font-size: 9.24pt; line-height: 12pt; color: #000; }
.sheet { position: relative; width: 612pt; height: 790pt; page-break-before: always; }
.sheet:first-child { page-break-before: auto; }
.field, .option, .rule, .signature { position: absolute; }
.field { line-height: 12pt; overflow-wrap: break-word; }
.label, .bold { font-weight: bold; }
.group-label { font-style: italic; }
.header { position: absolute; top: 56pt; left: 51pt; width: 483pt; padding-bottom: 1pt; border-bottom: 2pt double #000; text-align: center; font-size: 11.76pt; font-weight: bold; line-height: 15.3pt; }
.option { white-space: nowrap; line-height: 12pt; }
.box { display: inline-block; width: 11pt; height: 11pt; border: .75pt solid #000; vertical-align: middle; text-align: center; margin-right: 3.5pt; }
.tick { position: relative; top: 1.4pt; left: .2pt; font-family: DejaVu Sans, sans-serif; font-size: 14pt; line-height: 9pt; }
.rule { border-bottom: .65pt solid #000; }
.signature { text-align: center; width: 145pt; line-height: 11.5pt; }
.signature img { max-width: 128pt; max-height: 66pt; }
.signature-name { font-weight: bold; text-transform: uppercase; }
.continuation { margin: 40pt 51pt; page-break-before: always; }
.continuation h2 { font-size: 11.76pt; margin-bottom: 16pt; }
.continuation .item { margin: 0 0 14pt; page-break-inside: avoid; }
.continuation p { margin: 3pt 0; }
.photo-page { margin: 65pt 51pt 40pt; width: 483pt; page-break-before: always; }
.photos { width: 100%; table-layout: fixed; border-collapse: collapse; }
.photos th, .photos td { width: 50%; padding: 1pt; border: .75pt solid #000; text-align: center; vertical-align: middle; }
.photos th { font-weight: bold; }
.photos img { max-width: 238pt; max-height: 220pt; }
</style>
</head>
<body>
<div class="sheet">
    <div class="header">BERITA ACARA<br>SERAH TERIMA MATERIAL BONGKARAN</div>
    @foreach (['number' => ['NOMOR', 99.5], 'date' => ['HARI / TANGGAL', 111.8], 'witel' => ['BRANCH', 124], 'sto_asal' => ['ASAL STO', 136.2], 'lokasi' => ['LOKASI BONGKARAN', 152.4], 'sto_tujuan' => ['STO TUJUAN', 229.5]] as $key => [$label, $top])
        <div class="field label" style="left:52.6pt;top:{{ $top }}pt;width:91pt">{{ $label }}</div>
        <div class="field" style="left:141.5pt;top:{{ $top }}pt">:</div>
        <div class="field bold" style="left:156.7pt;top:{{ $top }}pt;width:321pt">{{ $key === 'date' && filled($form[$key] ?? null) ? \Carbon\Carbon::parse($form[$key])->locale('id')->translatedFormat('l').' / '.\Carbon\Carbon::parse($form[$key])->format('d - m - Y') : ($key === 'lokasi' ? \Illuminate\Support\Str::limit($location, 175) : ($form[$key] ?? '')) }}</div>
    @endforeach
    @foreach ([192, 208.2, 224.4] as $top)<div class="rule" style="left:156.7pt;top:{{ $top }}pt;width:315pt"></div>@endforeach
    <div class="field bold" style="left:52.6pt;top:258pt">Usulan Tindak Lanjut</div>
    <div class="field" style="left:141.5pt;top:258pt">:</div>
    @include('ba-bongkar.pdf-option', ['x' => 155.3, 'y' => 256, 'label' => 'Penghapusan', 'checked' => ($form['tindak_lanjut'] ?? '') === 'penghapusan', 'bold' => true])
    @include('ba-bongkar.pdf-option', ['x' => 240.6, 'y' => 256, 'label' => 'Refurbished ( Material Memenuhi Spek Elektris )', 'checked' => ($form['tindak_lanjut'] ?? '') === 'refurbished', 'bold' => true])
    <div class="field bold" style="left:52.6pt;top:270.2pt">Jenis Material</div>
    <div class="field" style="left:141.5pt;top:270.2pt">:</div>
    @foreach ([
        'feeder' => ['A. Feeder', 282.4, [['288 Core',155.3,280.4],['264 Core',240.6,280.4],['144 Core',342.3,280.4],['96 Core',406.6,280.4],['48 Core',155.3,304.9],['36 Core',240.6,304.9],['24 Core',342.3,304.9]]],
        'distribusi' => ['B. Distribusi',380.4,[['48 Core',155.3,378.4],['24 Core',240.6,378.4],['12 Core',342.3,378.4]]],
        'odc' => ['C. ODC',453.8,[['ODC C',155.3,451.8],['ODC B',240.6,451.8]]],
        'odp' => ['D. ODP',551.7,[['ODP Pole',155.3,549.7],['ODP Wall',240.6,549.7],['ODP Padenstal',342.3,549.7],['DP Tembaga',155.3,574.2],['ODP CA',240.6,574.2]]],
        'closure' => ['E. Universal Closure',649.7,[['288 Core',155.3,647.7],['144 Core',240.6,647.7],['96 Core',342.3,647.7],['48 Core',406.6,647.7],['24 Core',155.3,672.2]]],
    ] as $key => [$label, $top, $choices])
        <div class="field group-label" style="left:52.6pt;top:{{ $top }}pt">{{ $label }}</div>
        @foreach ($choices as [$type, $x, $y])
            @include('ba-bongkar.pdf-option', ['x' => $x, 'y' => $y, 'label' => str_replace('Padenstal', 'Pedestal', $type), 'checked' => ($primary[$key]['type'] ?? '') === $type, 'bold' => false])
        @endforeach
    @endforeach
    @foreach ([
        ['feeder','length','Panjang',331.4,' Meter'], ['feeder','year','Tahun',355.9,''],
        ['distribusi','length','Panjang',404.8,' Meter'], ['distribusi','year','Tahun',429.3,''],
        ['odc','brand','Merk',478.3,''], ['odc','year','Tahun',502.8,''], ['odc','quantity','Jumlah',527.3,' Buah'],
        ['odp','year','Tahun',600.7,''], ['odp','quantity','Jumlah',625.2,' Buah'], ['closure','year','Tahun',698.6,'']
    ] as [$group, $field, $label, $top, $unit])
        <div class="field" style="left:156.7pt;top:{{ $top }}pt;width:321pt">{{ $label }} : {{ $value($primary[$group], $field) }}{{ $unit }}</div>
    @endforeach
</div>
<div class="sheet">
    <div class="field" style="left:156.7pt;top:69pt">Merk : {{ $value($primary['closure'], 'brand') }}</div>
    <div class="field group-label" style="left:52.6pt;top:97.5pt">F. Tiang</div>
    <div class="field bold" style="left:156.7pt;top:97.5pt">Jumlah Tiang : {{ $value($primary['tiang'], 'quantity') }} Batang</div>
    @foreach ([[156.7,'Tinggi Tiang :'],[242.1,'Jenis Tiang :'],[408,'Kondisi :']] as [$x, $label])<div class="field" style="left:{{ $x }}pt;top:113.7pt">{{ $label }}</div>@endforeach
    @foreach ([
        ['type','T9',155.3,129.6], ['type','T7',155.3,154], ['type','Tiang Galvanis',155.3,196.4],
        ['segments','2 Segmen',240.6,154], ['segments','3 Segmen',240.6,171.9],
        ['condition','Patah',406.6,129.6], ['condition','Bengkok',406.6,154], ['condition','Berkarat',406.6,171.9], ['condition','Kropos',406.6,196.4]
    ] as [$field, $label, $x, $y])
        @include('ba-bongkar.pdf-option', ['x' => $x, 'y' => $y, 'label' => $label, 'checked' => ($primary['tiang'][$field] ?? '') === $label, 'bold' => false])
    @endforeach
    <div class="field" style="left:156.7pt;top:222.9pt">Tahun : {{ $value($primary['tiang'], 'year') }}</div>
    <div class="field group-label" style="left:52.6pt;top:247.4pt">G. Drop Core</div>
    @foreach (range(0, 4) as $index)
        @php($drop = $rows->get('drop_core', collect())->get($index, []))
        <div class="field" style="left:156.7pt;top:{{ 247.4 + $index * 24.5 }}pt">Panjang : {{ $value($drop, 'length') }} Meter</div>
        <div class="field" style="left:306pt;top:{{ 247.4 + $index * 24.5 }}pt">Jumlah : {{ $value($drop, 'quantity') }} Unit</div>
    @endforeach
    <div class="field" style="left:156.7pt;top:369.8pt">Tahun : {{ $value($primary['drop_core'], 'year') }}</div>
    <div class="field group-label" style="left:52.6pt;top:394.3pt">E. BARCODE SCMT *</div>
    <div class="field" style="left:141.5pt;top:394.3pt">:</div>
    <div class="field" style="left:156.7pt;top:394.3pt;width:321pt">{{ $form['barcode_scmt'] ?? '' }}</div>
    <div class="field bold" style="left:52.6pt;top:418.9pt;width:482pt">Demikian berita acara ini dibuat dengan sebenar-benarnya untuk dipergunakan sebagaimana mestinya.</div>
    <div class="signature bold" style="left:57pt;top:458.8pt">Yang Menyerahkan,</div>
    <div class="signature bold" style="left:381pt;top:458.8pt">Yang Menerima,</div>
    <div class="signature" style="left:57pt;top:472.5pt;height:66pt">@if (isset($signatures['technician']['image']))<img src="{{ $signatures['technician']['image'] }}">@endif</div>
    <div class="rule" style="left:72.2pt;top:541pt;width:115pt"></div>
    <div class="rule" style="left:389.8pt;top:541pt;width:129pt"></div>
    <div class="signature signature-name" style="left:57pt;top:542.9pt">{{ $signatures['technician']['name'] ?? '' }}</div>
    <div class="signature bold" style="left:57pt;top:555.5pt">@if (filled($signatures['technician']['phone'] ?? null))(No. Tlpn : {{ $signatures['technician']['phone'] }} )@endif</div>
    <div class="signature signature-name" style="left:381pt;top:542.9pt">{{ $form['receiver_name'] ?? '' }}</div>
    <div class="signature bold" style="left:381pt;top:555.5pt">{{ $form['receiver_position'] ?? '' }}@if (filled($form['receiver_phone'] ?? null))<br>{{ $form['receiver_phone'] }}@endif</div>
    <div class="signature bold" style="left:222pt;top:574pt">Mengetahui,</div>
    <div class="signature bold" style="left:57pt;top:587pt">MGR. NETWORK OPERATION OSP</div>
    <div class="signature bold" style="left:381pt;top:587.5pt">MGR ASSET INVENTORY &amp;<br>DATA MANAGENT REGIONAL .....</div>
    <div class="signature" data-signature-role="manager_network" style="left:57pt;top:615pt;height:72pt"></div>
    <div class="signature" data-signature-role="manager_asset" style="left:381pt;top:615pt;height:72pt"></div>
    <div class="signature bold" style="left:57pt;top:688.5pt;width:145pt"><div style="display:inline-block;text-align:left">____________________<div style="display:block;margin-top:3.5pt">NIK.</div></div></div>
    <div class="signature bold" style="left:381pt;top:688.5pt;width:145pt"><div style="display:inline-block;text-align:left">____________________<div style="display:block;margin-top:3.5pt">NIK.</div></div></div>
</div>
@if ($extraRows->isNotEmpty() || $longLocation || filled($form['mitra'] ?? null))
<div class="continuation">
    <h2>RINCIAN MATERIAL BONGKARAN TAMBAHAN</h2>
    <p>{{ $lop->incident }} · {{ $lop->nama_lop }}</p>
    @if ($longLocation)<p><strong>Lokasi bongkaran:</strong> {{ $location }}</p>@endif
    @if (filled($form['mitra'] ?? null))<p><strong>Mitra teknisi:</strong> {{ $form['mitra'] }}</p>@endif
    @foreach ($extraRows as $item)
        <div class="item">
            <strong>{{ $groups[$item['group']]['label'] ?? '' }} · {{ str_replace('Padenstal', 'Pedestal', $item['type'] ?? '') }}</strong>
            @foreach (['length' => 'Panjang', 'quantity' => 'Jumlah', 'year' => 'Tahun', 'brand' => 'Merk', 'segments' => 'Jenis Tiang', 'condition' => 'Kondisi'] as $field => $label)
                @if (filled($item[$field] ?? null))<p>{{ $label }} : {{ $item[$field] }}{{ $field === 'length' ? ' Meter' : ($field === 'quantity' ? ' Buah' : '') }}</p>@endif
            @endforeach
        </div>
    @endforeach
</div>
@endif
@foreach (array_chunk($images, 4) as $photoPage)
<div class="photo-page">
    <table class="photos">
        <tr><th>{{ $form['photo_caption'] ?? 'MATERIAL BONGKARAN' }}</th><th>{{ $form['photo_caption'] ?? 'MATERIAL BONGKARAN' }}</th></tr>
        @foreach (array_chunk($photoPage, 2) as $photoRow)
            <tr>@foreach ($photoRow as $src)<td><img src="{{ $src }}"></td>@endforeach @if (count($photoRow) === 1)<td></td>@endif</tr>
        @endforeach
    </table>
</div>
@endforeach
</body>
</html>
