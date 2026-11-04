<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Classes\PrintQueue\LabelPdfOrientation;

/**
 * Veloyd levert sinds 1 november 2026 liggende A6-labels (420x298 pt) aan; de
 * daemon print zonder oriëntatieopties, dus het label moet staand bij hem
 * aankomen.
 */
function labelTestPdf(float $w, float $h, int $pages = 1): string
{
    $base = dirname((new ReflectionClass(\LynX39\LaraPdfMerger\PdfManage::class))->getFileName());
    require_once $base . '/tcpdf/tcpdf.php';
    require_once $base . '/tcpdf/tcpdi.php';

    $pdf = new TCPDF($w > $h ? 'L' : 'P', 'pt', [$w, $h]);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    for ($i = 0; $i < $pages; $i++) {
        $pdf->AddPage($w > $h ? 'L' : 'P', [$w, $h]);
        $pdf->SetFont('helvetica', '', 20);
        $pdf->Text(20, 20, 'TOP LEFT ' . ($i + 1));
    }

    return $pdf->Output('t.pdf', 'S');
}

function labelPageSizes(string $pdf): array
{
    preg_match_all('/\/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $m, PREG_SET_ORDER);

    return array_map(fn ($b) => [round($b[3] - $b[1]), round($b[4] - $b[2])], $m);
}

it('turns a landscape A6 label into a portrait A6 page', function () {
    $out = LabelPdfOrientation::portrait(labelTestPdf(420, 298));

    expect(labelPageSizes($out))->toBe([[298.0, 420.0]]);
});

it('rotates every landscape page of a multi-page label', function () {
    $out = LabelPdfOrientation::portrait(labelTestPdf(420, 298, 2));

    expect(labelPageSizes($out))->toBe([[298.0, 420.0], [298.0, 420.0]]);
});

it('passes a portrait label through byte for byte', function () {
    $pdf = labelTestPdf(298, 420);

    expect(LabelPdfOrientation::portrait($pdf))->toBe($pdf);
});

it('passes something that is not a PDF through unchanged', function () {
    expect(LabelPdfOrientation::portrait('geen pdf'))->toBe('geen pdf');
});

it('serves a stored landscape shipping label to the daemon in portrait', function () {
    Storage::fake('dashed');
    Storage::disk('dashed')->put('labels/l.pdf', labelTestPdf(420, 298));

    $printer = Printer::factory()->create(['type' => PrinterType::ShippingLabel, 'is_active' => true]);
    $job = PrintJob::factory()->create([
        'type' => PrintJobType::ShippingLabel,
        'status' => PrintJobStatus::Claimed,
        'pdf_disk' => 'dashed',
        'pdf_path' => 'labels/l.pdf',
    ]);
    $token = $printer->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->get("/api/print/{$job->ulid}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(labelPageSizes($response->getContent()))->toBe([[298.0, 420.0]]);
});

it('leaves a packing slip as it is', function () {
    Storage::fake('dashed');
    $pdf = labelTestPdf(842, 595);
    Storage::disk('dashed')->put('slips/s.pdf', $pdf);

    $printer = Printer::factory()->create(['type' => PrinterType::PackingSlip, 'is_active' => true]);
    $job = PrintJob::factory()->create([
        'type' => PrintJobType::PackingSlip,
        'status' => PrintJobStatus::Claimed,
        'pdf_disk' => 'dashed',
        'pdf_path' => 'slips/s.pdf',
    ]);
    $token = $printer->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->get("/api/print/{$job->ulid}/pdf")
        ->assertOk();

    expect($response->streamedContent())->toBe($pdf);
});
