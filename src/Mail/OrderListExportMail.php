<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;

class OrderListExportMail extends Mailable implements RegistersEmailTemplate, SendsToTelegram
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public string $hash;

    public ?string $filePath;

    public function __construct(string $hash, ?string $filePath = null)
    {
        $this->hash = $hash;
        $this->filePath = $filePath;
    }

    public static function emailTemplateName(): string
    {
        return 'Geëxporteerde bestellijst';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden met een geëxporteerde bestellijst als bijlage.';
    }

    public static function defaultSubject(): string
    {
        return 'Export van bestellingen';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Je export staat klaar', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>In de bijlage vind je het geëxporteerde overzicht van bestellingen.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        return ['siteName' => Customsetting::get('site_name')];
    }

    public function build()
    {
        $context = ['siteName' => Customsetting::get('site_name')];
        $templateHtml = $this->renderFromTemplate($context);
        $relativePath = $this->filePath ?: 'dashed/tmp-exports/' . $this->hash . '/order-lists/order-list.xlsx';

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                Translation::get('exported-order-list-email-subject', 'orders', 'Export van bestellingen'),
                $context
            );
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));
            $mail = $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        } else {
            $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.exported-order-list')
                ? config('dashed-core.site_theme', 'dashed') . '.emails.exported-order-list'
                : 'dashed-ecommerce-core::emails.exported-order-list';

            $mail = $this->view($view)
                ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
                ->subject(Translation::get('exported-order-list-email-subject', 'orders', 'Export van bestellingen'))
                ->with([
                    'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                ]);
        }

        $mail->attachFromStorageDisk('dashed', $relativePath, Customsetting::get('site_name') . ' - export van bestellingen.xlsx', [
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        return $mail;
    }

    public function telegramSummary(): TelegramSummary
    {
        return new TelegramSummary(
            title: 'Order export gereed',
            fields: [
                'Bestand' => $this->filePath ? basename($this->filePath) : null,
            ],
            emoji: '📋',
        );
    }

    public static function makeForTest(): ?self
    {
        return new self(
            hash: 'test-hash',
            filePath: null,
        );
    }
}
