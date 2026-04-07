<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use BackedEnum;
use UnitEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Filament\Schemas\Components\Section;

class AbandonedCartSettingsPage extends Page
{
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Verlaten winkelwagen emails';
    protected static ?string $title = 'Verlaten winkelwagen emails';

    protected string $view = 'dashed-core::settings.pages.default-settings';
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'abandoned_cart_emails_enabled' => (bool) Customsetting::get('abandoned_cart_emails_enabled', null, false),

            'abandoned_cart_email1_delay_hours' => (int) Customsetting::get('abandoned_cart_email1_delay_hours', null, 1),
            'abandoned_cart_email1_subject' => Customsetting::get('abandoned_cart_email1_subject', null, 'Je hebt iets achtergelaten'),

            'abandoned_cart_email2_delay_hours' => (int) Customsetting::get('abandoned_cart_email2_delay_hours', null, 24),
            'abandoned_cart_email2_subject' => Customsetting::get('abandoned_cart_email2_subject', null, 'Je :product wacht nog op je'),

            'abandoned_cart_email3_enabled' => (bool) Customsetting::get('abandoned_cart_email3_enabled', null, false),
            'abandoned_cart_email3_delay_hours' => (int) Customsetting::get('abandoned_cart_email3_delay_hours', null, 72),
            'abandoned_cart_email3_subject' => Customsetting::get('abandoned_cart_email3_subject', null, 'Speciaal voor jou: een cadeautje'),
            'abandoned_cart_email3_incentive_type' => Customsetting::get('abandoned_cart_email3_incentive_type', null, 'amount'),
            'abandoned_cart_email3_incentive_value' => (float) Customsetting::get('abandoned_cart_email3_incentive_value', null, 5),
            'abandoned_cart_email3_valid_days' => (int) Customsetting::get('abandoned_cart_email3_valid_days', null, 7),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                Section::make('Algemeen')
                    ->schema([
                        Toggle::make('abandoned_cart_emails_enabled')
                            ->label('Verlaten winkelwagen emails inschakelen')
                            ->helperText('Stuur automatisch emails naar bezoekers die hun winkelwagen hebben achtergelaten.'),
                    ]),

                Section::make('Email 1 — 1 uur na verlaten')
                    ->schema([
                        TextInput::make('abandoned_cart_email1_delay_hours')
                            ->label('Uren na verlaten')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('uur'),
                        TextInput::make('abandoned_cart_email1_subject')
                            ->label('Onderwerpregel')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Email 2 — 24 uur later')
                    ->schema([
                        TextInput::make('abandoned_cart_email2_delay_hours')
                            ->label('Uren na email 1')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('uur'),
                        TextInput::make('abandoned_cart_email2_subject')
                            ->label('Onderwerpregel')
                            ->helperText('Gebruik :product voor de productnaam.')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Email 3 — 72 uur later (optioneel)')
                    ->schema([
                        Toggle::make('abandoned_cart_email3_enabled')
                            ->label('Email 3 inschakelen'),
                        TextInput::make('abandoned_cart_email3_delay_hours')
                            ->label('Uren na email 2')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('uur'),
                        TextInput::make('abandoned_cart_email3_subject')
                            ->label('Onderwerpregel')
                            ->maxLength(255),
                        Select::make('abandoned_cart_email3_incentive_type')
                            ->label('Incentive type')
                            ->options([
                                'none' => 'Geen kortingscode',
                                'amount' => 'Vast bedrag (bijv. €5)',
                                'percentage' => 'Percentage (bijv. 10%)',
                            ]),
                        TextInput::make('abandoned_cart_email3_incentive_value')
                            ->label('Kortingswaarde')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Bedrag in euro\'s of percentage.'),
                        TextInput::make('abandoned_cart_email3_valid_days')
                            ->label('Geldigheid kortingscode')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('dagen'),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Opslaan')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Customsetting::set($key, $value);
        }

        Notification::make()->title('Instellingen opgeslagen')->success()->send();
    }
}
