<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Support\Automation\AutomationEngine;

/**
 * Voert één automatiseringsregel uit voor één onderwerp (in fase 1 altijd
 * een Order) — gedispatched door AutomationTriggerSubscriber zodra een
 * regel matcht. De daadwerkelijke uitvoering + logging zit in
 * AutomationEngine::run(), zodat die ook los van de wachtrij testbaar is;
 * deze job is bewust een dunne wrapper.
 *
 * `tries = 1`: acties zijn niet per se veilig om te herhalen (een
 * create_label-actie die halverwege faalt, kan alsnog portokosten hebben
 * gemaakt) — een automatische queue-retry zou dezelfde regel nogmaals
 * kunnen draaien en zelf de lus worden die laag 2 net probeert te
 * voorkomen.
 */
class RunAutomationRuleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public function __construct(public AutomationRule $rule, public Model $subject)
    {
    }

    public function handle(): void
    {
        AutomationEngine::run($this->rule, $this->subject);
    }
}
