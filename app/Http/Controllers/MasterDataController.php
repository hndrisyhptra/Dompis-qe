<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorCategory;
use App\Models\DesignatorPackagePrice;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\Region;
use App\Models\TicketSegmentMap;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    /**
     * Halaman hub Master Data: daftar seluruh entity master + jumlah baris
     * + tautan kelola.
     */
    public function index(): View
    {
        $this->authorize('manage-master-data');

        $entities = [
            ['label' => 'Region', 'route' => 'regions.index', 'count' => Region::count(), 'desc' => 'Wilayah operasional (JATIM, JATENG DIY, BALNUS).'],
            ['label' => 'Branch', 'route' => 'branches.index', 'count' => Branch::count(), 'desc' => 'Cabang / witel di bawah tiap region.'],
            ['label' => 'Kategori Designator', 'route' => 'designator-categories.index', 'count' => DesignatorCategory::count(), 'desc' => 'Pengelompokan item pekerjaan (ODP, ODC, Kabel, ...).'],
            ['label' => 'Tipe Designator', 'route' => 'designator-types.index', 'count' => DesignatorType::count(), 'desc' => 'Jenis pekerjaan designator (Material, Jasa, Instalasi, ...).'],
            ['label' => 'Designator', 'route' => 'designators.index', 'count' => Designator::count(), 'desc' => 'Katalog item/material untuk BOQ & reservasi.'],
            ['label' => 'KHS (Harga)', 'route' => 'designator-prices.index', 'count' => DesignatorPackagePrice::count(), 'desc' => 'Harga satuan designator per paket.'],
            ['label' => 'Paket KHS', 'route' => 'packages.index', 'count' => Package::count(), 'desc' => 'Wadah harga satuan pekerjaan.'],
            ['label' => 'Pemetaan Segment Tiket', 'route' => 'ticket-segment-maps.index', 'count' => TicketSegmentMap::count(), 'desc' => 'jenis_tiket_2 -> Segmen LOP (auto-fill Input LOP Baru).'],
            ['label' => 'Format Nama LOP', 'route' => 'lop-name-format.edit', 'count' => null, 'desc' => 'Template penamaan LOP otomatis.'],
        ];

        return view('master-data.index', ['entities' => $entities]);
    }
}
