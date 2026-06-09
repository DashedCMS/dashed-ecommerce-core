<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\PrinterResource;

/**
 * Beheer van netwerk-printers (pakbon/label) vanuit de app. Het daadwerkelijke
 * printen blijft via de print-queue + de daemon op de Pi/NAS (CUPS); hier
 * beheer je welke printers er zijn, hun status, en de koppeling (token).
 */
class PrinterController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PrinterResource::collection(Printer::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        $printer = Printer::create($data);

        return (new PrinterResource($printer))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $printer): PrinterResource
    {
        $model = Printer::findOrFail($printer);
        $model->update($this->validateData($request, $model));

        return new PrinterResource($model->fresh());
    }

    public function destroy(int $printer): JsonResponse
    {
        Printer::findOrFail($printer)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * (Her)genereer het token en geef het installatie-commando terug dat je op de
     * Pi/NAS draait (via SSH) om de print-daemon te koppelen.
     */
    public function pair(int $printer): JsonResponse
    {
        $model = Printer::findOrFail($printer);

        $model->tokens()->delete();
        $token = $model->createToken("printer-{$model->ulid}")->plainTextToken;
        $model->forceFill(['plain_token' => $token])->save();

        $installUrl = URL::temporarySignedRoute(
            'dashed.print-queue.installer',
            now()->addHours(24),
            ['ulid' => $model->ulid],
        );

        return response()->json([
            'token' => $token,
            'install_command' => 'curl -fsSL "' . $installUrl . '" | sudo bash',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?Printer $existing = null): array
    {
        $rules = [
            'name' => [$existing ? 'sometimes' : 'required', 'string', 'max:255'],
            'cups_name' => [$existing ? 'sometimes' : 'required', 'string', 'max:255'],
            'type' => [$existing ? 'sometimes' : 'required', 'string', 'in:' . implode(',', array_map(static fn (PrinterType $t) => $t->value, PrinterType::cases()))],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'max_retries' => ['sometimes', 'integer', 'min:0', 'max:20'],
        ];

        return $request->validate($rules);
    }
}
