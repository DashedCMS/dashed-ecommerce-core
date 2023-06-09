<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingMethodResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Qubiqx\QcommerceEcommerceCore\Models\ShippingClass;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingMethodResource;

class EditShippingMethod extends EditRecord
{
    use Translatable;

    protected static string $resource = ShippingMethodResource::class;

    public function afterFill(): void
    {
        foreach (ShippingClass::get() as $shippingClass) {
            $shippingMethodClass = $this->record->shippingMethodClasses()->where('shipping_class_id', $shippingClass->id)->first();
            if ($shippingMethodClass) {
                $this->form->fill(array_merge($this->form->getState(), [
                    "shipping_class_costs_$shippingClass->id" => $shippingMethodClass->costs,
                ]));
            }
        }
    }

    protected function mutateFormDataBeforeSave($data): array
    {
        foreach (ShippingClass::get() as $shippingClass) {
            //            dump($shippingClass->id);
            if (isset($data["shipping_class_costs_$shippingClass->id"])) {
                $value = $data["shipping_class_costs_$shippingClass->id"];

                $shippingMethodClass = $this->record->shippingMethodClasses()->where('shipping_class_id', $shippingClass->id)->first();

                if (! $shippingMethodClass) {
                    $shippingMethodClass = $this->record->shippingMethodClasses()->create([
                        'shipping_class_id' => $shippingClass->id,
                    ]);
                }

                $shippingMethodClass->costs = $value;
                $shippingMethodClass->save();
                //                $this->form->getState()["shipping_class_costs_$shippingClass->id"] = null;
                unset($data["shipping_class_costs_$shippingClass->id"]);
                //                dump($this->form->getState()["shipping_class_costs_$shippingClass->id"]);
                //                unset($this->form->getState()["shipping_class_costs_$shippingClass->id"]);
            }
        }

        return $data;
    }
}
