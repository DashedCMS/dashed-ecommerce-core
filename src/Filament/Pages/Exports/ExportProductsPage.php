<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Exports;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Dashed\DashedEcommerceCore\Jobs\ExportProductsJob;

class ExportProductsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationLabel = 'Exporteer producten';
    protected static ?string $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer producten';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'dashed-ecommerce-core::exports.pages.export';

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Exporteer')
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
        ExportProductsJob::dispatch(auth()->user()->email, $this->form->getState()['only_public_showable'] ?? false);
        Notification::make()
            ->title('De export wordt klaargemaakt en naar je toe gemaild')
            ->success()
            ->send();
    }
}
