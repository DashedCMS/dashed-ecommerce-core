<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\Tabs;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\ReceiptPrinter\ReceiptPrinter;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Models\Customsetting;

class POSSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'POS instellingen';
    protected static ?string $navigationGroup = 'Overige';
    protected static ?string $title = 'POS instellingen';

    protected static string $view = 'dashed-core::settings.pages.default-settings';
    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        $formData["cash_register_available"] = Customsetting::get('cash_register_available', null, false);
        $formData["receipt_printer_connector_type"] = Customsetting::get('receipt_printer_connector_type', null, '');
        $formData["receipt_printer_connector_descriptor"] = Customsetting::get('receipt_printer_connector_descriptor', null, '');
        $formData["cash_register_track_cash_book"] = Customsetting::get('cash_register_track_cash_book', null, '');
        $formData["cash_register_amount"] = Customsetting::get('cash_register_amount', null, 0);

        $this->form->fill($formData);
    }

    protected function getFormSchema(): array
    {
        $schema = [
            Placeholder::make('label')
                ->label("POS instellingen voor")
                ->columnSpanFull(),
            Select::make("receipt_printer_connector_type")
                ->options([
                    'cups' => 'cups',
                    'network' => 'network',
                    'windows' => 'windows',
                ])
                ->reactive()
                ->label('Bonnen printer connectie type'),
            TextInput::make("receipt_printer_connector_descriptor")
                ->label('Naam van de printer')
                ->required(fn(Get $get) => $get("receipt_printer_connector_type")),
            Toggle::make("cash_register_available")
                ->reactive()
                ->label('Kassa beschikbaar'),
            Toggle::make("cash_register_track_cash_book")
                ->label('Kasboek bijhouden')
                ->reactive()
                ->visible(fn(Get $get) => $get("cash_register_available")),
            TextInput::make("cash_register_amount")
                ->label('Bedrag in de kassa')
                ->required()
                ->numeric()
                ->visible(fn(Get $get) => $get("cash_register_track_cash_book")),
        ];

        return [
            Section::make($schema)
                ->columns(2),
        ];
    }

    public function getFormStatePath(): ?string
    {
        return 'data';
    }

    protected function getActions(): array
    {
        return [
            Action::make('testPrinter')
                ->label('Test bonnen printer')
                ->visible(Customsetting::get('receipt_printer_connector_type'))
                ->action(function () {
                    try {
                        $printer = new ReceiptPrinter();
                        $printer->init(
                            Customsetting::get('receipt_printer_connector_type'),
                            Customsetting::get('receipt_printer_connector_descriptor')
                        );
                        $printer->setCurrency('€');
                        $printer->setStore(rand(1000, 10000), 'Store name', 'Store address', 'Store phone', 'Store email', 'Store website');
                        $printer->addItem('Dit is een test transactie', 1, 1234);

                        $printer->printReceipt();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Er is een fout opgetreden')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('testRegister')
                ->label('Test kassalade')
                ->visible(Customsetting::get('receipt_printer_connector_type') && Customsetting::get('cash_register_available'))
                ->action(function () {
                    try {
                        $printer = new ReceiptPrinter();
                        $printer->init(
                            Customsetting::get('receipt_printer_connector_type'),
                            Customsetting::get('receipt_printer_connector_descriptor')
                        );
                        $printer->openDrawer();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Er is een fout opgetreden')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function submit()
    {
        $sites = Sites::getSites();

        foreach ($sites as $site) {
            Customsetting::set('cash_register_available', $this->form->getState()["cash_register_available"], $site['id']);
            Customsetting::set('cash_register_track_cash_book', $this->form->getState()["cash_register_track_cash_book"], $site['id']);
            Customsetting::set('cash_register_amount', $this->form->getState()["cash_register_amount"], $site['id']);
            Customsetting::set('receipt_printer_connector_type', $this->form->getState()["receipt_printer_connector_type"], $site['id']);
            Customsetting::set('receipt_printer_connector_descriptor', $this->form->getState()["receipt_printer_connector_descriptor"], $site['id']);
        }

        Notification::make()
            ->title('De POS instellingen zijn opgeslagen')
            ->success()
            ->send();
    }
}
