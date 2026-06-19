<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\ProcessedOperation;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\OrderResource;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\OrderSummaryResource;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\Concerns\MapsCarrierLabelStatus;

class OrderController extends Controller
{
    use MapsCarrierLabelStatus;

    private const CHANGEABLE_STATUSES = ['paid', 'partially_paid', 'cancelled', 'waiting_for_confirmation'];

    private const CHANGEABLE_FULFILLMENT_STATUSES = ['handled', 'partially_handled', 'unhandled', 'waiting_for_supplier'];

    /**
     * Betaalstatus-opties — gelijk aan de Filament order-resource.
     */
    private const STATUS_OPTIONS = [
        'paid' => 'Betaald',
        'partially_paid' => 'Gedeeltelijk betaald',
        'waiting_for_confirmation' => 'Wachten op bevestiging',
        'pending' => 'Lopende aankoop',
        'concept' => 'Concept',
        'cancelled' => 'Geannuleerd',
        'return' => 'Retour',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::thisSite();

        // Dashboard-shortcut: alleen onafgehandelde orders.
        if ($request->boolean('unhandled')) {
            $query->unhandled();
        }

        $this->applyArrayFilter($query, 'status', $request->query('status'));
        $this->applyFulfillmentFilter($query, $request->query('fulfillment_status'));
        $this->applyArrayFilter($query, 'retour_status', $request->query('retour_status'));
        $this->applyArrayFilter($query, 'order_origin', $request->query('order_origin'));
        $this->applyArrayFilter($query, 'utm_source', $request->query('utm_source'));
        $this->applyArrayFilter($query, 'utm_medium', $request->query('utm_medium'));
        $this->applyArrayFilter($query, 'utm_campaign', $request->query('utm_campaign'));
        $this->applyArrayFilter($query, 'country', $request->query('country'));

        if ($startDate = $request->query('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->query('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        if ($search = trim((string) $request->query('search'))) {
            // Slimme zoek: splits op spaties; ELK woord moet ergens matchen (over
            // de kolommen), en ALLE woorden samen (AND). Zo vindt "Jan Jansen"
            // een order met voornaam Jan + achternaam Jansen (en omgekeerd).
            $columns = ['invoice_id', 'first_name', 'last_name', 'email', 'company_name', 'city'];
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [$search];

            $query->where(function (Builder $outer) use ($terms, $columns): void {
                foreach ($terms as $term) {
                    $outer->where(function (Builder $q) use ($term, $columns): void {
                        foreach ($columns as $column) {
                            $q->orWhere($column, 'like', "%{$term}%");
                        }
                        if (is_numeric($term)) {
                            $q->orWhere('id', (int) $term);
                        }
                    });
                }
            });
        }

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);

        return OrderSummaryResource::collection(
            $query->orderByDesc('created_at')->paginate($perPage),
        );
    }

    /**
     * Beschikbare filteropties (statisch + dynamisch per site), zodat de app de
     * filter-UI kan vullen — gelijk aan de Filament order-resource.
     */
    public function filterOptions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'statuses' => $this->mapOptions(self::STATUS_OPTIONS),
                'fulfillment_statuses' => $this->mapOptions(Orders::getFulfillmentStatusses()),
                'retour_statuses' => $this->mapOptions(Orders::getReturnStatusses()),
                'order_origins' => $this->distinctOptions('order_origin'),
                'utm_sources' => $this->distinctOptions('utm_source'),
                'utm_mediums' => $this->distinctOptions('utm_medium'),
                'utm_campaigns' => $this->distinctOptions('utm_campaign'),
                'countries' => $this->distinctOptions('country'),
            ],
        ]);
    }

    public function show(int $order): OrderResource
    {
        return $this->detail(Order::thisSite()->findOrFail($order));
    }

    /** Laadt de relaties die de detail-resource nodig heeft. */
    private function detail(Order $model): OrderResource
    {
        return new OrderResource($model->fresh()->load(['orderProducts.product', 'orderPayments', 'trackAndTraces']));
    }

    public function update(Request $request, int $order): OrderResource
    {
        $model = Order::thisSite()->findOrFail($order);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(self::CHANGEABLE_STATUSES)],
        ]);

        $model->changeStatus($data['status']);

        activity()
            ->performedOn($model)
            ->causedBy($request->user())
            ->withProperties($data)
            ->log('mobile-api: orderstatus gewijzigd');

        return $this->detail($model);
    }

    /** Markeer de bestelling als betaald (zoals Filament "Markeer als betaald"). */
    public function markAsPaid(Request $request, int $order): OrderResource
    {
        $model = Order::thisSite()->findOrFail($order);
        $model->markAsPaid();

        activity()->performedOn($model)->causedBy($request->user())->log('mobile-api: gemarkeerd als betaald');

        return $this->detail($model);
    }

    /**
     * Registreer een (gedeeltelijke) retour/RMA voor de bestelling. Hergebruikt
     * de centrale Order::registerReturn-logica (voorraad-terugboeking + status).
     *
     * Conservatief m.b.t. geld: `refund=true` zet alléén een markering (zoals het
     * CMS doet voor een nog te verwerken terugbetaling) — er wordt nooit een echte
     * PSP-terugbetaling uitgevoerd vanuit de app.
     */
    public function returnOrder(Request $request, int $order): OrderResource|JsonResponse
    {
        $model = Order::thisSite()->findOrFail($order);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.order_product_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'restock' => ['sometimes', 'boolean'],
            'refund' => ['sometimes', 'boolean'],
        ]);

        $restock = (bool) ($data['restock'] ?? true);
        $refund = (bool) ($data['refund'] ?? false);

        try {
            $result = DB::transaction(fn () => $model->registerReturn($data['lines'], $restock, $refund));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        activity()->performedOn($model)->causedBy($request->user())
            ->withProperties(['restock' => $restock, 'refund' => $refund] + $result)
            ->log('mobile-api: retour geregistreerd');

        return $this->detail($model);
    }

    /**
     * Zoek een bestelling op een gescande code: track & trace-code of factuurnummer.
     * Zo kun je in de inpak-scanner naast de pakbon-barcode ook een T&T-label scannen
     * om de juiste order te openen.
     */
    public function match(Request $request): OrderResource
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:191'],
        ]);
        $code = trim($data['code']);

        // Pakbon-/order-barcode: Code128 van 'order-<id>' (zie packing-slip.blade,
        // gelijk aan de POS-conventie) → direct op order-id.
        if (str($code)->lower()->startsWith('order-')) {
            $orderId = (int) str($code)->after('-')->trim()->toString();
            $model = $orderId > 0 ? Order::thisSite()->find($orderId) : null;
            if ($model) {
                return $this->detail($model);
            }
        }

        $model = Order::thisSite()
            ->whereHas('trackAndTraces', fn (Builder $q) => $q->where('code', $code))
            ->latest()
            ->first();

        // Terugval: factuurnummer (bijv. een gescande pakbon-/factuur-barcode).
        if (! $model) {
            $model = Order::thisSite()->where('invoice_id', $code)->latest()->first();
        }

        abort_if($model === null, 404, 'Geen bestelling gevonden voor deze code.');

        return $this->detail($model);
    }

    /** Wijzig de fulfilment-/verwerkingsstatus. */
    public function changeFulfillment(Request $request, int $order): OrderResource
    {
        $model = Order::thisSite()->findOrFail($order);

        $data = $request->validate([
            'fulfillment_status' => ['required', 'string', Rule::in(self::CHANGEABLE_FULFILLMENT_STATUSES)],
        ]);

        $model->changeFulfillmentStatus($data['fulfillment_status']);

        activity()->performedOn($model)->causedBy($request->user())->withProperties($data)->log('mobile-api: fulfilment-status gewijzigd');

        return $this->detail($model);
    }

    /** Factuur-PDF-URL (genereert 'm indien nodig). */
    public function invoiceUrl(int $order): JsonResponse
    {
        $model = Order::thisSite()->findOrFail($order);

        return response()->json(['url' => $model->downloadInvoiceUrl()]);
    }

    /** Pakbon-PDF-URL (genereert 'm indien nodig). */
    public function packingSlipUrl(int $order): JsonResponse
    {
        $model = Order::thisSite()->findOrFail($order);

        return response()->json(['url' => $model->downloadPackingslipUrl()]);
    }

    /** Verzendlabel-PDF-URL van de fulfillment-integratie (MyParcel/Veloyd), indien aangemaakt. */
    public function labelUrl(int $order): JsonResponse
    {
        $model = Order::thisSite()->findOrFail($order);

        return response()->json(['url' => $this->firstLabelPublicUrl($model)]);
    }

    /**
     * Lijst van verzendlabels voor deze order (zoals Filament toont): per
     * vervoerder elke zending met track&trace en of er al een PDF is.
     */
    public function labels(int $order): JsonResponse
    {
        $model = Order::thisSite()->findOrFail($order);
        $labels = [];

        if (class_exists(\Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::class)) {
            foreach (\Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::where('order_id', $model->id)
                ->whereNotNull('shipment_id')->latest()->get() as $mp) {
                $st = $this->labelStatus($mp);
                $labels[] = [
                    'id' => (int) $mp->id,
                    'carrier' => 'myparcel',
                    'carrier_name' => $mp->carrier ?: 'MyParcel',
                    'track_trace' => $this->trackTraceString($mp->track_and_trace),
                    'has_pdf' => (bool) $mp->label_pdf_path,
                    'status' => $st['key'],
                    'status_label' => $st['label'],
                    'status_tone' => $st['tone'],
                    'created_at' => optional($mp->created_at)->toIso8601String(),
                ];
            }
        }

        if (class_exists(\Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::class)) {
            foreach (\Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::where('order_id', $model->id)
                ->whereNotNull('shipment_id')->latest()->get() as $v) {
                $st = $this->labelStatus($v);
                $labels[] = [
                    'id' => (int) $v->id,
                    'carrier' => 'veloyd',
                    'carrier_name' => $v->carrier ?: 'Veloyd',
                    'track_trace' => $this->trackTraceString($v->track_and_trace),
                    'has_pdf' => (bool) $v->label_pdf_path,
                    'status' => $st['key'],
                    'status_label' => $st['label'],
                    'status_tone' => $st['tone'],
                    'created_at' => optional($v->created_at)->toIso8601String(),
                ];
            }
        }

        return response()->json([
            'data' => $labels,
            'providers' => $this->labelProviders($model),
        ]);
    }

    /**
     * Maak een verzendlabel aan via de geconfigureerde provider (MyParcel/Veloyd)
     * met de standaard carrier/pakkettype per land. Optioneel `provider` om te
     * forceren. Geeft de bijgewerkte labellijst terug.
     */
    public function createLabel(Request $request, int $order): JsonResponse
    {
        $model = Order::thisSite()->findOrFail($order);

        $result = $this->attemptCreateLabel(
            $model,
            (string) $request->input('provider', ''),
            $this->labelOverrides($request->all()),
        );

        if ($result['ok']) {
            return response()->json(['success' => true, 'provider' => $result['provider'], 'message' => $result['message']]);
        }

        return response()->json(['success' => false, 'message' => $result['message']], 422);
    }

    /**
     * De label-keuzes (vervoerder/pakkettype/verzendtype) uit het verzoek; lege
     * waarden vallen terug op de standaard per land in de provider-classes.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function labelOverrides(array $input): array
    {
        return array_filter([
            'carrier' => $input['carrier'] ?? null,
            'package_type' => $input['package_type'] ?? null,
            'delivery_type' => $input['delivery_type'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Maak één verzendlabel aan via de geconfigureerde provider (Veloyd/MyParcel).
     * Gedeeld door het single- en het bulk-endpoint, zodat de business-logica
     * (provider-keuze + carrier-call) maar op één plek staat.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{ok: bool, provider: ?string, message: string}
     */
    private function attemptCreateLabel(Order $model, string $provider, array $overrides): array
    {
        $errors = [];

        if (($provider === '' || $provider === 'veloyd')
            && $this->veloydConfigured($model)) {
            try {
                \Dashed\DashedEcommerceVeloyd\Classes\Veloyd::createLabelForOrder($model, $overrides);

                return ['ok' => true, 'provider' => 'veloyd', 'message' => 'Verzendlabel aangemaakt via Veloyd.'];
            } catch (\Throwable $e) {
                report($e);
                $errors[] = 'Veloyd: ' . $e->getMessage();
            }
        }

        if (($provider === '' || $provider === 'myparcel')
            && $this->myparcelConfigured($model)) {
            try {
                \Dashed\DashedEcommerceMyParcel\Classes\MyParcel::createLabelForOrder($model, $overrides);

                return ['ok' => true, 'provider' => 'myparcel', 'message' => 'Verzendlabel aangemaakt via MyParcel.'];
            } catch (\Throwable $e) {
                report($e);
                $errors[] = 'MyParcel: ' . $e->getMessage();
            }
        }

        return [
            'ok' => false,
            'provider' => null,
            'message' => $errors ? implode(' ', $errors) : 'Geen verzendprovider geconfigureerd voor deze site.',
        ];
    }

    // ── Bulk-acties ──────────────────────────────────────────────────────────
    //
    // Dezelfde per-order logica als de single-endpoints hierboven, maar in een
    // loop over een lijst id's. Elke order wordt site-scoped opgehaald en in een
    // eigen DB-transactie verwerkt; een fout bij één order zet die op ok:false
    // (met error-melding) maar laat de rest doorlopen (partial success).

    /** Bulk: wijzig de betaalstatus van meerdere orders. */
    public function bulkStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
            'status' => ['required', 'string', Rule::in(self::CHANGEABLE_STATUSES)],
        ]);

        return $this->runBulk($request, $data['ids'], function (Order $model) use ($data): void {
            $model->changeStatus($data['status']);
        }, ['status' => $data['status']], 'mobile-api: bulk orderstatus gewijzigd');
    }

    /** Bulk: wijzig de fulfilment-/verwerkingsstatus van meerdere orders. */
    public function bulkFulfillment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
            'fulfillment_status' => ['required', 'string', Rule::in(self::CHANGEABLE_FULFILLMENT_STATUSES)],
        ]);

        return $this->runBulk($request, $data['ids'], function (Order $model) use ($data): void {
            $model->changeFulfillmentStatus($data['fulfillment_status']);
        }, ['fulfillment_status' => $data['fulfillment_status']], 'mobile-api: bulk fulfilment-status gewijzigd');
    }

    /** Bulk: maak verzendlabels aan voor meerdere orders (sequentieel; mag deels falen). */
    public function bulkCreateLabel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
            'provider' => ['sometimes', 'nullable', 'string'],
            'carrier' => ['sometimes', 'nullable', 'string'],
            'package_type' => ['sometimes', 'nullable', 'string'],
            'delivery_type' => ['sometimes', 'nullable', 'string'],
        ]);

        $provider = (string) ($data['provider'] ?? '');
        $overrides = $this->labelOverrides($data);

        return $this->runBulk($request, $data['ids'], function (Order $model) use ($provider, $overrides): void {
            $result = $this->attemptCreateLabel($model, $provider, $overrides);
            if (! $result['ok']) {
                // Gooi door zodat runBulk dit als ok:false met de melding registreert.
                throw new \RuntimeException($result['message']);
            }
        }, [], 'mobile-api: bulk verzendlabel aangemaakt');
    }

    /**
     * Voer een per-order mutatie uit over een lijst id's. Per id: site-scoped
     * ophalen (niet gevonden → ok:false), in een eigen transactie de callback
     * draaien, fouten vangen → ok:false met melding (partial success).
     *
     * @param  array<int, int>  $ids
     * @param  callable(Order): void  $mutate
     * @param  array<string, mixed>  $logProperties
     * @return JsonResponse
     */
    private function runBulk(Request $request, array $ids, callable $mutate, array $logProperties, string $logMessage): JsonResponse
    {
        $results = [];
        $okCount = 0;
        $failCount = 0;

        foreach (array_values(array_unique(array_map('intval', $ids))) as $id) {
            $model = Order::thisSite()->find($id);

            if (! $model) {
                $results[] = ['id' => $id, 'ok' => false, 'error' => 'Bestelling niet gevonden.'];
                $failCount++;

                continue;
            }

            try {
                DB::transaction(fn () => $mutate($model));

                activity()->performedOn($model)->causedBy($request->user())
                    ->withProperties($logProperties)->log($logMessage);

                $results[] = ['id' => $id, 'ok' => true, 'error' => null];
                $okCount++;
            } catch (\Throwable $e) {
                report($e);
                $results[] = ['id' => $id, 'ok' => false, 'error' => $e->getMessage()];
                $failCount++;
            }
        }

        return response()->json([
            'results' => $results,
            'ok_count' => $okCount,
            'fail_count' => $failCount,
        ]);
    }

    /** Welke label-providers zijn (op basis van de API-sleutel) beschikbaar. */
    private function labelProviders(Order $model): array
    {
        $providers = [];

        if ($this->veloydConfigured($model)) {
            $providers[] = 'veloyd';
        }
        if ($this->myparcelConfigured($model)) {
            $providers[] = 'myparcel';
        }

        return $providers;
    }

    /**
     * Is Veloyd voor de site van deze order geconfigureerd?
     *
     * Leest de API-sleutel via de provider-class zelf (die met `disableCache: true`
     * leest), zodat deze check NOOIT op een verouderde settings-cache draait. Dat
     * voorkomt de situatie waarin het CMS (dat ook vers leest) wél een label kan
     * maken maar de app "Geen verzendprovider geconfigureerd" terugkrijgt.
     */
    private function veloydConfigured(Order $model): bool
    {
        return class_exists(\Dashed\DashedEcommerceVeloyd\Classes\Veloyd::class)
            && \Dashed\DashedEcommerceVeloyd\Classes\Veloyd::apiKey($model->site_id) !== '';
    }

    /** Idem voor MyParcel; `false` haalt de onbewerkte (niet-base64) sleutel op. */
    private function myparcelConfigured(Order $model): bool
    {
        return class_exists(\Dashed\DashedEcommerceMyParcel\Classes\MyParcel::class)
            && \Dashed\DashedEcommerceMyParcel\Classes\MyParcel::apiKey($model->site_id, false) !== '';
    }

    /**
     * Keuze-opties (vervoerder / pakkettype / verzendtype) per beschikbare provider,
     * met de standaard-waarde per land — exact dezelfde opties als in het CMS-formulier.
     * De app toont deze vóór het aanmaken van een verzendlabel.
     */
    public function labelOptions(int $order): JsonResponse
    {
        $model = Order::thisSite()->findOrFail($order);
        $country = $model->countryIsoCode;
        $available = $this->labelProviders($model);
        $cs = '\Dashed\DashedCore\Models\Customsetting';
        $out = [];

        if (in_array('veloyd', $available, true)) {
            $v = '\Dashed\DashedEcommerceVeloyd\Classes\Veloyd';
            $out[] = [
                'provider' => 'veloyd',
                'label' => 'Veloyd',
                'fields' => [
                    $this->labelSelectField('carrier', 'Vervoerder', $v::getCarriers(), $cs::get("veloyd_default_carrier_{$country}", $model->site_id, 'PostNL')),
                    $this->labelSelectField('package_type', 'Pakkettype', $v::getPackageTypes(), $cs::get("veloyd_default_package_type_{$country}", $model->site_id, '1')),
                    $this->labelSelectField('delivery_type', 'Verzendtype', $v::getDeliveryTypes(), $cs::get("veloyd_default_delivery_type_{$country}", $model->site_id, 'Standaard')),
                ],
            ];
        }

        if (in_array('myparcel', $available, true)) {
            $m = '\Dashed\DashedEcommerceMyParcel\Classes\MyParcel';
            $out[] = [
                'provider' => 'myparcel',
                'label' => 'MyParcel',
                'fields' => [
                    $this->labelSelectField('carrier', 'Vervoerder', $m::getCarriers(), $cs::get("my_parcel_default_carrier_{$country}", $model->site_id)),
                    $this->labelSelectField('package_type', 'Pakkettype', $m::getPackageTypes(), $cs::get("my_parcel_default_package_type_{$country}", $model->site_id, '1')),
                    $this->labelSelectField('delivery_type', 'Verzendtype', $m::getDeliveryTypes(), $cs::get("my_parcel_default_delivery_type_{$country}", $model->site_id, '2')),
                ],
            ];
        }

        return response()->json(['providers' => $out]);
    }

    /**
     * @param  array<int|string, string>  $options  value => label
     * @return array<string, mixed>
     */
    private function labelSelectField(string $name, string $label, array $options, mixed $default): array
    {
        $opts = [];
        foreach ($options as $value => $text) {
            $opts[] = ['value' => (string) $value, 'label' => (string) $text];
        }

        return [
            'name' => $name,
            'label' => $label,
            'type' => 'select',
            'required' => true,
            'options' => $opts,
            'default' => $default === null ? null : (string) $default,
        ];
    }

    /** Eerste beschikbare label als publieke PDF-URL (MyParcel zo nodig on-demand). */
    private function firstLabelPublicUrl(Order $model): ?string
    {
        if (class_exists(\Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::class)) {
            $mp = \Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::where('order_id', $model->id)
                ->whereNotNull('shipment_id')->latest()->first();
            if ($mp) {
                $path = $mp->label_pdf_path;
                if ((! $path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($path))
                    && class_exists(\Dashed\DashedEcommerceMyParcel\Classes\MyParcel::class)) {
                    try {
                        $path = \Dashed\DashedEcommerceMyParcel\Classes\MyParcel::downloadLabelForOrder($mp);
                    } catch (\Throwable $e) {
                        report($e);
                        $path = null;
                    }
                }
                if ($path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    // Label gedownload via de app → uit de wachtrij halen.
                    if (! $mp->label_printed) {
                        $mp->forceFill(['label_printed' => 1])->save();
                    }

                    return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
                }
            }
        }

        if (class_exists(\Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::class)) {
            $v = \Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::where('order_id', $model->id)
                ->whereNotNull('shipment_id')->latest()->first();
            if ($v) {
                $path = $v->label_pdf_path;
                if ((! $path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($path))
                    && class_exists(\Dashed\DashedEcommerceVeloyd\Classes\Veloyd::class)) {
                    try {
                        $path = \Dashed\DashedEcommerceVeloyd\Classes\Veloyd::downloadLabelForOrder($v);
                    } catch (\Throwable $e) {
                        report($e);
                        $path = null;
                    }
                }
                if ($path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    // Label gedownload via de app → uit de wachtrij halen.
                    if (! $v->label_printed) {
                        $v->forceFill(['label_printed' => 1])->save();
                    }

                    return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
                }
            }
        }

        return null;
    }

    /** Maakt van het track_and_trace-veld (array of string) een leesbare string. */
    private function trackTraceString($tt): ?string
    {
        if (blank($tt)) {
            return null;
        }
        if (is_string($tt)) {
            return $tt;
        }

        $codes = [];
        foreach ((array) $tt as $entry) {
            if (is_array($entry)) {
                foreach ($entry as $code => $url) {
                    $codes[] = (string) $code;
                }
            } elseif (is_string($entry)) {
                $codes[] = $entry;
            }
        }

        return $codes ? implode(', ', array_filter($codes)) : null;
    }

    /** Voeg een notitie/orderlog toe (optioneel zichtbaar voor de klant). */
    public function addNote(Request $request, int $order): OrderResource
    {
        $model = Order::thisSite()->findOrFail($order);

        $data = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
            'public_for_customer' => ['sometimes', 'boolean'],
        ]);

        \Dashed\DashedEcommerceCore\Models\OrderLog::createLog(
            orderId: $model->id,
            tag: 'note.created',
            note: $data['note'],
            publicForCustomer: (bool) ($data['public_for_customer'] ?? false),
        );

        return $this->detail($model);
    }

    /**
     * Print de pakbon of het verzendlabel naar de geconfigureerde netwerk-/CUPS-
     * printer door een PrintJob in de wachtrij te zetten (de printer-worker pakt
     * 'm op). Vereist een actieve printer van het juiste type.
     */
    public function print(Request $request, int $order): JsonResponse
    {
        $model = Order::thisSite()->findOrFail($order);

        $data = $request->validate([
            'type' => ['required', Rule::in(['packing_slip', 'shipping_label', 'invoice'])],
            'printer_id' => ['sometimes', 'nullable', 'integer'],
            // Optioneel: print een specifiek label (uit de labellijst).
            'carrier' => ['sometimes', 'nullable', Rule::in(['myparcel', 'veloyd'])],
            'label_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $jobType = match ($data['type']) {
            'shipping_label' => \Dashed\DashedEcommerceCore\Enums\PrintJobType::ShippingLabel,
            'invoice' => \Dashed\DashedEcommerceCore\Enums\PrintJobType::Invoice,
            default => \Dashed\DashedEcommerceCore\Enums\PrintJobType::PackingSlip,
        };
        // Facturen én pakbonnen gaan naar dezelfde A4-document-printer
        // (PrinterType::PackingSlip/Both); alleen verzendlabels hebben een eigen type.
        $printerType = $data['type'] === 'shipping_label'
            ? \Dashed\DashedEcommerceCore\Enums\PrinterType::ShippingLabel
            : \Dashed\DashedEcommerceCore\Enums\PrinterType::PackingSlip;

        // Een specifiek gekozen printer (uit de app) → de job gaat exact daarheen.
        // Anders: een actieve printer van het juiste type (type-routing).
        $printerId = $data['printer_id'] ?? null;
        if ($printerId) {
            $printer = \Dashed\DashedEcommerceCore\Models\Printer::active()->whereKey($printerId)->first();
            if (! $printer) {
                return response()->json(['success' => false, 'message' => 'De gekozen printer is niet (meer) actief.'], 422);
            }
        } else {
            $hasPrinter = \Dashed\DashedEcommerceCore\Models\Printer::active()
                ->whereIn('type', [$printerType->value, \Dashed\DashedEcommerceCore\Enums\PrinterType::Both->value])
                ->exists();

            if (! $hasPrinter) {
                return response()->json(['success' => false, 'message' => 'Geen actieve ' . strtolower($jobType->label()) . '-printer geconfigureerd.'], 422);
            }
        }

        // Optioneel een specifiek label targeten (uit de labellijst), zodat het
        // juiste label wordt opgehaald i.p.v. het nieuwste van de order.
        $printableType = null;
        $printableId = null;
        if ($jobType === \Dashed\DashedEcommerceCore\Enums\PrintJobType::ShippingLabel
            && ! empty($data['carrier']) && ! empty($data['label_id'])) {
            $map = [
                'myparcel' => \Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::class,
                'veloyd' => \Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::class,
            ];
            $cls = $map[$data['carrier']] ?? null;
            if ($cls && class_exists($cls)) {
                $row = $cls::where('order_id', $model->id)->whereKey((int) $data['label_id'])->first();
                if ($row) {
                    $printableType = $cls;
                    $printableId = (int) $row->id;
                    // Label via de app geprint → uit de wachtrij halen.
                    if (! $row->label_printed) {
                        $row->forceFill(['label_printed' => 1])->save();
                    }
                }
            }
        }

        \Dashed\DashedEcommerceCore\Models\PrintJob::create([
            'type' => $jobType,
            'order_id' => $model->id,
            'printer_id' => $printerId,
            'printable_type' => $printableType,
            'printable_id' => $printableId,
            'status' => \Dashed\DashedEcommerceCore\Enums\PrintJobStatus::Pending,
        ]);

        activity()->performedOn($model)->causedBy($request->user())->withProperties($data)->log('mobile-api: ' . strtolower($jobType->label()) . ' geprint');

        return response()->json(['success' => true, 'message' => $jobType->label() . ' naar de printer gestuurd.']);
    }

    /**
     * Markeer een order als ingepakt (of maak dat ongedaan). Wordt gebruikt door
     * de Pick & Pack-flow: scan de pakbon → pak in → scan het label → ingepakt.
     */
    public function packed(Request $request, int $order): OrderResource
    {
        $model = Order::thisSite()->findOrFail($order);

        $data = $request->validate([
            'packed' => ['sometimes', 'boolean'],
            // Client-gegenereerd operatie-id voor idempotente (offline) sync.
            'op_id' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);
        $packed = $data['packed'] ?? true;
        $opId = isset($data['op_id']) ? (string) $data['op_id'] : null;

        // Idempotentie: draagt de actie een op_id, dan voeren we 'm maar één keer
        // uit. Een replay (offline sync die per ongeluk twee keer afspeelt) is een
        // no-op die de order ongewijzigd teruggeeft. Zonder op_id: gedrag als vroeger.
        ProcessedOperation::once($opId, function () use ($request, $model, $packed): array {
            $model->forceFill(['packed_at' => $packed ? now() : null])->save();

            // Houd de fulfillment-status in het CMS in sync met de inpak-actie:
            // inpakken → 'packed' (Ingepakt); inpakken ongedaan maken → terug naar
            // 'unhandled', maar alléén als de order nog op 'packed' staat (zo
            // overschrijven we geen latere status zoals 'shipped'). changeFulfillmentStatus
            // is idempotent en verstuurt de geconfigureerde fulfillment-mail + event.
            if ($packed) {
                $model->changeFulfillmentStatus('packed');
            } elseif ($model->fulfillment_status === 'packed') {
                $model->changeFulfillmentStatus('unhandled');
            }

            \Dashed\DashedEcommerceCore\Models\OrderLog::createLog(
                orderId: $model->id,
                tag: $packed ? 'order.packed' : 'order.unpacked',
            );

            activity()->performedOn($model)->causedBy($request->user())
                ->log('mobile-api: order ' . ($packed ? 'ingepakt' : 'inpakken ongedaan gemaakt'));

            return ['id' => $model->id, 'packed' => $packed];
        });

        return $this->detail($model->fresh());
    }

    /**
     * Print pakbon én label in één keer (op type-routing naar de actieve printers).
     * Dedup: maakt geen nieuwe job aan als er voor de order al een onafgeronde job
     * van dat type in de wachtrij staat — geen dubbele jobs.
     */
    public function printDocuments(int $order): JsonResponse
    {
        $model = Order::thisSite()->findOrFail($order);
        $queued = [];

        if ($this->activePrinterForType(\Dashed\DashedEcommerceCore\Enums\PrinterType::PackingSlip)
            && $this->queueJobOnce($model, \Dashed\DashedEcommerceCore\Enums\PrintJobType::PackingSlip)) {
            $queued[] = 'pakbon';
        }

        if ($this->orderHasShippingLabel($model)
            && $this->activePrinterForType(\Dashed\DashedEcommerceCore\Enums\PrinterType::ShippingLabel)
            && $this->queueJobOnce($model, \Dashed\DashedEcommerceCore\Enums\PrintJobType::ShippingLabel)) {
            $queued[] = 'label';
        }

        return response()->json([
            'success' => ! empty($queued),
            'queued' => $queued,
            'message' => $queued
                ? 'Naar de printer gestuurd: ' . implode(' + ', $queued)
                : 'Er staat al een job in de wachtrij, of er is geen geschikte printer/label.',
        ]);
    }

    /** Is er een actieve printer van dit type (of "beide")? */
    private function activePrinterForType(\Dashed\DashedEcommerceCore\Enums\PrinterType $type): bool
    {
        return \Dashed\DashedEcommerceCore\Models\Printer::active()
            ->whereIn('type', [$type->value, \Dashed\DashedEcommerceCore\Enums\PrinterType::Both->value])
            ->exists();
    }

    /** Heeft de order een verzendlabel (MyParcel-shipment of Veloyd-PDF)? */
    private function orderHasShippingLabel(Order $model): bool
    {
        $myParcel = class_exists(\Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::class)
            && \Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::where('order_id', $model->id)
                ->whereNotNull('shipment_id')->exists();

        $veloyd = class_exists(\Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::class)
            && \Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::where('order_id', $model->id)
                ->whereNotNull('shipment_id')->exists();

        return $myParcel || $veloyd;
    }

    /**
     * Zet één job van dit type in de wachtrij, tenzij er al een onafgeronde
     * (pending/claimed/printing) job van dat type voor de order staat. Geeft
     * terug of er een nieuwe job is aangemaakt.
     */
    private function queueJobOnce(Order $model, \Dashed\DashedEcommerceCore\Enums\PrintJobType $type): bool
    {
        $exists = \Dashed\DashedEcommerceCore\Models\PrintJob::where('order_id', $model->id)
            ->where('type', $type->value)
            ->whereIn('status', [
                \Dashed\DashedEcommerceCore\Enums\PrintJobStatus::Pending->value,
                \Dashed\DashedEcommerceCore\Enums\PrintJobStatus::Claimed->value,
                \Dashed\DashedEcommerceCore\Enums\PrintJobStatus::Printing->value,
            ])
            ->exists();

        if ($exists) {
            return false;
        }

        \Dashed\DashedEcommerceCore\Models\PrintJob::create([
            'type' => $type,
            'order_id' => $model->id,
            'status' => \Dashed\DashedEcommerceCore\Enums\PrintJobStatus::Pending,
        ]);

        return true;
    }

    /**
     * De order-acties (zoals op de Filament ViewOrder-pagina) die nú beschikbaar
     * zijn voor deze bestelling — dynamisch uit de registry, met per-order
     * opgeloste velden/standaardwaarden.
     */
    public function actions(int $order): JsonResponse
    {
        $model = Order::thisSite()->findOrFail($order);
        $registry = app(\Dashed\DashedMobileApi\MobileApiRegistry::class);

        $out = [];
        foreach ($registry->orderActions() as $action) {
            $visible = $action['visible'] ?? null;
            if (is_callable($visible) && ! $visible($model)) {
                continue;
            }

            $out[] = [
                'key' => $action['key'],
                'label' => $action['label'],
                'group' => $action['group'] ?? 'Acties',
                'icon' => $action['icon'] ?? 'ellipsis-horizontal',
                'destructive' => (bool) ($action['destructive'] ?? false),
                'confirm' => $action['confirm'] ?? null,
                'fields' => array_map(function (array $field) use ($model) {
                    $options = $field['options'] ?? null;
                    $options = is_callable($options) ? $options($model) : $options;
                    $default = $field['default'] ?? null;
                    $default = is_callable($default) ? $default($model) : $default;

                    return [
                        'name' => $field['name'],
                        'label' => $field['label'],
                        'type' => $field['type'] ?? 'text',
                        'required' => (bool) ($field['required'] ?? false),
                        // Selecties als lijst van {value,label} voor de app.
                        'options' => is_array($options)
                            ? array_map(fn ($value, $label) => ['value' => (string) $value, 'label' => (string) $label], array_keys($options), array_values($options))
                            : null,
                        'default' => $default,
                    ];
                }, $action['fields'] ?? []),
            ];
        }

        return response()->json(['data' => $out]);
    }

    /** Voer een geregistreerde order-actie uit. */
    public function runAction(Request $request, int $order, string $key): OrderResource|JsonResponse
    {
        $model = Order::thisSite()->findOrFail($order);
        $registry = app(\Dashed\DashedMobileApi\MobileApiRegistry::class);
        $action = $registry->orderAction($key);

        if (! $action) {
            return response()->json(['message' => 'Onbekende actie.'], 404);
        }

        $visible = $action['visible'] ?? null;
        if (is_callable($visible) && ! $visible($model)) {
            return response()->json(['message' => 'Deze actie is niet beschikbaar voor deze bestelling.'], 422);
        }

        // Validatieregels uit de velddefinities.
        $rules = [];
        foreach ($action['fields'] ?? [] as $field) {
            $type = $field['type'] ?? 'text';
            if ($type === 'checkbox') {
                $rules[$field['name']] = ['sometimes', 'boolean'];

                continue;
            }
            $rule = [($field['required'] ?? false) ? 'required' : 'nullable'];
            if ($type === 'number') {
                $rule[] = 'numeric';
            } elseif ($type === 'email') {
                $rule[] = 'email';
            } else {
                $rule[] = 'string';
            }
            $rules[$field['name']] = $rule;
        }
        $data = $request->validate($rules);

        $handle = $action['handle'] ?? null;
        if (is_callable($handle)) {
            $handle($model, $data);
        }

        activity()->performedOn($model)->causedBy($request->user())->withProperties(['action' => $key])->log('mobile-api: order-actie ' . $key);

        return $this->detail($model->fresh());
    }

    private function applyArrayFilter(Builder $query, string $column, mixed $value): void
    {
        $values = $this->toList($value);
        if ($values) {
            $query->whereIn($column, $values);
        }
    }

    private function applyFulfillmentFilter(Builder $query, mixed $value): void
    {
        $values = $this->toList($value);
        if (! $values) {
            return;
        }

        // 'unhandled_virtual' (en de widget-shortcut 'unhandled') = alles behalve afgehandeld.
        if (in_array('unhandled_virtual', $values, true)) {
            $query->whereNotIn('fulfillment_status', ['handled', 'partially_handled']);

            return;
        }

        $query->whereIn('fulfillment_status', $values);
    }

    /**
     * @return array<int, string>
     */
    private function toList(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && $value !== '') {
            $items = explode(',', $value);
        } else {
            return [];
        }

        return array_values(array_filter(array_map('trim', $items), fn ($v) => $v !== ''));
    }

    /**
     * @param array<string, string> $map
     * @return array<int, array{value: string, label: string}>
     */
    private function mapOptions(array $map): array
    {
        $out = [];
        foreach ($map as $value => $label) {
            $out[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return $out;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function distinctOptions(string $column): array
    {
        return Order::thisSite()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn ($v) => ['value' => (string) $v, 'label' => ucfirst((string) $v)])
            ->values()
            ->all();
    }
}
