<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedEcommerceCore\Models\Referral;
use Dashed\DashedEcommerceCore\Filament\Pages\CustomerProfilePage;
use Dashed\DashedEcommerceCore\Services\Referrals\ReferralProgram;
use Dashed\DashedEcommerceCore\Filament\Resources\ReferralResource\Pages\ListReferrals;

/**
 * Overzicht van het verwijsprogramma. Verwijzingen ontstaan vanzelf bij
 * betaling; hier kan een beheerder er een afwijzen of eerder belonen.
 */
class ReferralResource extends Resource
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesResourceLabels;

    protected static ?string $model = Referral::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-plus';
    protected static UnitEnum|string|null $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Verwijzingen';
    protected static ?string $label = 'Verwijzing';
    protected static ?string $pluralLabel = 'Verwijzingen';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['referralCode', 'order', 'rewardDiscountCode']))
            ->columns([
                TextColumn::make('referralCode.email')
                    ->label(__('Verwijzer'))
                    ->url(fn (Referral $record) => $record->referralCode ? CustomerProfilePage::urlFor($record->referralCode->user_id, $record->referralCode->email) : null)
                    ->searchable(),
                TextColumn::make('referred_email')
                    ->label(__('Nieuwe klant'))
                    ->url(fn (Referral $record) => CustomerProfilePage::urlFor($record->order?->user_id, $record->referred_email))
                    ->searchable(),
                TextColumn::make('order.invoice_id')
                    ->label(__('Bestelling'))
                    ->url(fn (Referral $record) => $record->order_id ? OrderResource::getUrl('view', ['record' => $record->order_id]) : null),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Referral::statuses()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Referral::STATUS_REWARDED => 'success',
                        Referral::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('reject_reason')
                    ->label(__('Reden'))
                    ->formatStateUsing(fn (?string $state) => $state ? (Referral::rejectReasons()[$state] ?? $state) : null)
                    ->placeholder('-'),
                TextColumn::make('qualifies_at')
                    ->label(__('Beloning vanaf'))
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('rewardDiscountCode.code')
                    ->label(__('Beloningscode'))
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label(__('Aangemaakt op'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(Referral::statuses()),
            ])
            ->recordActions([
                Action::make('rewardNow')
                    ->label(__('Nu belonen'))
                    ->icon('heroicon-o-gift')
                    ->requiresConfirmation()
                    ->modalDescription(__('De verwijzer krijgt direct zijn kortingscode, ook als de wachttijd nog niet voorbij is.'))
                    ->visible(fn (Referral $record) => $record->status === Referral::STATUS_PENDING && auth()->user()?->can('edit_referral'))
                    ->action(function (Referral $record) {
                        ReferralProgram::reward($record);
                        Notification::make()->title(__('Beloning verstuurd'))->success()->send();
                    }),
                Action::make('reject')
                    ->label(__('Afwijzen'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Referral $record) => $record->status === Referral::STATUS_PENDING && auth()->user()?->can('edit_referral'))
                    ->action(fn (Referral $record) => ReferralProgram::reject($record, 'manual')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferrals::route('/'),
        ];
    }
}
