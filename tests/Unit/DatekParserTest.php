<?php

namespace Tests\Unit;

use App\Services\DatekParserService;
use PHPUnit\Framework\TestCase;

class DatekParserTest extends TestCase
{
    private DatekParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DatekParserService;
    }

    public function test_distribusi_with_odc_and_labeled_odp_list(): void
    {
        $d = $this->parser->parse(
            '[SQM GAMAS] | AKSES | DISTRIBUSI | TIF-3 | REG-5 | KU TERSERET KENDARAAN | PENYAMBUNGAN | '
            .'SURABAYA UTARA | PME | [ODC-PME-FBK] | Datek ODP Terdampak : [ODP-PME-FBK/29, ODP-PME-FBK/33, '
            .'ODP-PME-FBK/27] | 4 Jam | IboosterDIST_811781856092'
        );

        $this->assertSame('distribusi', $d['kategori']);
        $this->assertSame(['ODC-PME-FBK'], $d['odc']);
        $this->assertSame(['ODP-PME-FBK/29', 'ODP-PME-FBK/33', 'ODP-PME-FBK/27'], $d['odp']);
        $this->assertSame([], $d['gpon']);
        $this->assertFalse($d['olt']);
        $this->assertFalse($this->parser->isEmpty($d));
    }

    public function test_gpon_with_ip_and_olt_rca_est(): void
    {
        $d = $this->parser->parse(
            '[SQM GAMAS] | AKSES | GPON | TIF-3 | REG-5 | UPLINK OLT FEEDER | PENYAMBUNGAN | BLEGA | '
            .'(EST 22/06/2026 15:00) | [GPON01-D5-BEA-2SRE 172.27.106.84] | OLT [SUSPECT RCA: PON PORT DOWN] | '
            .'IboosterO_1782081990946503'
        );

        $this->assertSame('gpon', $d['kategori']);
        $this->assertCount(1, $d['gpon']);
        $this->assertSame('GPON01-D5-BEA-2SRE', $d['gpon'][0]['name']);
        $this->assertSame('172.27.106.84', $d['gpon'][0]['ip']);
        $this->assertSame([], $d['gpon'][0]['ports']);
        $this->assertTrue($d['olt']);
        $this->assertSame('PON PORT DOWN', $d['rca']);
        $this->assertSame('22/06/2026 15:00', $d['est']);
        $this->assertSame([], $d['odc']);
        $this->assertSame([], $d['odp']);
    }

    public function test_short_gpon_line(): void
    {
        $d = $this->parser->parse(
            '[SQM GAMAS] [GPON01-D5-BEA-2SRE 172.27.106.84] | OLT | UPLINK OLT FEEDER | PENYAMBUNGAN | '
            .'[SUSPECT RCA: PON PORT DOWN] | IboosterO_1782275838573052'
        );

        $this->assertCount(1, $d['gpon']);
        $this->assertSame('GPON01-D5-BEA-2SRE', $d['gpon'][0]['name']);
        $this->assertSame('172.27.106.84', $d['gpon'][0]['ip']);
        $this->assertTrue($d['olt']);
        $this->assertSame('PON PORT DOWN', $d['rca']);
    }

    public function test_distribusi_with_cable_and_multiple_gpon_ports_and_pic(): void
    {
        $d = $this->parser->parse(
            'GAMAS | AKSES | DISTRIBUSI | TIF-3 | REG-5 | RABASAN | PENYAMBUNGAN | DS-SMP-FE-14-01-04/01-10 | '
            .'SUMENEP | (EST 23/06/2026 16:00) | GPON01-D5-SMP-3 [2/10, 2/11], GPON03-D5-SMP-2 [7/4] | '
            .'PIC : RANU / 082139794255'
        );

        $this->assertSame('distribusi', $d['kategori']);
        $this->assertSame(['DS-SMP-FE-14-01-04/01-10'], $d['kabel']);
        $this->assertSame([], $d['odc']);
        $this->assertSame([], $d['odp']);
        $this->assertCount(2, $d['gpon']);
        $this->assertSame('GPON01-D5-SMP-3', $d['gpon'][0]['name']);
        $this->assertNull($d['gpon'][0]['ip']);
        $this->assertSame(['2/10', '2/11'], $d['gpon'][0]['ports']);
        $this->assertSame('GPON03-D5-SMP-2', $d['gpon'][1]['name']);
        $this->assertSame(['7/4'], $d['gpon'][1]['ports']);
        $this->assertSame('23/06/2026 16:00', $d['est']);
        $this->assertSame(['nama' => 'RANU', 'telp' => '082139794255'], $d['pic']);
    }

    public function test_empty_and_plain_text_is_empty(): void
    {
        $d = $this->parser->parse(null);
        $this->assertTrue($this->parser->isEmpty($d));

        $d = $this->parser->parse('Gangguan umum, tidak ada rincian elemen jaringan.');
        $this->assertTrue($this->parser->isEmpty($d));
    }

    public function test_segment_hint_wins_over_text_scan(): void
    {
        $d = $this->parser->parse('UPLINK OLT FEEDER | [GPON01-D5-BEA-2SRE 10.0.0.1]', 'gpon');
        $this->assertSame('gpon', $d['kategori']);
    }
}
