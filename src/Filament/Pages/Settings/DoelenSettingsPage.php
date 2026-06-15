<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class DoelenSettingsPage extends Page implements HasSchemas
{
    use HasSettingsPermission;
    use InteractsWithSchemas;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-flag';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Verkoopdoelen';
    protected static string | UnitEnum | null $navigationGroup = 'Overige';
    protected static ?string $title = 'Verkoopdoelen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    private const PERIODS = [
        'today' => 'Vandaag',
        'week' => 'Deze week',
        'month' => 'Deze maand',
        'year' => 'Dit jaar',
    ];

    public function mount(): void
    {
        $formData = [];
        foreach (array_keys(self::PERIODS) as $key) {
            $revenue = (float) Customsetting::get('dashboard_revenue_target_' . $key);
            $orders = (int) Customsetting::get('dashboard_orders_target_' . $key);
            $formData['revenue_target_' . $key] = $revenue > 0 ? $revenue : null;
            $formData['orders_target_' . $key] = $orders > 0 ? $orders : null;
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];
        foreach (self::PERIODS as $key => $label) {
            $sections[] = Section::make($label)
                ->description('Laat leeg of 0 voor geen doel.')
                ->columns(2)
                ->schema([
                    TextInput::make('revenue_target_' . $key)
                        ->label('Omzetdoel (€)')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('€'),
                    TextInput::make('orders_target_' . $key)
                        ->label('Bestellingsdoel (aantal)')
                        ->numeric()
                        ->minValue(0)
                        ->integer(),
                ]);
        }

        return $schema->schema($sections)->statePath('data');
    }

    public function submit(): void
    {
        $formData = $this->form->getState();

        foreach (Sites::getSites() as $site) {
            foreach (array_keys(self::PERIODS) as $key) {
                Customsetting::set('dashboard_revenue_target_' . $key, (float) ($formData['revenue_target_' . $key] ?? 0), $site['id']);
                Customsetting::set('dashboard_orders_target_' . $key, (int) ($formData['orders_target_' . $key] ?? 0), $site['id']);
            }
        }

        Notification::make()->title('Verkoopdoelen opgeslagen')->success()->send();

        redirect(DoelenSettingsPage::getUrl());
    }
}
