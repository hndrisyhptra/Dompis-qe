<?php

namespace App\Http\Controllers;

use App\Enums\ProgramType;
use App\Http\Requests\UpdateLopNameFormatRequest;
use App\Services\LopNamingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LopNameFormatController extends Controller
{
    public function __construct(private readonly LopNamingService $namingService) {}

    public function edit(): View
    {
        $this->authorize('manage-master-data');

        return view('lop-name-format.edit', [
            'template' => $this->namingService->activeTemplate(),
            'tokens' => $this->namingService->availableTokens(),
            'programCodes' => collect(ProgramType::cases())->mapWithKeys(
                fn (ProgramType $type) => [$type->value => $type->code()]
            ),
        ]);
    }

    public function update(UpdateLopNameFormatRequest $request): RedirectResponse
    {
        $this->namingService->updateTemplate(
            $request->validated('template'),
            $request->user()
        );

        return redirect()
            ->route('lop-name-format.edit')
            ->with('status', 'Format nama LOP berhasil diperbarui.');
    }
}
