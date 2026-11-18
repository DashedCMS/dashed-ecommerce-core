<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Tests\Unit\Checkout;

use Orchestra\Testbench\TestCase;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\Checkout;

/**
 * The overridable validation hooks of Checkout::submit(). A plain Testbench
 * class without the Dashed providers: the package harness cannot boot a full
 * Livewire checkout, and these hooks only need the validator.
 */
class CheckoutValidationHooksTest extends TestCase
{
    public function test_defaults_keep_laravels_messages_and_the_toast(): void
    {
        $checkout = new Checkout();

        $this->assertSame([], $this->invokeHook($checkout, 'validationMessages'));
        $this->assertSame([], $this->invokeHook($checkout, 'validationAttributes'));
        $this->assertTrue($this->invokeHook($checkout, 'shouldShowValidationToast'));

        $errors = $this->invokeHook($checkout, 'makeCheckoutValidator', ['street' => ''], ['street' => ['required']])->errors();

        $this->assertSame('The street field is required.', $errors->first('street'));
    }

    public function test_overridden_messages_and_attributes_reach_the_validator(): void
    {
        $checkout = new class () extends Checkout {
            protected function validationMessages(): array
            {
                return ['street.required' => 'Vul je straatnaam in.'];
            }

            protected function validationAttributes(): array
            {
                return ['city' => 'woonplaats'];
            }

            protected function shouldShowValidationToast(): bool
            {
                return false;
            }
        };

        $errors = $this->invokeHook(
            $checkout,
            'makeCheckoutValidator',
            ['street' => '', 'city' => ''],
            ['street' => ['required'], 'city' => ['required']],
        )->errors();

        $this->assertSame('Vul je straatnaam in.', $errors->first('street'));
        $this->assertSame('The woonplaats field is required.', $errors->first('city'));
        $this->assertFalse($this->invokeHook($checkout, 'shouldShowValidationToast'));
    }

    private function invokeHook(object $object, string $method, mixed ...$arguments): mixed
    {
        return (new \ReflectionMethod($object, $method))->invoke($object, ...$arguments);
    }
}
