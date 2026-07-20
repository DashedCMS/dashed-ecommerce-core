<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\AutomationRuleResource;
use Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile\AutomationRuleRunResource;

/**
 * Automatiseringsregels ("als dit gebeurt en deze voorwaarden gelden, doe dat")
 * voor de mobiele app: uitlezen, aan/uit zetten en het uitvoerlog bekijken.
 *
 * De app is bewust géén regel-editor. Het opstellen van een regel — trigger,
 * voorwaarden, acties — gebeurt in het CMS, waar de volledige context en
 * validatie zit. Vanaf de telefoon wil je precies één ding kunnen: een regel
 * die verkeerd uitpakt meteen uitzetten. Vandaar dat `update()` alleen de
 * schakelaar accepteert.
 */
class AutomationRuleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $rules = AutomationRule::query()
            ->where('site_id', (string) Sites::getActive())
            // `last_run_at` in één query erbij, anders N+1 over de lijst.
            ->withMax('runs', 'created_at')
            ->orderBy('name')
            ->get();

        return AutomationRuleResource::collection($rules);
    }

    /**
     * Zet een regel aan of uit.
     *
     * LET OP — dashed-core zet een globale `Model::unguard()`. Mass assignment
     * is daarmee overal open en `$fillable` op AutomationRule beschermt hier
     * niets. Daarom nooit `$model->update($request->all())` of een variant
     * daarop: we lezen exact één gevalideerd veld uit en zetten dat expliciet.
     * Alles wat verder in de body zit (naam, trigger, voorwaarden, acties,
     * site_id, id) wordt genegeerd.
     */
    public function update(Request $request, int $id): AutomationRuleResource
    {
        $rule = AutomationRule::query()
            ->where('site_id', (string) Sites::getActive())
            ->findOrFail($id);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $rule->is_active = (bool) $data['is_active'];
        $rule->save();

        activity()
            ->performedOn($rule)
            ->causedBy($request->user())
            ->withProperties(['is_active' => $rule->is_active])
            ->log('mobile-api: automatiseringsregel ' . ($rule->is_active ? 'aangezet' : 'uitgezet'));

        return new AutomationRuleResource(
            $rule->fresh()->loadMax('runs', 'created_at')
        );
    }

    /**
     * Het uitvoerlog, nieuwste eerst. Optioneel te filteren op één regel, zodat
     * de app vanaf een regel direct naar "wat deed deze regel?" kan doorklikken.
     */
    public function runs(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'rule_id' => ['sometimes', 'nullable', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AutomationRuleRun::query()
            ->where('site_id', (string) Sites::getActive())
            // rule + subject eager-loaden voor rule_name en het subject-blok.
            ->with(['rule:id,name', 'subject']);

        if (! empty($data['rule_id'])) {
            $query->where('rule_id', (int) $data['rule_id']);
        }

        $perPage = (int) ($data['per_page'] ?? 25);

        return AutomationRuleRunResource::collection(
            $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)
        );
    }
}
