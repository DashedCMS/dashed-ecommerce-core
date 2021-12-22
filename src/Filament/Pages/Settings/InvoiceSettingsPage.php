<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceCore\Models\User;

class InvoiceSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-report';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Facturatie instellingen';
    protected static ?string $navigationGroup = 'Overige';
    protected static ?string $title = 'Facturatie instellingen';

    protected static string $view = 'qcommerce-ecommerce-core::settings.pages.invoice-settings';

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        foreach ($sites as $site) {
//            $site['notification_form_inputs_emails'] = '';
//            $notificationFormInputsEmails = Customsetting::get('notification_form_inputs_emails', $site['id'], '[]');
            $formData["notification_form_inputs_emails_{$site['id']}"] = json_decode(Customsetting::get('notification_form_inputs_emails', $site['id'], '{}'));
//            if ($notificationFormInputsEmails) {
//                foreach (json_decode($notificationFormInputsEmails) as $notificationFormInputsEmail) {
//                    $site['notification_form_inputs_emails'] .= $notificationFormInputsEmail . ',';
//                }
//            }
        }

        $this->form->fill($formData);
    }

    protected function getFormSchema(): array
    {
        $sites = Sites::getSites();
        $tabGroups = [];

        $tabs = [];
        foreach ($sites as $site) {
            $schema = [
                Placeholder::make('label')
                    ->label("Notificaties voor {$site['name']}")
                    ->content('Stel extra opties in voor de notificaties.'),
                TagsInput::make("notification_form_inputs_emails_{$site['id']}")
                    ->suggestions(User::where('role', 'admin')->pluck('email')->toArray())
                    ->label('Emails om de bevestigingsmail van een formulier aanvraag naar te sturen')
                    ->placeholder('Voer een email in')
                    ->reactive()
                    ->required(),
            ];

            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema($schema)
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]);
        }
        $tabGroups[] = Tabs::make('Sites')
            ->tabs($tabs);

        return $tabGroups;
    }

    public function submit()
    {
        $sites = Sites::getSites();
        $formState = $this->form->getState();

        foreach ($sites as $site) {
            $emails = $this->form->getState()["notification_form_inputs_emails_{$site['id']}"];
            foreach ($emails as $key => $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    unset($emails[$key]);
                }
            }
            Customsetting::set('notification_form_inputs_emails', $emails, $site['id']);
            $formState["notification_form_inputs_emails_{$site['id']}"] = $emails;
        }

        $this->form->fill($formState);
        $this->notify('success', 'De formulier instellingen zijn opgeslagen');
    }
}
