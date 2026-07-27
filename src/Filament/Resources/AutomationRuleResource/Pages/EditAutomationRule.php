<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource\Pages;

use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource;

class EditAutomationRule extends EditRecord
{
    protected static string $resource = AutomationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Zet een bestaande `schedule`-array uit naar de losse
        // schedule_*-formuliervelden (Task 7), zodat het bewerken van een
        // bestaande tijd-regel het schedule-subformulier voorvult.
        return AutomationRuleResource::scheduleFormDataFromRecord($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['site_id'] = $data['site_id'] ?? Sites::getFirstSite()['id'];

        // Bundelt de losse schedule_*-formuliervelden (Task 7) tot de
        // `schedule`-array en whitelist daarbij de waarden — zie
        // AutomationRuleResource::withScheduleData().
        return AutomationRuleResource::withScheduleData($data);
    }
}
