<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\EmailTemplate;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnProcessor;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnCustomMail;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\OrderReturnResource;

class OrderReturnController extends Controller
{
    private const EAGER = ['order:id,invoice_id,first_name,last_name,email', 'lines.orderProduct', 'lines.returnReason', 'creditOrder:id,invoice_id,total'];

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
            // Slim multi-term: elk woord moet matchen op e-mail óf op het
            // invoice_id van de gekoppelde order (whereHas kan niet via SmartSearch).
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [$search];
            $query->where(function ($outer) use ($terms): void {
                foreach ($terms as $term) {
                    $outer->where(function ($q) use ($term): void {
                        $q->where('email', 'like', "%{$term}%")
                            ->orWhereHas('order', fn ($o) => $o->where('invoice_id', 'like', "%{$term}%"));
                    });
                }
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
        if ($return->status !== OrderReturn::STATUS_APPROVED) {
            return response()->json(['message' => 'Alleen een goedgekeurde retour kan verwerkt worden.'], 422);
        }
        $data = $request->validate([
            'restock' => ['sometimes', 'boolean'],
            'refund' => ['sometimes', 'boolean'], // geaccepteerd voor oude app-versies, genegeerd: terugbetalen is een aparte stap
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'lines' => ['sometimes', 'array'],
            'lines.*.order_return_line_id' => ['required_with:lines', 'integer'],
            'lines.*.quantity' => ['required_with:lines', 'integer', 'min:0'],
        ]);

        $lines = $data['lines'] ?? $return->lines
            ->map(fn ($l) => ['order_return_line_id' => $l->id, 'quantity' => (int) $l->quantity])
            ->all();

        try {
            app(ReturnProcessor::class)->process($return, $lines, [
                'restock' => (bool) ($data['restock'] ?? true),
                'note' => $data['note'] ?? null,
            ]);
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
