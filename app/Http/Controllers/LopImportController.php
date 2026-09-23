<?php

namespace App\Http\Controllers;

use App\Enums\LopBudgetType;
use App\Enums\LopSegment;
use App\Enums\ProgramType;
use App\Http\Requests\ImportLopRequest;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Package;
use App\Models\QeLop;
use App\Models\ServiceArea;
use App\Services\LopExcelImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LopImportController extends Controller
{
    private const SESSION_KEY = 'lop_import';

    public function __construct(private readonly LopExcelImportService $importService) {}

    public function importForm(): View
    {
        $this->authorize('create', QeLop::class);

        return view('lop.import', [
            'areas' => Area::query()->where('is_active', true)->orderBy('code')->get(),
            'branches' => Branch::query()->with('regionRef')->where('is_active', true)->orderBy('name')->get(),
            'serviceAreas' => ServiceArea::query()->with(['branch', 'region'])->where('is_active', true)->orderBy('workzone')->get(),
            'programTypes' => ProgramType::cases(),
            'segments' => LopSegment::cases(),
            'budgetTypes' => LopBudgetType::cases(),
            'packages' => Package::query()->orderBy('code')->get(),
        ]);
    }

    public function preview(ImportLopRequest $request): View
    {
        $parsed = $this->importService->parse($request->file('file'));

        $preview = $this->importService->preview($parsed, $request->only([
            'nama_lop', 'sto', 'branch', 'area', 'program_type',
            'incident', 'job_description', 'package_code',
        ]));

        // Simpan payload untuk langkah store (hindari re-upload + anti-tamper baris)
        session()->put(self::SESSION_KEY, [
            'lop' => $preview['lop'],
            'package' => $preview['package'],
            'rows' => $preview['rows'],
            'totals' => $preview['totals'],
            'file_name' => $request->file('file')->getClientOriginalName(),
        ]);

        return view('lop.import-preview', [
            'preview' => $preview,
            'fileName' => $request->file('file')->getClientOriginalName(),
            'areas' => Area::query()->where('is_active', true)->orderBy('code')->get(),
            'branches' => Branch::query()->with('regionRef')->where('is_active', true)->orderBy('name')->get(),
            'serviceAreas' => ServiceArea::query()->with(['branch', 'region'])->where('is_active', true)->orderBy('workzone')->get(),
            'programTypes' => ProgramType::cases(),
            'segments' => LopSegment::cases(),
            'budgetTypes' => LopBudgetType::cases(),
            'packages' => Package::query()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', QeLop::class);

        $payload = session()->get(self::SESSION_KEY);

        if (! is_array($payload) || empty($payload['rows'])) {
            return redirect()->route('lop.import.form')
                ->withErrors(['file' => 'Sesi preview kedaluwarsa — upload ulang file Excel.']);
        }

        $data = $request->validate([
            'nama_lop' => ['required', 'string', 'max:255'],
            'incident' => ['nullable', 'string', 'max:100', 'regex:/^INP\d+$/', Rule::unique('qe_lops', 'incident')],
            'program_type' => ['required', Rule::enum(ProgramType::class)],
            'sto' => ['required', 'string', 'max:20', Rule::exists('service_areas', 'workzone')],
            'branch' => ['required', 'string', 'max:100', Rule::exists('branches', 'name')],
            'area' => ['required', 'string', 'max:10', Rule::exists('areas', 'code')],
            'segment' => $request->input('program_type') === ProgramType::RELOK_UTILITAS->value
                ? ['required', 'array', 'min:1', 'max:3']
                : ['required', Rule::enum(LopSegment::class)],
            'budget_type' => [
                'nullable',
                'required_if:program_type,'.ProgramType::RELOK_UTILITAS->value,
                Rule::enum(LopBudgetType::class),
            ],
            'package_code' => ['nullable', 'string', Rule::exists('packages', 'code')],
            'job_description' => ['required', 'string', 'max:2000'],
        ], [
            'incident.regex' => 'Incident manual harus format INP diikuti angka (atau kosongkan untuk generate otomatis).',
            'sto.exists' => 'STO tidak ditemukan di Master Service Area.',
            'segment.max' => 'Maksimal 3 segmen untuk program Relok Utilitas.',
        ]);

        if ($request->input('program_type') === ProgramType::RELOK_UTILITAS->value) {
            Validator::make($request->all(), [
                'segment.*' => ['required', 'string', Rule::enum(LopSegment::class)],
            ])->validate();
        }

        // Relasi Area → Branch → STO (cermin StoreLopRequest)
        $branch = Branch::with('regionRef.area')->where('name', $data['branch'])->first();
        if ($branch && $branch->regionRef && $branch->regionRef->area
            && (string) $branch->regionRef->area->code !== (string) $data['area']) {
            return back()->withErrors(['branch' => 'Branch tidak termasuk dalam Area terpilih.'])->withInput();
        }
        $sa = ServiceArea::where('workzone', mb_strtoupper($data['sto']))->first();
        if ($sa && $sa->branch && $sa->branch->name !== $data['branch']) {
            return back()->withErrors(['sto' => 'STO tidak termasuk dalam Branch terpilih.'])->withInput();
        }

        // Baris dari sesi (bukan dari input user)
        $rows = $payload['rows'];
        $okRows = array_values(array_filter($rows, fn ($r) => ($r['status'] ?? null) === 'ok'));
        $errRows = array_filter($rows, fn ($r) => ($r['status'] ?? null) === 'error');

        if ($errRows) {
            return back()->withErrors(['file' => 'Ada baris error — perbaiki file lalu preview ulang.'])->withInput();
        }
        if ($okRows === []) {
            return back()->withErrors(['file' => 'Tidak ada designator dengan VOL terisi.'])->withInput();
        }

        // Normalisasi segmen seperti StoreLopRequest
        $segment = $data['segment'];
        if (is_array($segment)) {
            $seen = [];
            $norm = [];
            foreach ($segment as $v) {
                $val = strtolower(trim((string) $v));
                if ($val === '' || isset($seen[$val])) {
                    continue;
                }
                $seen[$val] = true;
                $norm[] = $val;
            }
            $segment = $norm;
        }

        // Paket: pilihan user, fallback ke hasil preview
        $packageCode = $data['package_code'] ?? $payload['package']['chosen'] ?? null;
        if ($packageCode && ! Package::where('code', $packageCode)->exists()) {
            return back()->withErrors(['package_code' => "Paket {$packageCode} belum ada di database."])->withInput();
        }

        $totals = $payload['totals'];
        // Hitung ulang total dari baris ok (anti-tamper)
        $materialTotal = 0.0;
        $jasaTotal = 0.0;
        $materialCount = 0;
        $jasaCount = 0;
        foreach ($okRows as $r) {
            if ($r['type'] === 'MATERIAL') {
                $materialCount++;
                $materialTotal += (float) $r['total'];
            } else {
                $jasaCount++;
                $jasaTotal += (float) $r['total'];
            }
        }

        $lop = $this->importService->import([
            'lop' => [
                'nama_lop' => $data['nama_lop'],
                'program_type' => $data['program_type'],
                'sto' => mb_strtoupper($data['sto']),
                'branch' => $data['branch'],
                'area' => $data['area'],
                'segment' => $segment,
                'budget_type' => $data['budget_type'] ?? null,
                'job_description' => $data['job_description'],
                'incident' => mb_strtoupper(trim((string) ($data['incident'] ?? ''))),
            ],
            'package' => [
                'detected' => $payload['package']['detected'] ?? null,
                'chosen' => $packageCode,
            ],
            'rows' => $rows,
            'totals' => [
                'material_count' => $materialCount,
                'material_total' => $materialTotal,
                'jasa_count' => $jasaCount,
                'jasa_total' => $jasaTotal,
                'grand_total' => $materialTotal + $jasaTotal,
            ],
        ], $request->user());

        session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('lop.index')
            ->with('status', "LOP {$lop->incident} berhasil diimpor ({$materialCount} material + {$jasaCount} jasa).");
    }
}
