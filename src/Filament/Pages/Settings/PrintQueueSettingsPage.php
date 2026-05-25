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
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;

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
            'auto_print_on_new_order' => (bool) Customsetting::get('print_queue.auto_print_on_new_order', null, false),
            'auto_print_label_on_generated' => (bool) Customsetting::get('print_queue.auto_print_label_on_generated', null, false),
            'health_check_threshold_seconds' => (int) Customsetting::get('print_queue.health_check_threshold_seconds', null, 60),
            'label_sync_interval_minutes' => (int) Customsetting::get('print_queue.label_sync_interval_minutes', null, 1),
            'job_retention_days' => (int) Customsetting::get('print_queue.job_retention_days', null, 90),
            'failed_job_retention_days' => (int) Customsetting::get('print_queue.failed_job_retention_days', null, 365),
        ];

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
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
            Section::make('Installatie op een Raspberry Pi')
                ->description('Stap-voor-stap instructies om een nieuwe Raspberry Pi aan te sluiten op de print queue.')
                ->columnSpanFull()
                ->collapsible()
                ->collapsed()
                ->schema([
                    Placeholder::make('pi_setup_instructions')
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
