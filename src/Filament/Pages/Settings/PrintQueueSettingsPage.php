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
use Illuminate\Support\Str;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedEcommerceCore\Filament\Resources\PrinterResource;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('discover_printers')
                ->label('Auto-import printers van een Pi/NAS')
                ->icon('heroicon-o-magnifying-glass-plus')
                ->color('success')
                ->modalHeading('Auto-import printers van een Pi/NAS')
                ->modalDescription('Genereert een eenmalig curl-commando. Draai het op je Pi/NAS via SSH; het script detecteert alle CUPS-printers daar en registreert ze automatisch in dit CMS, inclusief tokens en daemon-setup.')
                ->modalSubmitActionLabel('Genereer commando')
                ->action(function (): void {
                    $nonce = Str::random(16);
                    $url = URL::temporarySignedRoute(
                        'dashed.print-queue.installer-discover',
                        now()->addHours(24),
                        ['nonce' => $nonce],
                    );

                    $oneLiner = 'curl -fsSL "' . $url . '" | sudo bash';

                    Notification::make()
                        ->title('Auto-import commando gegenereerd')
                        ->body(new HtmlString(
                            '<p style="margin-bottom: 0.5rem;">Plak dit op je Pi/NAS in een SSH-sessie (geldig 24 uur):</p>'
                            . '<code style="display: block; background-color: #111827; color: #f3f4f6; padding: 0.5rem; border-radius: 0.375rem; font-family: ui-monospace, monospace; font-size: 0.75rem; word-break: break-all;">' . e($oneLiner) . '</code>'
                            . '<button type="button" onclick="navigator.clipboard.writeText(\'' . e($oneLiner) . '\'); this.textContent=\'Gekopieerd\'; setTimeout(()=>this.textContent=\'Kopieer commando\',1500);" style="margin-top: 0.5rem; background-color: #059669; color: #ffffff; border: none; padding: 0.375rem 0.75rem; border-radius: 0.375rem; font-size: 0.75rem; cursor: pointer; font-weight: 500;">Kopieer commando</button>'
                        ))
                        ->persistent()
                        ->success()
                        ->send();
                }),
        ];
    }

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

    public function form(Schema $schema): Schema
    {
        $printerListUrl = class_exists(PrinterResource::class) ? PrinterResource::getUrl('index') : null;

        return $schema->schema([
            Section::make('Hoe stel je een printer in?')
                ->columnSpanFull()
                ->schema([
                    Placeholder::make('how_to_setup')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(fn () => new HtmlString(
                            '<div style="font-size: 0.875rem; line-height: 1.55; color: #374151; display: flex; flex-direction: column; gap: 1rem;">'
                            . '<div>'
                            . '<strong>3 stappen om een printer aan te sluiten:</strong>'
                            . '<ol style="list-style: decimal inside; margin-top: 0.5rem;">'
                            . '<li><strong>Op je Pi of NAS:</strong> registreer de printer in CUPS (eenmalig) met <code>sudo lpadmin -p &lt;naam&gt; -E -v &lt;device-uri&gt; -m everywhere</code>. Controleer met <code>lpstat -p</code> welke naam je hebt gekozen.</li>'
                            . '<li><strong>In dit CMS:</strong> ga naar ' . ($printerListUrl ? '<a href="' . e($printerListUrl) . '" style="text-decoration: underline; font-weight: 600;">Print queue &rarr; Printers</a>' : 'Print queue &rarr; Printers') . ', maak een nieuw record, vul je naam in, vul precies dezelfde <em>CUPS naam</em> in als op de Pi, kies type (pakbon/label/beide). Klik daarna op <strong>Genereer token</strong>.</li>'
                            . '<li><strong>Op de Pi of NAS:</strong> kopieer het commando dat in admin verschijnt na het genereren van het token en plak het in een SSH-sessie. Het script installeert een kleine daemon die elke 5 seconden bij dit CMS langs gaat.</li>'
                            . '</ol>'
                            . '</div>'
                            . '<div style="background-color: #eff6ff; border-left: 4px solid #2563eb; border-radius: 0.5rem; padding: 0.75rem; color: #1e3a8a;">'
                            . '<strong>Hoe weet ik de juiste CUPS naam?</strong>'
                            . '<p style="margin-top: 0.25rem;">SSH naar je Pi/NAS en run <code>lpstat -p</code>. Je ziet iets als <code>printer pakbon_brother is idle</code>. Dan is <code>pakbon_brother</code> de naam die je in dit CMS invult.</p>'
                            . '</div>'
                            . '<div style="background-color: #f3f4f6; border-radius: 0.5rem; padding: 0.75rem;">'
                            . '<strong>Tips voor de CUPS-printer aanmaken op een Pi/NAS:</strong>'
                            . '<ul style="list-style: disc inside; margin-top: 0.25rem;">'
                            . '<li>USB-printer: <code>lpinfo -v | grep usb</code> laat de device-URI zien. Dan <code>sudo lpadmin -p mijn_printer -E -v "usb://..." -m everywhere</code>.</li>'
                            . '<li>Netwerk (WiFi/LAN): <code>sudo lpadmin -p mijn_printer -E -v socket://192.168.1.50:9100 -m everywhere</code> voor HP/Brother JetDirect, of <code>ipp://192.168.1.50:631/ipp/print</code> voor IPP.</li>'
                            . '<li>Mooie web-UI om printers te beheren: open <code>http://&lt;pi-ip&gt;:631</code> in je browser na <code>sudo cupsctl ServerAlias=*</code>.</li>'
                            . '<li>Op een NAS: zelfde principe, alleen open je een SSH-sessie via de NAS-beheer-UI (Synology DSM Terminal &amp; SNMP, QNAP Telnet/SSH, UnRAID console, TrueNAS Shell).</li>'
                            . '</ul>'
                            . '</div>'
                            . '</div>'
                        )),
                ]),
            Section::make('Automatisch printen')
                ->columnSpanFull()
                ->schema([
                    Toggle::make('auto_print_on_new_order')
                        ->label('Automatisch pakbon printen bij nieuwe bestelling')
                        ->helperText('Voegt een pakbon-job toe aan de wachtrij zodra een nieuwe bestelling binnenkomt. Dit werkt alleen als minstens 1 printer type "pakbon" of "beide" actief is.'),
                    Toggle::make('auto_print_label_on_generated')
                        ->label('Automatisch verzendlabel printen zodra label is aangemaakt')
                        ->helperText('Pakt verzendlabels op die door MyParcel of Veloyd zijn gegenereerd. Werkt alleen als minstens 1 printer type "label" of "beide" actief is.'),
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
