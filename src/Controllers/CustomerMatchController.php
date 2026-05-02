<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Dashed\DashedEcommerceCore\Models\CustomerMatchEndpoint;
use Dashed\DashedEcommerceCore\Models\CustomerMatchAccessLog;
use Dashed\DashedEcommerceCore\Services\CustomerMatch\CustomerMatchExporter;

class CustomerMatchController extends Controller
{
    public function __construct(
        private readonly CustomerMatchExporter $exporter,
    ) {
    }

    public function export(Request $request): StreamedResponse
    {
        /** @var CustomerMatchEndpoint $endpoint */
        $endpoint = $request->attributes->get('customer_match_endpoint');

        $exporter = $this->exporter;
        $rowCount = 0;

        $response = new StreamedResponse(function () use ($endpoint, $exporter, &$rowCount): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $exporter->header());

            foreach ($exporter->rows($endpoint) as $row) {
                fputcsv($handle, array_values($row));
                $rowCount++;

                if (($rowCount % 500) === 0) {
                    flush();
                }
            }

            fclose($handle);

            CustomerMatchAccessLog::create([
                'customer_match_endpoint_id' => $endpoint->id,
                'slug' => $endpoint->slug,
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 512),
                'status' => 200,
                'row_count' => $rowCount,
                'created_at' => now(),
            ]);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf(
            'attachment; filename="customer-match-%s.csv"',
            now()->format('Y-m-d'),
        ));
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
