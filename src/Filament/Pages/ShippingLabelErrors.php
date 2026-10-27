<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class ShippingLabelErrors extends Page
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $title = 'Labels met fouten';
    protected static ?string $slug = 'shipping-label-errors';

    protected string $view = 'dashed-ecommerce-core::filament.pages.shipping-label-errors';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * De pagina toont bestellingen en adressen uit de foutmeldingen van de
     * vervoerder; opnieuw proberen wijzigt de bestelling (zie retry()).
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_order') ?? false;
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
        abort_unless(auth()->user()?->can('edit_order') ?? false, 403);

        $provider = ecommerce()->shippingLabelProviders()[$providerKey] ?? null;
        if ($provider) {
            $provider->retry($id);

            Notification::make()
                ->title(__('Label opnieuw in wachtrij gezet'))
                ->success()
                ->send();
        }
    }
}
