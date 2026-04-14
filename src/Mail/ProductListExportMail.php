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

class ProductListExportMail extends Mailable implements RegistersEmailTemplate, SendsToTelegram
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
        return 'Geëxporteerde productlijst';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden met een geëxporteerde productlijst als bijlage.';
    }

    public static function defaultSubject(): string
    {
        return 'Geëxporteerde productlijst';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Je export staat klaar', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>In de bijlage vind je de geëxporteerde productlijst.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
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
        $relativePath = $this->filePath ?: 'dashed/tmp-exports/' . $this->hash . '/product-lists/product-list.xlsx';

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                Translation::get('exported-product-list-email-subject', 'products', 'Geëxporteerde productlijst'),
                $context
            );
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));
            $mail = $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        } else {
            $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.exported-product-list')
                ? config('dashed-core.site_theme', 'dashed') . '.emails.exported-product-list'
                : 'dashed-ecommerce-core::emails.exported-product-list';

            $mail = $this->view($view)
                ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
                ->subject(Translation::get('exported-product-list-email-subject', 'products', 'Geëxporteerde productlijst'))
                ->with([
                    'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                ]);
        }

        $mail->attachFromStorageDisk('dashed', $relativePath, Customsetting::get('site_name') . ' - geexporteerde productlijst.xlsx', [
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        return $mail;
    }

    public function telegramSummary(): TelegramSummary
    {
        return new TelegramSummary(
            title: 'Producten export gereed',
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
