<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ApiClientResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\CheckboxList;
use Dashed\DashedEcommerceCore\Models\ApiClient;
use Dashed\DashedEcommerceCore\Filament\Resources\ApiClientResource;

class ListApiClients extends ListRecords
{
    protected static string $resource = ApiClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label(__('Nieuwe koppeling'))
                ->icon('heroicon-o-key')
                ->schema([
                    TextInput::make('name')
                        ->label(__('Naam'))
                        ->helperText(__('Bijvoorbeeld de partij die de sleutel krijgt, zodat je hem later terugvindt.'))
                        ->required()
                        ->maxLength(100),
                    CheckboxList::make('abilities')
                        ->label(__('Toegang'))
                        ->options(ApiClient::abilityOptions())
                        ->default([ApiClient::ABILITY_ORDERS_EXPORT])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    [, $token] = ApiClient::createWithToken($data['name'], $data['abilities']);
                    ApiClientResource::showTokenOnce(__('Koppeling aangemaakt'), $token);
                }),
        ];
    }
}
