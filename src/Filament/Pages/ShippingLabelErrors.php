<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class ShippingLabelErrors extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $title = 'Labels met fouten';
    protected static ?string $slug = 'shipping-label-errors';

    protected string $view = 'dashed-ecommerce-core::filament.pages.shipping-label-errors';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * @return array<int, array{provider_key:string, provider_label:string, id:int, order_id:int|null, invoice_id:string|null, error:string}>
     */
    public function rows(): array
    {
        $rows = [];
        foreach (ecommerce()->shippingLabelProviders() as $provider) {
            foreach ($provider->failedOrders() as $row) {
                $rows[] = array_merge($row, [
                    'provider_key' => $provider->key(),
                    'provider_label' => $provider->label(),
                ]);
            }
        }

        return $rows;
    }

    public function retry(string $providerKey, int $id): void
    {
        $provider = ecommerce()->shippingLabelProviders()[$providerKey] ?? null;
        if ($provider) {
            $provider->retry($id);

            Notification::make()
                ->title('Label opnieuw in wachtrij gezet')
                ->success()
                ->send();
        }
    }
}
