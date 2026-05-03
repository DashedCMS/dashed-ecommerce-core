<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedEcommerceCore\Models\CustomerMatchEndpoint;
use Dashed\DashedEcommerceCore\Services\CustomerMatch\CustomerMatchExporter;

class CustomerMatchSettingsPage extends Page
{
    use HasSettingsPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Google Ads Customer Match';
    protected static string|UnitEnum|null $navigationGroup = 'Overige';
    protected static ?string $title = 'Google Ads Customer Match';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $endpoint = CustomerMatchEndpoint::singleton();
        $filter = $endpoint->customer_filter ?? [];

        $this->form->fill([
            'is_active' => (bool) $endpoint->is_active,
            'username' => $endpoint->username,
            'slug' => $endpoint->slug,
            'min_orders' => $filter['min_orders'] ?? 1,
            'since' => $filter['since'] ?? null,
            'until' => $filter['until'] ?? null,
            'countries' => $filter['countries'] ?? [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $endpoint = CustomerMatchEndpoint::singleton();

        return $schema
            ->components([
                Section::make('Endpoint')
                    ->description('Plak deze waardes in de Google Ads "HTTPS source" import.')
                    ->schema([
                        TextEntry::make('endpoint_url')
                            ->label('URL')
                            ->state(fn () => $this->endpointUrl($endpoint))
                            ->copyable(),
                        TextInput::make('username')
                            ->label('Gebruikersnaam')
                            ->disabled()
                            ->dehydrated(false),
                        TextEntry::make('password_status')
                            ->label('Wachtwoord')
                            ->state(fn () => $this->passwordDisplay()),
                        TextInput::make('slug')
                            ->label('Slug (deel van de URL)')
                            ->disabled()
                            ->dehydrated(false),
                        Toggle::make('is_active')
                            ->label('Endpoint actief')
                            ->helperText('Uitschakelen blokkeert nieuwe downloads (404).'),
                    ])
                    ->columns(['default' => 1, 'lg' => 2]),

                Section::make('Filter')
                    ->description('Welke klanten worden meegenomen in de export.')
                    ->schema([
                        TextInput::make('min_orders')
                            ->label('Minimum aantal betaalde bestellingen')
                            ->numeric()
                            ->minValue(1)
                            ->default(1),
                        TagsInput::make('countries')
                            ->label('Landen (ISO-2, bv. NL, BE, DE)')
                            ->placeholder('Voeg landcode toe')
                            ->helperText('Leeg = alle landen.'),
                        DatePicker::make('since')
                            ->label('Vanaf besteldatum')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Tot besteldatum')
                            ->native(false),
                    ])
                    ->columns(['default' => 1, 'lg' => 2]),

                Section::make('Activiteit')
                    ->schema([
                        TextEntry::make('last_accessed')
                            ->label('Laatst opgehaald')
                            ->state(fn () => $this->lastAccessedDisplay()),
                        TextEntry::make('exports_30d')
                            ->label('Succesvolle exports laatste 30 dagen')
                            ->state(fn () => (string) $this->exportsLast30Days()),
                        TextEntry::make('matching_customers')
                            ->label('Aantal matchende klanten')
                            ->state(fn () => (string) app(CustomerMatchExporter::class)->count(CustomerMatchEndpoint::singleton())),
                    ])
                    ->columns(['default' => 1, 'lg' => 3]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerateSlug')
                ->label('Roteer URL')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('De huidige URL stopt direct met werken. Werk daarna Google Ads bij met de nieuwe URL.')
                ->action(function (): void {
                    $endpoint = CustomerMatchEndpoint::singleton();
                    $endpoint->slug = CustomerMatchEndpoint::generateSlug();
                    $endpoint->save();

                    $this->mount();

                    Notification::make()
                        ->title('Nieuwe URL gegenereerd')
                        ->success()
                        ->send();
                }),

            Action::make('regeneratePassword')
                ->label('Genereer nieuw wachtwoord')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Het huidige wachtwoord wordt direct ongeldig. Het nieuwe wachtwoord zie je 1x na bevestigen.')
                ->action(function (): void {
                    $endpoint = CustomerMatchEndpoint::singleton();
                    $plain = CustomerMatchEndpoint::generatePassword();
                    $endpoint->password = Hash::make($plain);
                    $endpoint->save();

                    session()->flash('customer_match_plaintext_password', $plain);

                    Notification::make()
                        ->title('Nieuw wachtwoord')
                        ->body('Wachtwoord: '.$plain.' - kopieer nu, wordt niet opnieuw getoond.')
                        ->persistent()
                        ->success()
                        ->send();
                }),

            Action::make('dryRun')
                ->label('Preview eerste 5 rijen')
                ->color('gray')
                ->action(function (): void {
                    $endpoint = CustomerMatchEndpoint::singleton();
                    $rows = iterator_to_array(app(CustomerMatchExporter::class)->rows($endpoint, 5));

                    if ($rows === []) {
                        Notification::make()
                            ->title('Geen klanten gevonden voor het huidige filter')
                            ->warning()
                            ->send();

                        return;
                    }

                    $body = "Header: ".implode(', ', app(CustomerMatchExporter::class)->header())."\n";
                    foreach ($rows as $i => $row) {
                        $body .= sprintf("#%d: %s\n", $i + 1, json_encode($row, JSON_UNESCAPED_UNICODE));
                    }

                    Notification::make()
                        ->title('Preview ('.count($rows).' rijen)')
                        ->body('<pre style="white-space:pre-wrap;font-size:11px">'.e($body).'</pre>')
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }

    public function submit(): void
    {
        $state = $this->form->getState();

        $endpoint = CustomerMatchEndpoint::singleton();
        $endpoint->is_active = (bool) ($state['is_active'] ?? false);
        $endpoint->customer_filter = [
            'min_orders' => max(1, (int) ($state['min_orders'] ?? 1)),
            'since' => $state['since'] ?? null,
            'until' => $state['until'] ?? null,
            'countries' => array_values(array_filter(array_map('strtoupper', $state['countries'] ?? []))),
        ];
        $endpoint->save();

        Notification::make()
            ->title('Customer Match instellingen opgeslagen')
            ->success()
            ->send();
    }

    private function endpointUrl(CustomerMatchEndpoint $endpoint): string
    {
        return URL::to('/google-ads/customer-match/'.$endpoint->slug.'.csv');
    }

    private function passwordDisplay(): string
    {
        $plain = session()->get('customer_match_plaintext_password');

        if ($plain) {
            session()->forget('customer_match_plaintext_password');

            return $plain.' (1x getoond - kopieer nu)';
        }

        return '••••••••••••  (gebruik "Genereer nieuw wachtwoord" om te roteren)';
    }

    private function lastAccessedDisplay(): string
    {
        $endpoint = CustomerMatchEndpoint::singleton();

        if (! $endpoint->last_accessed_at) {
            return 'Nog niet opgehaald';
        }

        return $endpoint->last_accessed_at->diffForHumans()
            .' vanaf '.($endpoint->last_accessed_ip ?: 'onbekend IP');
    }

    private function exportsLast30Days(): int
    {
        return CustomerMatchEndpoint::singleton()
            ->accessLogs()
            ->where('status', 200)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
    }
}
