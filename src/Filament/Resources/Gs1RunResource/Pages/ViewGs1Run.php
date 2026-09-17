<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\Gs1RunResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Services\Gs1\Gs1Assigner;
use Dashed\DashedEcommerceCore\Services\Gs1\Gs1MissingFields;
use Dashed\DashedEcommerceCore\Services\Gs1\Gs1ReferenceData;
use Dashed\DashedEcommerceCore\Filament\Resources\Gs1RunResource;
use Dashed\DashedEcommerceCore\Services\Gs1\Gs1RunLockedException;

class ViewGs1Run extends ViewRecord
{
    protected static string $resource = Gs1RunResource::class;

    /**
     * Alleen-lezen (bekijken, downloaden) mag met View:Product. Alles wat
     * iets schrijft vraagt Edit:Product, via de policy van de run.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('fillMissing')
                ->label(__('Ontbrekende velden invullen'))
                ->icon('heroicon-o-pencil-square')
                ->authorize(fn () => auth()->user()?->can('update', $this->record) ?? false)
                ->visible(fn () => $this->record->isConcept() && $this->missingGroups() !== [])
                ->modalDescription(__('Wat je kiest wordt op de categorie bewaard, of als winkelstandaard voor producten zonder categorie. Een volgende keer wordt het niet meer gevraagd.'))
                ->schema(fn () => $this->missingSchema())
                ->action(function (array $data) {
                    try {
                        $this->missingFields()->store($data['answers'] ?? []);
                    } catch (\InvalidArgumentException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('Velden opgeslagen'))->success()->send();
                }),
            Action::make('assign')
                ->label(__('Toewijzen en bestand maken'))
                ->icon('heroicon-o-check')
                ->authorize(fn () => auth()->user()?->can('update', $this->record) ?? false)
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription(__('De codes uit het voorbeeld worden op de producten gezet en het uploadbestand wordt gemaakt. Daarna kun je deze run niet opnieuw toewijzen.'))
                ->visible(fn () => $this->record->isConcept())
                ->action(function () {
                    try {
                        app(Gs1Assigner::class)->apply($this->record);
                    } catch (Gs1RunLockedException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return;
                    } catch (\Throwable $exception) {
                        report($exception);
                        $this->record->refresh();
                        Notification::make()
                            ->title(__('Toewijzen is mislukt'))
                            ->body(__('Er is niets toegewezen. De fout is gelogd; probeer het opnieuw of neem contact op met de beheerder.'))
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    $this->record->refresh();
                    Notification::make()
                        ->title(__(':aantal codes toegewezen', ['aantal' => $this->record->summary['assigned'] ?? 0]))
                        ->success()
                        ->send();
                }),
            Action::make('download')
                ->label(__('Uploadbestand downloaden'))
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => filled($this->record->result_path) && Storage::disk('local')->exists($this->record->result_path))
                ->action(fn () => response()->download(Storage::disk('local')->path($this->record->result_path))),
            Action::make('close')
                ->label(__('Afsluiten'))
                ->icon('heroicon-o-lock-closed')
                ->authorize(fn () => auth()->user()?->can('update', $this->record) ?? false)
                ->requiresConfirmation()
                ->modalDescription(__('Na afsluiten kun je niets meer terugdraaien in deze run.'))
                ->visible(fn () => $this->record->isToegewezen())
                ->action(function () {
                    app(Gs1Assigner::class)->close($this->record);
                    $this->record->refresh();
                }),
        ];
    }

    private function missingFields(): Gs1MissingFields
    {
        return new Gs1MissingFields(Gs1ReferenceData::fromArray($this->record->reference_data ?? []), $this->record->site_id);
    }

    private function missingGroups(): array
    {
        return $this->missingFields()->groups(
            Product::query()->needsGs1Code()->with(['productCategories', 'productGroup'])->lazyById(200)
        );
    }

    private function missingSchema(): array
    {
        $reference = Gs1ReferenceData::fromArray($this->record->reference_data ?? []);
        $sections = [];

        foreach ($this->missingGroups() as $group) {
            $fields = [];
            foreach ($group['fields'] as $field) {
                if (! Gs1MissingFields::answerable($field)) {
                    continue;
                }

                $name = "answers.{$group['key']}.{$field}";
                $label = Gs1MissingFields::label($field);

                $fields[] = match (true) {
                    $field === 'quantity' => TextInput::make($name)->label($label)->numeric()->minValue(1),
                    $field === 'brand' => TextInput::make($name)->label($label)->maxLength(70),
                    default => Select::make($name)->label($label)->options($reference->options($field))->searchable(),
                };
            }

            if ($fields !== []) {
                $sections[] = Section::make(__(':groep (:aantal producten)', ['groep' => $group['label'], 'aantal' => count($group['product_ids'])]))
                    ->columns(2)
                    ->schema($fields);
            }
        }

        return $sections;
    }
}
