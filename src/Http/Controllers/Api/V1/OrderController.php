<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\OrderResource;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\OrderSummaryResource;

class OrderController extends Controller
{
    private const CHANGEABLE_STATUSES = ['paid', 'partially_paid', 'cancelled', 'waiting_for_confirmation'];

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
            $query->where(function (Builder $q) use ($search): void {
                foreach (['invoice_id', 'first_name', 'last_name', 'email', 'company_name', 'city'] as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
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
            'fulfillment_status' => ['required', 'string', Rule::in(['handled', 'partially_handled', 'unhandled', 'waiting_for_supplier'])],
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
                $labels[] = [
                    'id' => (int) $mp->id,
                    'carrier' => 'myparcel',
                    'carrier_name' => $mp->carrier ?: 'MyParcel',
                    'track_trace' => $this->trackTraceString($mp->track_and_trace),
                    'has_pdf' => (bool) $mp->label_pdf_path,
                    'created_at' => optional($mp->created_at)->toIso8601String(),
                ];
            }
        }

        if (class_exists(\Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::class)) {
            foreach (\Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::where('order_id', $model->id)
                ->whereNotNull('shipment_id')->latest()->get() as $v) {
                $labels[] = [
                    'id' => (int) $v->id,
                    'carrier' => 'veloyd',
                    'carrier_name' => $v->carrier ?: 'Veloyd',
                    'track_trace' => $this->trackTraceString($v->track_and_trace),
                    'has_pdf' => (bool) $v->label_pdf_path,
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
        $provider = (string) $request->input('provider', '');
        $errors = [];

        if (($provider === '' || $provider === 'veloyd')
            && class_exists(\Dashed\DashedEcommerceVeloyd\Classes\Veloyd::class)
            && \Dashed\DashedCore\Models\Customsetting::get('veloyd_api_key', $model->site_id)) {
            try {
                \Dashed\DashedEcommerceVeloyd\Classes\Veloyd::createLabelForOrder($model);

                return response()->json(['success' => true, 'provider' => 'veloyd', 'message' => 'Verzendlabel aangemaakt via Veloyd.']);
            } catch (\Throwable $e) {
                report($e);
                $errors[] = 'Veloyd: ' . $e->getMessage();
            }
        }

        if (($provider === '' || $provider === 'myparcel')
            && class_exists(\Dashed\DashedEcommerceMyParcel\Classes\MyParcel::class)
            && \Dashed\DashedCore\Models\Customsetting::get('my_parcel_api_key', $model->site_id)) {
            try {
                \Dashed\DashedEcommerceMyParcel\Classes\MyParcel::createLabelForOrder($model);

                return response()->json(['success' => true, 'provider' => 'myparcel', 'message' => 'Verzendlabel aangemaakt via MyParcel.']);
            } catch (\Throwable $e) {
                report($e);
                $errors[] = 'MyParcel: ' . $e->getMessage();
            }
        }

        return response()->json([
            'success' => false,
            'message' => $errors ? implode(' ', $errors) : 'Geen verzendprovider geconfigureerd voor deze site.',
        ], 422);
    }

    /** Welke label-providers zijn (op basis van de API-sleutel) beschikbaar. */
    private function labelProviders(Order $model): array
    {
        $providers = [];

        if (class_exists(\Dashed\DashedEcommerceVeloyd\Classes\Veloyd::class)
            && \Dashed\DashedCore\Models\Customsetting::get('veloyd_api_key', $model->site_id)) {
            $providers[] = 'veloyd';
        }
        if (class_exists(\Dashed\DashedEcommerceMyParcel\Classes\MyParcel::class)
            && \Dashed\DashedCore\Models\Customsetting::get('my_parcel_api_key', $model->site_id)) {
            $providers[] = 'myparcel';
        }

        return $providers;
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
            'type' => ['required', Rule::in(['packing_slip', 'shipping_label'])],
            'printer_id' => ['sometimes', 'nullable', 'integer'],
            // Optioneel: print een specifiek label (uit de labellijst).
            'carrier' => ['sometimes', 'nullable', Rule::in(['myparcel', 'veloyd'])],
            'label_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $jobType = $data['type'] === 'shipping_label'
            ? \Dashed\DashedEcommerceCore\Enums\PrintJobType::ShippingLabel
            : \Dashed\DashedEcommerceCore\Enums\PrintJobType::PackingSlip;
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
        ]);
        $packed = $data['packed'] ?? true;

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

        return $this->detail($model);
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
