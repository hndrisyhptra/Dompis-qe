@props(['value' => 1])

<x-select name="is_active" label="Status">
    <option value="1" @selected((int) old('is_active', (int) $value) === 1)>Aktif</option>
    <option value="0" @selected((int) old('is_active', (int) $value) === 0)>Nonaktif</option>
</x-select>
