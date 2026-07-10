<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\EmailTemplate;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnCustomMail;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\OrderReturnResource;

class OrderReturnController extends Controller
{
    private const EAGER = ['order:id,invoice_id,first_name,last_name,email', 'lines.orderProduct', 'lines.returnReason'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = OrderReturn::query()
            ->where('site_id', Sites::getActive())
            ->with(self::EAGER);

        if ($status = $request->query('status')) {
            $statuses = is_array($status) ? $status : explode(',', (string) $status);
            $query->whereIn('status', $statuses);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($o) => $o->where('invoice_id', 'like', "%{$search}%"));
            });
        }

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);

        return OrderReturnResource::collection(
            $query->orderByDesc('requested_at')->paginate($perPage),
        );
    }

    public function show(int $orderReturn): OrderReturnResource
    {
        return new OrderReturnResource($this->find($orderReturn));
    }

    public function approve(Request $request, int $orderReturn): OrderReturnResource|JsonResponse
    {
        $return = $this->find($orderReturn);
        if ($return->status !== OrderReturn::STATUS_REQUESTED) {
            return response()->json(['message' => 'Alleen aangevraagde retouren kunnen worden goedgekeurd.'], 422);
        }
        $data = $request->validate(['admin_note' => ['nullable', 'string']]);
        $return->approve($data['admin_note'] ?? null);

        return new OrderReturnResource($this->find($orderReturn));
    }

    public function reject(Request $request, int $orderReturn): OrderReturnResource|JsonResponse
    {
        $return = $this->find($orderReturn);
        if ($return->status !== OrderReturn::STATUS_REQUESTED) {
            return response()->json(['message' => 'Alleen aangevraagde retouren kunnen worden afgewezen.'], 422);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'min:1']]);
        $return->reject($data['reason']);

        return new OrderReturnResource($this->find($orderReturn));
    }

    public function handle(Request $request, int $orderReturn): OrderReturnResource|JsonResponse
    {
        $return = $this->find($orderReturn);
        if (! in_array($return->status, [OrderReturn::STATUS_REQUESTED, OrderReturn::STATUS_APPROVED], true)) {
            return response()->json(['message' => 'Deze retour kan niet meer worden afgehandeld.'], 422);
        }
        $data = $request->validate([
            'restock' => ['sometimes', 'boolean'],
            'refund' => ['sometimes', 'boolean'],
        ]);
        $restock = (bool) ($data['restock'] ?? false);
        $refund = (bool) ($data['refund'] ?? false);

        try {
            DB::transaction(function () use ($return, $restock, $refund): void {
                if ($restock || $refund) {
                    $lines = $return->lines
                        ->map(fn ($l) => ['order_product_id' => $l->order_product_id, 'quantity' => (int) $l->quantity])
                        ->all();
                    if ($lines) {
                        $return->order?->registerReturn($lines, $restock, $refund);
                    }
                }
                $return->markHandled();
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OrderReturnResource($this->find($orderReturn));
    }

    /**
     * Record-onafhankelijke standaard-onderwerp/-bericht voor een handmatig
     * bericht aan de klant (spiegelt de Filament "Stuur e-mail"-actie). De app
     * gebruikt dit om het opstelscherm voor te vullen.
     */
    public function emailDefaults(): JsonResponse
    {
        return response()->json([
            'subject' => $this->defaultEmailSubject(),
            'message' => $this->defaultEmailMessage(),
        ]);
    }

    public function sendEmail(Request $request, int $orderReturn): OrderReturnResource
    {
        $return = $this->find($orderReturn);
        $data = $request->validate([
            'subject' => ['required', 'string'],
            'message' => ['required', 'string'],
            'email' => ['nullable', 'email'],
        ]);

        // De app stuurt platte tekst; zet die veilig om naar HTML voor het
        // :message:-blok (Filament gebruikt daar een RichEditor).
        $message = str_contains($data['message'], '<')
            ? $data['message']
            : nl2br(e($data['message']));

        $return->sendCustomEmail($data['subject'], $message, $data['email'] ?? null);

        return new OrderReturnResource($this->find($orderReturn));
    }

    public function label(int $orderReturn): JsonResponse
    {
        $return = $this->find($orderReturn);
        if (! $return->return_label_path || ! Storage::disk('public')->exists($return->return_label_path)) {
            return response()->json(['message' => 'Geen retourlabel beschikbaar.'], 404);
        }

        return response()->json(['url' => Storage::disk('public')->url($return->return_label_path)]);
    }

    /**
     * Haalt een retour van de actieve site op; 404 (ModelNotFound) als de retour
     * niet bestaat of bij een andere site hoort. De mobile-api-route-groep draait
     * geen SubstituteBindings, dus we resolven bewust zelf op id (net als de
     * overige mobile-api-controllers).
     */
    protected function find(int $id): OrderReturn
    {
        return OrderReturn::query()
            ->where('site_id', Sites::getActive())
            ->with(self::EAGER)
            ->findOrFail($id);
    }

    private function defaultEmailSubject(): string
    {
        $template = EmailTemplate::forMailable(OrderReturnCustomMail::emailTemplateKey());
        $subject = $template?->getTranslation('subject', app()->getLocale(), useFallbackLocale: true);

        return $subject ?: OrderReturnCustomMail::defaultSubject();
    }

    private function defaultEmailMessage(): string
    {
        // Filament levert HTML (RichEditor); de app toont platte tekst, dus de
        // alinea's worden regelovergangen en de tags gestript.
        $html = OrderReturnCustomMail::defaultMessage();
        $text = preg_replace('/<\/p>\s*<p>/i', "\n\n", $html);

        return trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5));
    }
}
