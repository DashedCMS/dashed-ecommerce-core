<?php

namespace Dashed\DashedEcommerceCore\Mail\Concerns;

use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Hangt de factuur van een bestelling aan vanaf de dashed-schijf.
 *
 * Facturen staan prive op die schijf (zie InvoiceAccess), dus de publieke URL
 * van de schijf geeft een 403. Symfony leest een pad-bijlage pas bij het
 * verzenden met file_get_contents(), en Laravels foutafhandelaar laat
 * error_get_last() dan leeg, zodat er alleen "Trying to access array offset
 * on null" in TextPart.php overblijft. Lezen via de schijf-API heeft daar
 * geen last van. Zonder factuur op de schijf gaat de mail zonder bijlage;
 * de ondertekende downloadlink in de mail maakt hem dan alsnog aan.
 */
trait AttachesInvoice
{
    protected function attachInvoiceFromDisk(Order $order): static
    {
        $path = ltrim((string) $order->invoicePath(), '/');

        if (! $path || ! Storage::disk('dashed')->exists($path)) {
            return $this;
        }

        return $this->attachFromStorageDisk('dashed', $path, Customsetting::get('site_name') . ' - ' . $order->invoice_id . '.pdf', [
            'mime' => 'application/pdf',
        ]);
    }
}
