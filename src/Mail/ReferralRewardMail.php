<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Referral;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ReferralCode;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedEcommerceCore\Services\Referrals\ReferralProgram;

/**
 * De beloning voor de verwijzer: een kortingscode van één keer gebruik.
 * De naam of het adres van de nieuwe klant staat er bewust niet in.
 */
class ReferralRewardMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(public Referral $referral, public DiscountCode $discountCode)
    {
    }

    public static function emailTemplateName(): string
    {
        return 'Verwijsprogramma: beloning (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant die iemand doorverwees, zodra de eerste bestelling van die nieuwe klant betaald is en de wachttijd voorbij is.';
    }

    public static function availableVariables(): array
    {
        return ['firstName', 'discountCode', 'discountValue', 'validUntil', 'referralUrl', 'siteName', 'primaryColor'];
    }

    public static function availableBlockKeys(): array
    {
        return ['heading', 'text', 'button', 'image', 'divider'];
    }

    public static function defaultSubject(): string
    {
        return 'Bedankt voor je verwijzing: :discountValue: korting';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Bedankt, :firstName:!', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Iemand die jij doorverwees heeft voor het eerst bij ons besteld. Als bedankje krijg je <strong>:discountValue:</strong> korting op je volgende bestelling.</p><p>Je kortingscode: <strong>:discountCode:</strong><br>Geldig tot en met :validUntil:.</p>']],
            ['type' => 'text', 'data' => ['body' => '<p>Ken je nog iemand? Deel dan gewoon opnieuw je eigen link: <a href=":referralUrl:">:referralUrl:</a></p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        return [
            'firstName' => 'Jan',
            'discountCode' => 'BEDANKT-AB12CD',
            'discountValue' => CurrencyHelper::formatPrice(10),
            'validUntil' => now()->addDays(90)->format('d-m-Y'),
            'referralUrl' => url('/verwijzing/VRIEND-AB12CD'),
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $referral = Referral::query()->whereNotNull('reward_discount_code_id')->latest()->first();

        return $referral && $referral->rewardDiscountCode ? new self($referral, $referral->rewardDiscountCode) : null;
    }

    public function build()
    {
        /** @var ReferralCode|null $referralCode */
        $referralCode = $this->referral->referralCode;
        $latestOrder = $referralCode
            ? Order::query()->where('email', $referralCode->email)->latest('id')->first()
            : null;
        $locale = $latestOrder?->locale ?: app()->getLocale();

        $value = $this->discountCode->type === 'percentage'
            ? ((int) $this->discountCode->discount_percentage) . '%'
            : CurrencyHelper::formatPrice((float) $this->discountCode->discount_amount);

        $context = [
            'firstName' => (string) ($latestOrder?->first_name ?: $referralCode?->user?->first_name ?: ''),
            'discountCode' => $this->discountCode->code,
            'discountValue' => $value,
            'validUntil' => $this->discountCode->end_date?->format('d-m-Y') ?? '',
            'referralUrl' => $referralCode ? ReferralProgram::url($referralCode) : '',
            'siteName' => Customsetting::get('site_name', $this->referral->site_id),
        ];

        $fallbackSubject = Translation::get('referral-reward-email-subject', 'referral', 'Bedankt voor je verwijzing: :discountValue: korting', 'text', [
            'discountValue' => $value,
        ]);

        $templateHtml = $this->renderFromTemplate($context, $locale);

        if ($templateHtml !== null) {
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'), $locale);

            return $this->html($templateHtml)
                ->from($fromEmail, $fromName)
                ->subject($this->templateSubject($fallbackSubject, $context, $locale));
        }

        return $this->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject($fallbackSubject)
            ->html('<p>' . e(Translation::get('referral-reward-email-body', 'referral', 'Bedankt voor je verwijzing! Je kortingscode is :code:, geldig tot en met :date:.', 'text', [
                'code' => $this->discountCode->code,
                'date' => $context['validUntil'],
            ])) . '</p>');
    }
}
