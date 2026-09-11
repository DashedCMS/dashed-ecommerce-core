<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;

class DisableOwnPaymentMethodsCommand extends Command
{
    protected $signature = 'dashed:disable-own-payment-methods';

    protected $description = 'Zet elke actieve checkoutbetaalmethode met psp own (handmatige betaling) uit.';

    public function handle(): int
    {
        $methods = PaymentMethod::query()->where('psp', 'own')->where('type', 'online')->where('active', true)->get();

        foreach ($methods as $method) {
            $method->active = false;
            $method->save();
            $this->line("Uitgezet: #{$method->id} {$method->name}");
        }

        $this->info($methods->count() . ' betaalmethode(s) uitgezet.');

        return self::SUCCESS;
    }
}
