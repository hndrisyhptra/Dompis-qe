<?php

namespace App\Http\Requests;

use App\Support\BaBongkarRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBaBongkarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('editBaBongkar', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        $required = $this->boolean('required') && $this->input('action') === 'generate' ? 'required' : 'nullable';

        return [
            'required' => ['required', 'boolean'],
            'revision' => ['nullable', 'uuid'],
            'action' => ['required', Rule::in(['draft', 'generate'])],
            'form' => ['nullable', 'array:number,date,witel,sto_asal,lokasi,sto_tujuan,tindak_lanjut,mitra,barcode_scmt,receiver_name,receiver_position,receiver_phone,photo_caption,materials'],
            'form.number' => ['nullable', 'string', 'max:100'],
            'form.date' => [$required, 'date_format:Y-m-d'],
            'form.witel' => [$required, 'string', 'max:255'],
            'form.sto_asal' => [$required, 'string', 'max:255'],
            'form.lokasi' => [$required, 'string', 'max:1000'],
            'form.sto_tujuan' => [$required, 'string', 'max:255'],
            'form.tindak_lanjut' => ['nullable', Rule::in(['penghapusan', 'refurbished'])],
            'form.mitra' => ['nullable', 'string', 'max:255'],
            'form.barcode_scmt' => ['nullable', 'string', 'max:100'],
            'form.receiver_name' => ['nullable', 'string', 'max:100'],
            'form.receiver_position' => ['nullable', 'string', 'max:100'],
            'form.receiver_phone' => ['nullable', 'string', 'max:30'],
            'form.photo_caption' => ['nullable', 'string', 'max:100'],
            'form.materials' => [$required, 'array', 'min:1', 'max:100'],
            'form.materials.*' => ['array:group,type,length,quantity,year,brand,segments,condition'],
            'form.materials.*.group' => ['required', Rule::in(array_keys(BaBongkarRules::MATERIALS))],
            'form.materials.*.type' => ['nullable', 'string', 'max:50'],
            'form.materials.*.length' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'form.materials.*.quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'form.materials.*.year' => ['nullable', 'integer', 'between:1900,'.now()->year],
            'form.materials.*.brand' => ['nullable', 'string', 'max:100'],
            'form.materials.*.segments' => ['nullable', Rule::in(['2 Segmen', '3 Segmen'])],
            'form.materials.*.condition' => ['nullable', Rule::in(['Patah', 'Bengkok', 'Berkarat', 'Kropos'])],
            'keep_photo_ids' => ['nullable', 'array', 'max:12'],
            'keep_photo_ids.*' => ['integer', 'distinct'],
            'photos' => ['nullable', 'array', 'max:12'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=8000,max_height=8000'],
            'signature_data' => ['nullable', 'string', 'max:2800000'],
            'signature_file' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            foreach ($this->file('photos', []) as $i => $photo) {
                $size = @getimagesize($photo->getRealPath());
                if ($size && $size[0] * $size[1] > 24000000) {
                    $validator->errors()->add("photos.$i", 'Foto maksimal 24 megapiksel. Perkecil resolusi sebelum upload.');
                }
            }
            if (! $this->boolean('required') || $this->input('action') !== 'generate') {
                return;
            }
            foreach ((array) $this->input('form.materials', []) as $i => $row) {
                $spec = BaBongkarRules::MATERIALS[$row['group'] ?? ''] ?? null;
                if (! $spec) {
                    continue;
                }
                if ($spec['types'] && ! in_array($row['type'] ?? null, $spec['types'], true)) {
                    $validator->errors()->add("form.materials.$i.type", 'Pilih tipe material sesuai kelompoknya.');
                }
                if ($spec['measure'] && ! is_numeric($row[$spec['measure']] ?? null)) {
                    $validator->errors()->add("form.materials.$i.{$spec['measure']}", 'Isi panjang/jumlah material bongkaran.');
                }
                if (($row['group'] ?? '') === 'tiang' && (empty($row['segments']) || empty($row['condition']))) {
                    $validator->errors()->add("form.materials.$i.segments", 'Lengkapi jenis dan kondisi tiang.');
                }
            }
        }];
    }
}
