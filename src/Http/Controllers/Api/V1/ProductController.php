<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProcessedOperation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\ProductResource;

class ProductController extends Controller
{
    private const SORTABLE = ['name', 'price', 'stock', 'total_purchases'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::thisSite();

        if ($request->has('public')) {
            $query->where('public', $request->boolean('public'));
        }

        if ($groupId = $request->query('product_group_id')) {
            $query->where('product_group_id', (int) $groupId);
        }

        if ($categoryId = $request->query('product_category_id')) {
            $query->whereHas('productCategories', function ($q) use ($categoryId): void {
                $q->whereKey((int) $categoryId);
            });
        }

        if ($search = $request->query('search')) {
            $query->search((string) $search);
        }

        // Lage/negatieve voorraad: voorraad op of onder de drempel, of <= 0.
        if ($request->boolean('low_stock')) {
            $query->where('use_stock', true)
                ->where(function ($q): void {
                    $q->whereColumn('stock', '<=', 'low_stock_notification_limit')
                        ->orWhere('stock', '<=', 0);
                });
        }

        $sort = (string) $request->query('sort', '');
        if (in_array($sort, self::SORTABLE, true)) {
            $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sort, $direction);
        } else {
            // Stabiele volgorde voor betrouwbare paginatie (infinite scroll).
            $query->orderBy('id');
        }

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);

        return ProductResource::collection($query->paginate($perPage));
    }

    public function show(int $product): ProductResource
    {
        return new ProductResource(Product::thisSite()->findOrFail($product));
    }

    /**
     * Maak een nieuw product (+ optioneel foto's) vanuit de app aan.
     * Multipart, zodat foto's in hetzelfde verzoek meekomen.
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate($this->rules(isCreate: true));

        $product = new Product();

        // Elk product hangt onder een productgroep (zoals in de CMS). De app
        // bouwt geen groepen, dus maken we er één aan op naam van het product.
        $group = $this->resolveProductGroup($request, $data);
        $product->product_group_id = $group->id;

        $product->site_ids = $group->site_ids ?: [Sites::getActive()];

        $this->applyData($product, $data);
        $product->images = $this->resolveImages($request, $data, existing: []);

        // Slug wordt in IsVisitable::saving() afgeleid van name als hij leeg is,
        // maar we respecteren een expliciete slug uit de request.
        $this->applySlug($product, $data);

        $product->save();

        $this->syncCategories($product, $data);

        activity()
            ->performedOn($product)
            ->causedBy($request->user())
            ->log('mobile-api: product aangemaakt');

        return (new ProductResource($product->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $product): ProductResource
    {
        $model = Product::thisSite()->findOrFail($product);

        $data = $request->validate($this->rules(isCreate: false));

        // Idempotentie voor offline ingevoerde voorraad-acties: draagt het verzoek
        // een `op_id`, dan passen we de mutatie maar één keer toe. Een replay
        // (dubbele sync) is dan een no-op die het product ongewijzigd teruggeeft —
        // anders zou "inboeken" bij elke replay nog eens optellen. Zonder op_id
        // gedraagt het endpoint zich exact als vroeger.
        $opId = isset($data['op_id']) ? (string) $data['op_id'] : null;
        unset($data['op_id']);

        ProcessedOperation::once($opId, function () use ($request, $model, $data): array {
            $this->applyData($model, $data);
            $this->applySlug($model, $data);

            // Bestaande media die behouden moet blijven (image_ids) + nieuw geüploade
            // foto's. Wordt alleen aangeraakt als de app er iets over zegt.
            if ($request->hasFile('images') || $request->has('image_ids')) {
                $existing = $request->has('image_ids')
                    ? array_map('intval', (array) $request->input('image_ids'))
                    : (is_array($model->images) ? $model->images : []);

                $model->images = $this->resolveImages($request, $data, existing: $existing);
            }

            $model->save();

            $this->syncCategories($model, $data);

            // Alleen scalaire velden loggen: geüploade files/arrays zijn niet naar
            // JSON te encoden voor de activity-log.
            $logProperties = array_filter($data, static fn ($value): bool => is_scalar($value) || $value === null);

            activity()
                ->performedOn($model)
                ->causedBy($request->user())
                ->withProperties($logProperties)
                ->log('mobile-api: product bijgewerkt');

            return ['id' => $model->id, 'stock' => $model->stock];
        });

        return new ProductResource($model->fresh());
    }

    /**
     * Validatieregels voor create/update. Bij create zijn name + price verplicht.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $isCreate): array
    {
        $req = $isCreate ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price' => [$req, 'numeric', 'min:0'],
            'new_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'purchase_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'vat_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ean' => ['sometimes', 'nullable', 'string', 'max:255'],
            'article_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'short_description' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'public' => ['sometimes', 'boolean'],

            // Voorraad
            'use_stock' => ['sometimes', 'boolean'],
            'stock' => ['sometimes', 'nullable', 'integer', 'min:0'],

            'product_group_id' => ['sometimes', 'nullable', 'integer', 'exists:dashed__product_groups,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:dashed__product_categories,id'],

            // Foto-upload (zelfde mimetypes/grootte als de chat-bijlagen).
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/gif', 'max:10240'],
            'image_ids' => ['sometimes', 'array'],
            'image_ids.*' => ['integer'],

            // Client-gegenereerd operatie-id voor idempotente (offline) sync.
            'op_id' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    /**
     * Zet de niet-relationele, niet-foto velden op het product. Translatable
     * velden (name/short_description/description) worden via Spatie's
     * HasTranslations voor de actieve locale weggeschreven.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyData(Product $product, array $data): void
    {
        $locale = app()->getLocale();

        $translatable = ['name', 'short_description', 'description'];
        foreach ($translatable as $field) {
            if (array_key_exists($field, $data)) {
                $product->setTranslation($field, $locale, (string) ($data[$field] ?? ''));
            }
        }

        $plain = [
            'price', 'new_price', 'purchase_price', 'vat_rate',
            'sku', 'ean', 'article_code', 'public', 'use_stock', 'stock',
        ];
        foreach ($plain as $field) {
            if (array_key_exists($field, $data)) {
                $product->{$field} = $data[$field];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applySlug(Product $product, array $data): void
    {
        if (! empty($data['slug'])) {
            $product->setTranslation('slug', app()->getLocale(), Str::slug((string) $data['slug']));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveProductGroup(Request $request, array $data): ProductGroup
    {
        if (! empty($data['product_group_id'])) {
            return ProductGroup::findOrFail((int) $data['product_group_id']);
        }

        $locale = app()->getLocale();
        $name = (string) ($data['name'] ?? 'Product');

        $group = new ProductGroup();
        $group->setTranslation('name', $locale, $name);
        $group->setTranslation('slug', $locale, Str::slug($name).'-'.Str::random(6));
        // ProductGroup heeft NOT NULL translatable tekstvelden; leeg invullen.
        foreach (['short_description', 'description', 'content', 'search_terms'] as $field) {
            $group->setTranslation($field, $locale, '');
        }
        $group->site_ids = [Sites::getActive()];
        $group->save();

        return $group;
    }

    /**
     * Sla geüploade foto's op naar de dashed-disk, registreer ze via de
     * MediaHelper (zelfde pad als de CMS ProductResource + de chat-bijlagen) en
     * combineer ze met de te behouden bestaande media-ids.
     *
     * Achtergrond-verwijderen gebeurt NIET hier: dat is een aparte, native
     * iOS-stap (#10) die later vóór de upload op het toestel draait. Deze
     * methode is bewust het integratiepunt — de bytes die hier binnenkomen zijn
     * de definitieve productfoto.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $existing
     * @return array<int, int>
     */
    private function resolveImages(Request $request, array $data, array $existing): array
    {
        $ids = array_values(array_filter(array_map('intval', $existing)));

        foreach ($request->file('images', []) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            // Op de dashed-disk zetten zodat MediaHelper het kan registreren.
            $path = $file->store('producten-tmp', 'dashed');
            if (! $path) {
                continue;
            }

            $id = mediaHelper()->uploadFromPath($path, 'producten');
            Storage::disk('dashed')->delete($path);

            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncCategories(Product $product, array $data): void
    {
        if (! array_key_exists('category_ids', $data)) {
            return;
        }

        $ids = array_values(array_filter(array_map('intval', (array) $data['category_ids'])));
        $product->productCategories()->sync($ids);
    }
}
