<?php

namespace Qubiqx\QcommerceEcommerceCore;

class EcommerceManager
{
    protected static $builders = [
        'paymentServiceProviders' => [],
        'orderSideWidgets' => [],
        'orderFullWidgets' => [],
        'productWidgets' => [],
    ];

    public function builder(string $name, ?array $blocks = null): self|array
    {
        if (! $blocks) {
            return static::$builders[$name];
        }

        static::$builders[$name] = $blocks;

        return $this;
    }
}
