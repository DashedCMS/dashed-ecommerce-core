<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\URL;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedEcommerceCore\Models\Printer;
use Filament\Actions\Action;

class PrintQueueSettingsPage extends Page
{
    use HasSettingsPermission;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-printer';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Print queue';
    protected static string|UnitEnum|null $navigationGroup = 'Overige';
    protected static ?string $title = 'Print queue instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [
            'auto_print_on_new_order' => (bool) Customsetting::get('print_queue.auto_print_on_new_order', null, null),
            'auto_print_label_on_generated' => (bool) Customsetting::get('print_queue.auto_print_label_on_generated', null, null),
            'health_check_threshold_seconds' => (int) Customsetting::get('print_queue.health_check_threshold_seconds', null, '60'),
            'label_sync_interval_minutes' => (int) Customsetting::get('print_queue.label_sync_interval_minutes', null, '1'),
            'job_retention_days' => (int) Customsetting::get('print_queue.job_retention_days', null, '90'),
            'failed_job_retention_days' => (int) Customsetting::get('print_queue.failed_job_retention_days', null, '365'),
        ];

        $this->form->fill($formData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pair_new_pi')
                ->label('Pair een nieuw apparaat')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->action(function (): void {
                    $printer = Printer::startPairing();

                    Notification::make()
                        ->title('Pairing code aangemaakt')
                        ->body('Ververs deze pagina; de installatie-oneliner staat in de Pi-sectie hieronder.')
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl());
                }),
        ];
    }

    public function cancelPairing(string $ulid): void
    {
        $printer = Printer::query()
            ->where('ulid', $ulid)
            ->whereNull('paired_at')
            ->whereNotNull('pairing_code')
            ->first();

        if (! $printer) {
            Notification::make()
                ->title('Pairing niet gevonden')
                ->body('Deze pairing is al voltooid of al verwijderd.')
                ->warning()
                ->send();

            $this->redirect(static::getUrl());

            return;
        }

        $printer->delete();

        Notification::make()
            ->title('Pairing geannuleerd')
            ->body('De openstaande pairing code is verwijderd.')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }

    private function pendingPairings(): \Illuminate\Support\Collection
    {
        return Printer::query()
            ->whereNotNull('pairing_code')
            ->where('pairing_expires_at', '>', now())
            ->whereNull('paired_at')
            ->orderByDesc('created_at')
            ->get();
    }

    private function pairingInstallUrl(Printer $printer, string $variant = 'native'): string
    {
        $route = $variant === 'docker'
            ? 'dashed.print-queue.installer-docker'
            : 'dashed.print-queue.installer';

        return URL::temporarySignedRoute(
            $route,
            $printer->pairing_expires_at ?? now()->addHours(2),
            ['code' => $printer->pairing_code],
        );
    }

    public function form(Schema $schema): Schema
    {
        $pending = $this->pendingPairings();

        return $schema->schema([
            Section::make('Pair een nieuw apparaat (Raspberry Pi of NAS)')
                ->description('Klik op "Pair een nieuw apparaat" bovenaan deze pagina om een pairing code te genereren. Daarna verschijnt hier het installatie-commando in twee varianten: een voor Pi/Linux en een voor een NAS via Docker.')
                ->columnSpanFull()
                ->schema([
                    Placeholder::make('pairing_instructions')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(function () use ($pending): HtmlString {
                            if ($pending->isEmpty()) {
                                return new HtmlString(
                                    '<div style="background-color: #f3f4f6; border-radius: 0.5rem; padding: 1rem; color: #374151;">'
                                    . 'Geen openstaande pairing codes. Klik op de groene "Pair een nieuw apparaat" knop hierboven om er een aan te maken.'
                                    . '</div>'
                                );
                            }

                            $html = '<div style="display: flex; flex-direction: column; gap: 1rem;">';
                            foreach ($pending as $printer) {
                                $nativeUrl = $this->pairingInstallUrl($printer, 'native');
                                $dockerUrl = $this->pairingInstallUrl($printer, 'docker');
                                $nativeOneLiner = 'curl -fsSL "' . $nativeUrl . '" | sudo bash';
                                $dockerOneLiner = 'curl -fsSL "' . $dockerUrl . '" | sudo bash';
                                $expiresLabel = $printer->pairing_expires_at?->diffForHumans();

                                $html .= '<div style="background-color: #d1fae5; border-left: 4px solid #059669; border-radius: 0.5rem; padding: 1rem; color: #064e3b;">'
                                    . '<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;">'
                                    . '<div>'
                                    . '<strong>Pairing code: ' . e($printer->pairing_code) . '</strong>'
                                    . ' <span style="color: #4b5563; font-weight: normal;">(verloopt ' . e($expiresLabel) . ')</span>'
                                    . '</div>'
                                    . '<button type="button" wire:click="cancelPairing(\'' . e($printer->ulid) . '\')" wire:confirm="Weet je zeker dat je deze openstaande pairing wilt verwijderen?" style="background-color: #dc2626; color: #ffffff; border: none; padding: 0.375rem 0.75rem; border-radius: 0.375rem; font-size: 0.75rem; cursor: pointer; font-weight: 500;">Annuleer pairing</button>'
                                    . '</div>'
                                    . '<p style="margin-top: 0.5rem;">Kies de variant die bij je host past en plak het commando in een SSH-sessie naar het apparaat.</p>'

                                    . '<div style="margin-top: 1rem;">'
                                    . '<div style="font-weight: 600; margin-bottom: 0.25rem;">Optie A: Raspberry Pi of Linux server (Debian/Ubuntu, systemd)</div>'
                                    . '<div style="color: #4b5563; font-size: 0.8125rem; margin-bottom: 0.5rem;">Installeert native via apt + systemd. Snelst en simpelst voor een Pi.</div>'
                                    . '<div style="display: flex; gap: 0.5rem; align-items: stretch; flex-wrap: wrap;">'
                                    . '<code style="background-color: #111827; color: #f3f4f6; padding: 0.75rem; border-radius: 0.375rem; font-family: ui-monospace, monospace; font-size: 0.8125rem; word-break: break-all; flex: 1; min-width: 0; line-height: 1.4;">' . e($nativeOneLiner) . '</code>'
                                    . '<button type="button" onclick="navigator.clipboard.writeText(\'' . e($nativeOneLiner) . '\'); this.textContent=\'Gekopieerd\'; setTimeout(()=>this.textContent=\'Kopieer\',1500);" style="background-color: #059669; color: #ffffff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.8125rem; cursor: pointer; font-weight: 500; white-space: nowrap;">Kopieer</button>'
                                    . '</div>'
                                    . '</div>'

                                    . '<div style="margin-top: 1rem;">'
                                    . '<div style="font-weight: 600; margin-bottom: 0.25rem;">Optie B: NAS, server of andere Linux met Docker</div>'
                                    . '<div style="color: #4b5563; font-size: 0.8125rem; margin-bottom: 0.5rem;">Werkt op Synology, QNAP, UnRAID, TrueNAS Scale, Asustor en elke Docker-host. Container regelt CUPS + Python intern.</div>'
                                    . '<div style="display: flex; gap: 0.5rem; align-items: stretch; flex-wrap: wrap;">'
                                    . '<code style="background-color: #111827; color: #f3f4f6; padding: 0.75rem; border-radius: 0.375rem; font-family: ui-monospace, monospace; font-size: 0.8125rem; word-break: break-all; flex: 1; min-width: 0; line-height: 1.4;">' . e($dockerOneLiner) . '</code>'
                                    . '<button type="button" onclick="navigator.clipboard.writeText(\'' . e($dockerOneLiner) . '\'); this.textContent=\'Gekopieerd\'; setTimeout(()=>this.textContent=\'Kopieer\',1500);" style="background-color: #4f46e5; color: #ffffff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.8125rem; cursor: pointer; font-weight: 500; white-space: nowrap;">Kopieer</button>'
                                    . '</div>'
                                    . '</div>'

                                    . '<p style="margin-top: 0.75rem; font-size: 0.8125rem;">Beide varianten detecteren USB-printers automatisch. Voor netwerkprinters: zie de uitleg-sectie onderaan deze pagina.</p>'
                                    . '</div>';
                            }
                            $html .= '</div>';

                            return new HtmlString($html);
                        }),
                ]),
            Section::make('Automatisch printen')
                ->columnSpanFull()
                ->schema([
                    Toggle::make('auto_print_on_new_order')
                        ->label('Automatisch pakbon printen bij nieuwe bestelling')
                        ->helperText('Maakt automatisch een print job aan zodra een nieuwe bestelling wordt geplaatst.'),
                    Toggle::make('auto_print_label_on_generated')
                        ->label('Automatisch verzendlabel printen zodra label is aangemaakt')
                        ->helperText('Voegt het verzendlabel toe aan de print queue zodra een verzendlabel is gegenereerd.'),
                ])
                ->columns(1),
            Section::make('Sync en health')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('label_sync_interval_minutes')
                        ->label('Sync interval verzendlabels (minuten)')
                        ->helperText('Hoe vaak verzendlabels van shipping providers worden opgehaald.')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(60),
                    TextInput::make('health_check_threshold_seconds')
                        ->label('Health check threshold (seconden)')
                        ->helperText('Een printer wordt als offline gemarkeerd als de laatste ping langer geleden is dan dit aantal seconden.')
                        ->numeric()
                        ->minValue(15)
                        ->maxValue(600),
                ])
                ->columns(2),
            Section::make('Retentie')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('job_retention_days')
                        ->label('Bewaar afgeronde print jobs (dagen)')
                        ->helperText('Afgeronde print jobs ouder dan dit aantal dagen worden opgeschoond.')
                        ->numeric()
                        ->minValue(7),
                    TextInput::make('failed_job_retention_days')
                        ->label('Bewaar mislukte print jobs (dagen)')
                        ->helperText('Mislukte print jobs ouder dan dit aantal dagen worden opgeschoond.')
                        ->numeric()
                        ->minValue(30),
                ])
                ->columns(2),
            Section::make('Wat doet het install script?')
                ->columnSpanFull()
                ->collapsible()
                ->collapsed()
                ->schema([
                    Placeholder::make('pi_setup_background')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(fn () => new HtmlString(
                            view('dashed-ecommerce-core::filament.pages.print-queue-pi-setup')->render()
                        )),
                ]),
        ])->statePath('data');
    }

    public function submit(): void
    {
        $formState = $this->form->getState();

        Customsetting::set('print_queue.auto_print_on_new_order', (bool) ($formState['auto_print_on_new_order'] ?? false));
        Customsetting::set('print_queue.auto_print_label_on_generated', (bool) ($formState['auto_print_label_on_generated'] ?? false));
        Customsetting::set('print_queue.health_check_threshold_seconds', (int) ($formState['health_check_threshold_seconds'] ?? 60));
        Customsetting::set('print_queue.label_sync_interval_minutes', (int) ($formState['label_sync_interval_minutes'] ?? 1));
        Customsetting::set('print_queue.job_retention_days', (int) ($formState['job_retention_days'] ?? 90));
        Customsetting::set('print_queue.failed_job_retention_days', (int) ($formState['failed_job_retention_days'] ?? 365));

        Notification::make()
            ->title('De print queue instellingen zijn opgeslagen')
            ->success()
            ->send();
    }
}
