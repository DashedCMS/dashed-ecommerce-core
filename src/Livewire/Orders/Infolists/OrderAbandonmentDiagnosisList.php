<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Services\Orders\OrderAbandonmentAnalyzer;
use Dashed\DashedEcommerceCore\Services\Orders\OrderAbandonmentDiagnosis;

class OrderAbandonmentDiagnosisList extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public Order $order;

    protected $listeners = [
        'refreshData' => '$refresh',
    ];

    public function mount($order): void
    {
        $this->order = $order;
    }

    public function getDiagnosisProperty(): ?OrderAbandonmentDiagnosis
    {
        return (new OrderAbandonmentAnalyzer())->analyze($this->order);
    }

    public function infolist(Schema $schema): Schema
    {
        $diagnosis = $this->diagnosis;

        $components = [];

        if ($diagnosis) {
            $color = match ($diagnosis->confidence) {
                'high' => 'danger',
                'medium' => 'warning',
                default => 'gray',
            };

            $components[] = Fieldset::make('abandonment')->columnSpanFull()
                ->label('Waarschijnlijke oorzaak')
                ->schema([
                    TextEntry::make('cause_label')
                        ->hiddenLabel()
                        ->badge()
                        ->color($color)
                        ->state(fn () => $diagnosis->label),
                    TextEntry::make('evidence')
                        ->hiddenLabel()
                        ->visible(count($diagnosis->evidence) > 0)
                        ->state(fn () => new HtmlString(
                            collect($diagnosis->evidence)->map(fn ($line) => e($line))->implode('<br>')
                        )),
                ]);
        }

        return $schema
            ->record($this->order)
            ->components($components);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.abandonment-diagnosis');
    }
}
