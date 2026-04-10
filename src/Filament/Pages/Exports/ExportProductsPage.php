<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Exports;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Dashed\DashedEcommerceCore\Jobs\ExportProductsJob;

class ExportProductsPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationLabel = 'Exporteer producten';
    protected static string | UnitEnum | null $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer producten';
    protected static ?int $navigationSort = 100000;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_exports');
    }


    protected string $view = 'dashed-ecommerce-core::exports.pages.export-simple';

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Exporteer')->columnSpanFull()
                    ->schema([
                        Toggle::make('only_public_showable')
                            ->label('Exporteer alleen openbare producten')
                            ->default(true)
                            ->helperText('Indien aangevinkt, worden alleen openbare producten geëxporteerd.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        ExportProductsJob::dispatch(
            auth()->user()->email,
            $this->form->getState()['only_public_showable'] ?? false,
            auth()->id(),
        );
        Notification::make()
            ->title('De export wordt klaargemaakt en naar je toe gemaild')
            ->success()
            ->send();
    }
}
