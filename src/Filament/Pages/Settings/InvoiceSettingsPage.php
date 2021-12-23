<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceCore\Models\Customsetting;

class InvoiceSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-report';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Facturatie instellingen';
    protected static ?string $navigationGroup = 'Overige';
    protected static ?string $title = 'Facturatie instellingen';

    protected static string $view = 'qcommerce-core::settings.pages.default-settings';

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["invoice_id_prefix_{$site['id']}"] = Customsetting::get('invoice_id_prefix', $site['id']);
            $formData["invoice_id_suffix_{$site['id']}"] = Customsetting::get('invoice_id_suffix', $site['id']);
            $formData["random_invoice_number_{$site['id']}"] = Customsetting::get('random_invoice_number', $site['id'], false) ? true : false;
            $formData["current_invoice_number_{$site['id']}"] = Customsetting::get('current_invoice_number', $site['id'], '1001');
            $formData["invoice_id_replacement_{$site['id']}"] = Customsetting::get('invoice_id_replacement', $site['id'], '*****');
        }

        $this->form->fill($formData);
    }

    protected function getFormSchema(): array
    {
        $sites = Sites::getSites();
        $tabGroups = [];

        $tabs = [];
        foreach ($sites as $site) {
            $schema = [
                Placeholder::make('label')
                    ->label("Facturerings instellingen voor {$site['name']}")
                    ->content('Let op: je kan per site een andere pre / suffix gebruiken om met de factuur ID te herkenning in de administratie via welke site de bestelling is binnen gekomen. Dit is alleen ?wettelijk (niet zeker)? toegestaan als het wel in 1 administratie terecht komt.')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                Checkbox::make("random_invoice_number_{$site['id']}")
                    ->label('Gebruik een willekeurige invoice ID')
                    ->reactive()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("invoice_id_replacement_{$site['id']}")
                    ->label('Invoice ID replacement')
                    ->maxLength(25)
                    ->rules([
                        'max:25',
                    ])
                    ->helperText('Gebruik * voor een random getal / letter, bijv: *****')
                    ->reactive()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->hidden(fn ($get) => ! $get("random_invoice_number_{$site['id']}")),
                TextInput::make("current_invoice_number_{$site['id']}")
                    ->label('Huidige factuurnummer')
                    ->type('number')
                    ->rules([
                        'numeric',
                    ])
                    ->helperText('Alleen numeriek')
                    ->reactive()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->hidden(fn ($get) => $get("random_invoice_number_{$site['id']}")),
                TextInput::make("invoice_id_prefix_{$site['id']}")
                    ->label('Voorvoegsel')
                    ->maxLength(5)
                    ->rules([
                        'max:5',
                    ]),
                TextInput::make("invoice_id_suffix_{$site['id']}")
                    ->label('Achtervoegsel')
                    ->reactive()
                    ->maxLength(5)
                    ->rules([
                        'max:5',
                    ]),
                Placeholder::make("invoice_id_example_{$site['id']}")
                    ->label("Voorbeeld van factuur ID")
                    ->content('QC#*****QC of QC#1001QC')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
            ];

            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema($schema)
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]);
        }
        $tabGroups[] = Tabs::make('Sites')
            ->tabs($tabs);

        return $tabGroups;
    }

    public function submit()
    {
        $sites = Sites::getSites();

        foreach ($sites as $site) {
            Customsetting::set('invoice_id_prefix', $this->form->getState()["invoice_id_prefix_{$site['id']}"], $site['id']);
            Customsetting::set('invoice_id_suffix', $this->form->getState()["invoice_id_suffix_{$site['id']}"], $site['id']);
            Customsetting::set('random_invoice_number', $this->form->getState()["random_invoice_number_{$site['id']}"], $site['id']);
            if (isset($this->form->getState()["current_invoice_number_{$site['id']}"])) {
                Customsetting::set('current_invoice_number', $this->form->getState()["current_invoice_number_{$site['id']}"], $site['id']);
            }
            if (isset($this->form->getState()["invoice_id_replacement_{$site['id']}"])) {
                Customsetting::set('invoice_id_replacement', $this->form->getState()["invoice_id_replacement_{$site['id']}"], $site['id']);
            }
        }

        $this->notify('success', 'De facturatie instellingen zijn opgeslagen');
    }
}
