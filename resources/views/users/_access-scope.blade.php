@php
    $editingUser = $user ?? null;
    $fieldPrefix = $fieldPrefix ?? '';
    $useOldInput = $useOldInput ?? true;
    $fieldValue = fn (string $key, mixed $default = null) => $useOldInput ? old($key, $default) : $default;
    $selectedServiceAreas = collect($fieldValue(
        'service_area_ids',
        $editingUser?->serviceAreas?->pluck('id_service_area')->all() ?? []
    ))->map(fn ($id) => (string) $id)->all();
@endphp

<section
    x-show="String(roleId) === String(adminRoleId)"
    x-cloak
    class="rounded-xl border border-ink-200 bg-ink-50/70 p-4 dark:border-ink-700 dark:bg-ink-900/40"
>
    <div class="mb-4">
        <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">Scope akses Admin</h2>
        <p class="mt-1 text-xs leading-5 text-ink-500 dark:text-ink-400">Tentukan batas lokasi LOP yang dapat dilihat dan dikelola akun ini.</p>
    </div>

    <x-select name="admin_scope_type" :id="$fieldPrefix.'admin_scope_type'" :show-errors="$useOldInput" label="Level scope" placeholder="Pilih level scope" x-model="scopeType" x-bind:disabled="String(roleId) !== String(adminRoleId)">
        @foreach ($scopeTypes as $scopeType)
            <option value="{{ $scopeType->value }}" @selected($fieldValue('admin_scope_type', $editingUser?->admin_scope_type?->value) === $scopeType->value)>
                {{ $scopeType->label() }}
            </option>
        @endforeach
    </x-select>

    <p class="mt-2 text-xs text-ink-500 dark:text-ink-400" x-show="scopeType === 'area'">Mencakup seluruh Region, Branch, dan Service Area di Area terpilih.</p>
    <p class="mt-2 text-xs text-ink-500 dark:text-ink-400" x-show="scopeType === 'region'">Mencakup seluruh Branch dan Service Area di Region terpilih.</p>
    <p class="mt-2 text-xs text-ink-500 dark:text-ink-400" x-show="scopeType === 'branch'">Mencakup seluruh Service Area di Branch terpilih.</p>
    <p class="mt-2 text-xs text-ink-500 dark:text-ink-400" x-show="scopeType === 'service_area'">Bisa memilih beberapa Service Area, termasuk lintas Branch bila diperlukan.</p>

    <div class="mt-4" x-show="scopeType === 'area'">
        <x-select name="area_id" :id="$fieldPrefix.'area_id'" :show-errors="$useOldInput" label="Area" placeholder="Pilih area" x-bind:disabled="String(roleId) !== String(adminRoleId) || scopeType !== 'area'">
            @foreach ($areas as $area)
                <option value="{{ $area->id_area }}" @selected($fieldValue('area_id', $editingUser?->area_id) == $area->id_area)>{{ $area->name }}</option>
            @endforeach
        </x-select>
    </div>

    <div class="mt-4" x-show="scopeType === 'region'">
        <x-select name="region_id" :id="$fieldPrefix.'region_id'" :show-errors="$useOldInput" label="Region" placeholder="Pilih region" x-bind:disabled="String(roleId) !== String(adminRoleId) || scopeType !== 'region'">
            @foreach ($regions as $region)
                <option value="{{ $region->id_region }}" @selected($fieldValue('region_id', $editingUser?->region_id) == $region->id_region)>
                    {{ $region->name }} · {{ $region->area?->name }}
                </option>
            @endforeach
        </x-select>
    </div>

    <div class="mt-4" x-show="scopeType === 'branch'">
        <x-select name="branch_id" :id="$fieldPrefix.'branch_id'" :show-errors="$useOldInput" label="Branch" placeholder="Pilih branch" x-bind:disabled="String(roleId) !== String(adminRoleId) || scopeType !== 'branch'">
            @foreach ($branches as $branch)
                <option value="{{ $branch->id_branch }}" @selected($fieldValue('branch_id', $editingUser?->branch_id) == $branch->id_branch)>
                    {{ $branch->name }} · {{ $branch->regionRef?->name }}
                </option>
            @endforeach
        </x-select>
    </div>

    <div class="mt-4" x-show="scopeType === 'service_area'">
        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300">Service Area</label>
        <div class="mt-1.5 max-h-60 overflow-y-auto rounded-lg border border-ink-200 bg-white p-2 dark:border-ink-700 dark:bg-ink-800">
            @foreach ($serviceAreas->groupBy(fn ($item) => $item->branch?->name ?? 'Tanpa Branch') as $branchName => $items)
                <div class="border-b border-ink-100 py-2 last:border-0 dark:border-ink-700">
                    <p class="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-ink-400">{{ $branchName }}</p>
                    <div class="grid gap-1 sm:grid-cols-2">
                        @foreach ($items as $serviceArea)
                            <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-2 text-sm text-ink-700 hover:bg-ink-50 dark:text-ink-200 dark:hover:bg-ink-700/60">
                                <input type="checkbox" name="service_area_ids[]" value="{{ $serviceArea->id_service_area }}"
                                       @checked(in_array((string) $serviceArea->id_service_area, $selectedServiceAreas, true))
                                       x-bind:disabled="String(roleId) !== String(adminRoleId) || scopeType !== 'service_area'"
                                       class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                                <span>{{ $serviceArea->workzone }} <span class="text-ink-400">· {{ $serviceArea->name }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        @if ($useOldInput)
            @error('service_area_ids')
                <p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>
            @enderror
        @endif
    </div>
</section>

<div x-show="String(roleId) !== String(adminRoleId)" x-cloak>
    <x-select name="branch_id" :id="$fieldPrefix.'branch_id_optional'" :show-errors="$useOldInput" label="Branch (opsional)" placeholder="Pilih branch" x-bind:disabled="String(roleId) === String(adminRoleId)">
        @foreach ($branches as $branch)
            <option value="{{ $branch->id_branch }}" @selected($fieldValue('branch_id', $editingUser?->branch_id) == $branch->id_branch)>{{ $branch->name }}</option>
        @endforeach
    </x-select>
</div>
