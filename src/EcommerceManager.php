<?php

namespace Dashed\DashedEcommerceCore;

class EcommerceManager
{
    protected static $builders = [
        'paymentServiceProviders' => [],
        'fulfillmentProviders' => [],
    ];

    protected static $buttonActions = [
        'orders' => [],
        'order' => [],
    ];

    protected static $widgets = [
        'orders' => [],
    ];

    public function builder(string $name, ?array $blocks = null): self|array
    {
        if (! $blocks) {
            return static::$builders[$name];
        }

        static::$builders[$name] = $blocks;

        return $this;
    }

    public function widgets(string $name, ?array $blocks = null): self|array
    {
        if (! $blocks) {
            return static::$widgets[$name];
        }

        static::$widgets[$name] = $blocks;

        return $this;
    }

    public function buttonActions(string $name, ?array $blocks = null): self|array
    {
        if (! $blocks) {
            return static::$buttonActions[$name];
        }

        static::$buttonActions[$name] = $blocks;

        return $this;
    }
}
