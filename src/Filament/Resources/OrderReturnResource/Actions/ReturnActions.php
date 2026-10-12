<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource\Actions;

use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Models\EmailTemplate;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Services\OrderReturn\RefundRegistrar;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnableLines;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnProcessor;
use Dashed\DashedEcommerceCore\Services\OrderReturn\FreeShippingReversal;
use Dashed\DashedEcommerceCore\Services\OrderReturn\FreeShippingReversalProposal;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnCustomMail;

/**
 * De acties op een retour, één keer gedefinieerd voor de lijst en de
 * detailpagina. Filament injecteert $record op beide plekken. Elke actie
 * roept alleen een service of modelmethode aan; validatie zit daar, het
 * formulier hier is alleen de nette laag ervoor.
 */
class ReturnActions
{
    /** @return array<int, Action> */
    public static function all(): array
    {
        return [
            self::approve(),
            self::reject(),
            self::process(),
            self::close(),
            self::registerRefund(),
            self::sendEmail(),
        ];
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label(__('Goedkeuren'))
            ->color('success')
            ->visible(fn (OrderReturn $record) => $record->status === OrderReturn::STATUS_REQUESTED)
            ->schema([
                Textarea::make('admin_note')->label(__('Notitie (optioneel)')),
            ])
            ->action(function (OrderReturn $record, array $data) {
                $record->approve($data['admin_note'] ?? null);
                Notification::make()->success()->title(__('Retouraanvraag goedgekeurd'))->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label(__('Afkeuren'))
            ->color('danger')
            ->visible(fn (OrderReturn $record) => in_array($record->status, [OrderReturn::STATUS_REQUESTED, OrderReturn::STATUS_APPROVED], true))
            ->schema(fn (OrderReturn $record) => [
                Textarea::make('rejected_reason')->label(__('Reden'))->required(),
                ...ReturnActionExtensions::fields('reject', $record),
            ])
            ->action(function (OrderReturn $record, array $data) {
                ReturnActionExtensions::runBefore('reject', $record, $data);
                $record->reject($data['rejected_reason']);
                Notification::make()->success()->title(__('Retouraanvraag afgekeurd'))->send();
            });
    }

    public static function process(): Action
    {
        return Action::make('process')
            ->label(__('Verwerken'))
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (OrderReturn $record) => $record->status === OrderReturn::STATUS_APPROVED)
            ->modalHeading(__('Retour verwerken'))
            ->modalDescription(__('Vul per regel in wat er echt terugkwam. 0 betekent niet gecrediteerd. Er wordt een creditorder gemaakt; terugbetalen is een aparte stap.'))
            ->schema(function (OrderReturn $record) {
                $fields = [];
                foreach ($record->lines()->with('orderProduct')->get() as $line) {
                    $remaining = $line->orderProduct ? ReturnableLines::remaining($line->orderProduct) : 0;
                    $fields[] = TextInput::make("lines.{$line->id}")
                        ->label($line->orderProduct?->name ?? __('Regel :id', ['id' => $line->id]))
                        ->helperText(__('Aangemeld: :aangemeld, nog te retourneren: :restant', ['aangemeld' => $line->quantity, 'restant' => $remaining]))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue($remaining)
                        ->live(onBlur: true)
                        ->default(min((int) $line->quantity, $remaining))
                        ->required();
                }

                return [
                    ...self::existingCreditFields($record),
                    Fieldset::make(__('Teruggekomen aantallen'))->columnSpanFull()->schema($fields)->columns(2),
                    Toggle::make('restock')->label(__('Terug op voorraad'))->default(true),
                    Toggle::make('refund_discount')
                        ->label(__('Korting verrekenen'))
                        ->helperText(__('Alleen bij een volledige retour: de vaste korting van de bestelling wordt dan van het creditbedrag afgetrokken, want de klant heeft die korting niet betaald. Bij een deelretour wordt dit geweigerd.'))
                        ->default(false),
                    Toggle::make('charge_shipping')
                        ->label(__('Verzendkosten inhouden'))
                        ->helperText(function (Get $get) use ($record) {
                            return self::reversalHelperText($record, (array) $get('lines'));
                        })
                        ->visible(function (Get $get) use ($record) {
                            return self::reversalProposal($record, (array) $get('lines')) !== null;
                        })
                        ->default(true)
                        ->live(),
                    TextInput::make('charge_shipping_amount')
                        ->label(__('Afwijkend bedrag (optioneel)'))
                        ->helperText(__('Leeg laten houdt het voorgestelde bedrag in. Een hoger bedrag wordt genegeerd.'))
                        ->numeric()
                        ->minValue(0.01)
                        ->visible(function (Get $get) use ($record) {
                            return $get('charge_shipping') && self::reversalProposal($record, (array) $get('lines')) !== null;
                        }),
                    Textarea::make('note')->label(__('Notitie (optioneel)')),
                ];
            })
            ->action(function (OrderReturn $record, array $data) {
                $lines = [];
                foreach ((array) ($data['lines'] ?? []) as $lineId => $quantity) {
                    $lines[] = ['order_return_line_id' => (int) $lineId, 'quantity' => (int) $quantity];
                }

                try {
                    $credit = app(ReturnProcessor::class)->process($record, $lines, [
                        'restock' => (bool) ($data['restock'] ?? true),
                        'refund_discount' => (bool) ($data['refund_discount'] ?? false),
                        'charge_shipping' => (bool) ($data['charge_shipping'] ?? true),
                        'charge_shipping_amount' => $data['charge_shipping_amount'] ?? null,
                        'note' => $data['note'] ?? null,
                        'confirm_existing_credit' => (bool) ($data['confirm_existing_credit'] ?? false),
                    ]);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                // De schakelaar kan verborgen zijn geweest (standaard aan): meld het
                // ingehouden bedrag zodat de beheerder het altijd ziet.
                $reversalLine = $credit->orderProducts()->where('sku', FreeShippingReversal::SKU)->first();
                $body = __('Creditorder :nummer aangemaakt.', ['nummer' => $credit->invoice_id]);
                if ($reversalLine) {
                    $body .= ' '.__('Verzendkosten ingehouden: :bedrag', ['bedrag' => CurrencyHelper::formatPrice($reversalLine->price)]);
                }

                Notification::make()
                    ->success()
                    ->title(__('Retour verwerkt'))
                    ->body($body)
                    ->actions([
                        Action::make('open')->label(__('Open creditorder'))->url(OrderResource::getUrl('view', ['record' => $credit->id])),
                    ])
                    ->send();
            });
    }

    /** @param  array<int|string, mixed>  $lineQuantities  order_return_line_id => aantal */
    protected static function reversalProposal(OrderReturn $record, array $lineQuantities): ?FreeShippingReversalProposal
    {
        if (! $record->order) {
            return null;
        }

        $perOrderProduct = [];
        foreach ($record->lines as $line) {
            $quantity = (int) ($lineQuantities[$line->id] ?? 0);
            $perOrderProduct[(int) $line->order_product_id] = ($perOrderProduct[(int) $line->order_product_id] ?? 0) + $quantity;
        }

        return app(FreeShippingReversal::class)->propose($record->order, $perOrderProduct);
    }

    /** @param  array<int|string, mixed>  $lineQuantities */
    protected static function reversalHelperText(OrderReturn $record, array $lineQuantities): ?string
    {
        $proposal = self::reversalProposal($record, $lineQuantities);

        return $proposal ? __('De bestelling zakt van :voor naar :na en komt onder gratis verzending vanaf :drempel. Voorstel: :bedrag (:methode) inhouden op de terugbetaling.', [
            'voor' => CurrencyHelper::formatPrice($proposal->valueBefore),
            'na' => CurrencyHelper::formatPrice($proposal->valueAfter),
            'drempel' => CurrencyHelper::formatPrice($proposal->threshold),
            'bedrag' => CurrencyHelper::formatPrice($proposal->amount),
            'methode' => $proposal->methodName,
        ]) : null;
    }

    /**
     * Heeft de bestelling al een creditorder, dan staat bovenaan de modal
     * welke, met een vinkje dat aan moet voordat er nog een bij komt. De
     * processor eist dezelfde bevestiging, dit is alleen de nette laag.
     *
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    protected static function existingCreditFields(OrderReturn $record): array
    {
        $existing = $record->order ? ReturnProcessor::existingCreditOrders($record->order) : collect();
        if ($existing->isEmpty()) {
            return [];
        }

        $items = $existing->map(fn ($credit) => '<li><a href="' . e(OrderResource::getUrl('view', ['record' => $credit->id])) . '" target="_blank" class="underline">'
            . e($credit->invoice_id ?: '#' . $credit->id) . '</a> ' . e(CurrencyHelper::formatPrice($credit->total))
            . ' (' . e($credit->created_at?->format('d-m-Y')) . ')</li>')->implode('');

        return [
            Placeholder::make('existing_credit_orders')
                ->label(__('Er is al een creditorder voor deze bestelling'))
                ->content(new HtmlString('<ul class="list-disc ps-5">' . $items . '</ul>'))
                ->columnSpanFull(),
            Checkbox::make('confirm_existing_credit')
                ->label(__('Ik heb de bestaande creditorder gecontroleerd en wil toch een nieuwe aanmaken'))
                ->accepted()
                ->columnSpanFull(),
        ];
    }

    public static function close(): Action
    {
        return Action::make('close')
            ->label(__('Sluiten zonder creditering'))
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->visible(fn (OrderReturn $record) => $record->status === OrderReturn::STATUS_APPROVED)
            ->modalDescription(__('De retour wordt afgesloten zonder creditorder. Wil je de klant iets uitleggen, gebruik dan "Stuur e-mail".'))
            ->schema(fn (OrderReturn $record) => [
                Textarea::make('closed_reason')->label(__('Reden'))->required(),
                ...ReturnActionExtensions::fields('close', $record),
            ])
            ->action(function (OrderReturn $record, array $data) {
                try {
                    ReturnActionExtensions::runBefore('close', $record, $data);
                    $record->close($data['closed_reason']);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }
                Notification::make()->success()->title(__('Retour gesloten'))->send();
            });
    }

    public static function registerRefund(): Action
    {
        return Action::make('registerRefund')
            ->label(__('Terugbetaling registreren'))
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->visible(fn (OrderReturn $record) => $record->status === OrderReturn::STATUS_HANDLED && $record->credit_order_id && ! $record->isRefunded())
            ->modalDescription(fn (OrderReturn $record) => __('Te crediteren: :bedrag. Registreer hier wat je hebt terugbetaald; de klant krijgt een mail.', ['bedrag' => CurrencyHelper::formatPrice($record->refundableAmount())]))
            ->schema(fn (OrderReturn $record) => [
                TextInput::make('amount')
                    ->label(__('Bedrag'))
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->maxValue($record->refundableAmount())
                    ->default($record->refundableAmount()),
                \Filament\Forms\Components\Select::make('method')
                    ->label(__('Betaalmethode'))
                    ->required()
                    ->options(self::refundMethodOptions())
                    ->default('Bankoverschrijving'),
            ])
            ->action(function (OrderReturn $record, array $data) {
                try {
                    app(RefundRegistrar::class)->register($record, (float) $data['amount'], (string) $data['method']);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }
                Notification::make()->success()->title(__('Terugbetaling geregistreerd'))->send();
            });
    }

    /** @return array<string, string> */
    protected static function refundMethodOptions(): array
    {
        $options = ['Bankoverschrijving' => __('Bankoverschrijving'), 'Contant' => __('Contant')];
        foreach (PaymentMethod::query()->orderBy('name')->get() as $method) {
            $name = is_array($method->name) ? ($method->name[app()->getLocale()] ?? reset($method->name)) : $method->name;
            if ($name) {
                $options[$name] = $name;
            }
        }

        return $options;
    }

    public static function sendEmail(): Action
    {
        return Action::make('sendEmail')
            ->label(__('Stuur e-mail'))
            ->icon('heroicon-o-envelope')
            ->color('primary')
            ->schema([
                TextInput::make('email')
                    ->label(__('E-mailadres'))
                    ->email()
                    ->required()
                    ->default(fn (OrderReturn $record) => $record->email),
                TextInput::make('subject')
                    ->label(__('Onderwerp'))
                    ->required()
                    ->default(function () {
                        $template = EmailTemplate::forMailable(OrderReturnCustomMail::emailTemplateKey());

                        return $template?->getTranslation('subject', app()->getLocale(), useFallbackLocale: true)
                            ?: OrderReturnCustomMail::defaultSubject();
                    }),
                Placeholder::make('variabelen')
                    ->label(__('Beschikbare variabelen'))
                    ->content(fn () => OrderReturnCustomMail::usableVariablesHint()),
                RichEditor::make('message')
                    ->label(__('Bericht'))
                    ->required()
                    ->default(fn () => OrderReturnCustomMail::defaultMessage()),
            ])
            ->action(function (OrderReturn $record, array $data) {
                $record->sendCustomEmail($data['subject'], $data['message'], $data['email']);
                Notification::make()->success()->title(__('Bericht naar klant verstuurd'))->send();
            });
    }
}
