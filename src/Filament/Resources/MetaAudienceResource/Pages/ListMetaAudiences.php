<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\MetaAudienceResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Models\MetaAudience;
use Dashed\DashedEcommerceCore\Filament\Resources\MetaAudienceResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\MetaAudienceSettingsPage;

class ListMetaAudiences extends ListRecords
{
    protected static string $resource = MetaAudienceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label(__('Instellingen'))
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(MetaAudienceSettingsPage::getUrl()),
            Action::make('create')
                ->label(__('Nieuwe doelgroep'))
                ->icon('heroicon-o-plus')
                ->schema(MetaAudienceResource::formFields())
                ->action(fn (array $data) => MetaAudience::create($data)),
        ];
    }
}
