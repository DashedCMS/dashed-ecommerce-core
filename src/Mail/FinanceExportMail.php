<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;

class FinanceExportMail extends Mailable implements RegistersEmailTemplate, SendsToTelegram
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public string $hash;

    public ?string $subjectString = '';

    public ?string $filePath;

    public function __construct(string $hash, ?string $subjectString = '', ?string $filePath = null)
    {
        $this->hash = $hash;
        $this->subjectString = $subjectString;
        $this->filePath = $filePath;
    }

    public static function emailTemplateName(): string
    {
        return 'Geëxporteerde factuur';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden met een geëxporteerde factuur als bijlage.';
    }

    public static function defaultSubject(): string
    {
        return 'Geëxporteerde factuur';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Je factuur staat klaar', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>In de bijlage vind je de gevraagde factuur.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        return [
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public function build()
    {
        $context = ['siteName' => Customsetting::get('site_name')];
        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                $this->subjectString ?: Translation::get('exported-invoice-email-subject', 'orders', 'Geëxporteerde factuur'),
                $context
            );
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));
            $mail = $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        } else {
            $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.exported-invoice')
                ? config('dashed-core.site_theme', 'dashed') . '.emails.exported-invoice'
                : 'dashed-ecommerce-core::emails.exported-invoice';

            $mail = $this->view($view)
                ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
                ->subject($this->subjectString ?: Translation::get('exported-invoice-email-subject', 'orders', 'Geëxporteerde factuur'))
                ->with([
                    'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                ]);
        }

        $relativePath = $this->filePath ?: 'dashed/tmp-exports/' . $this->hash . '/invoices/exported-invoice.pdf';
        $mail->attachFromStorageDisk('public', $relativePath, $this->subjectString ? $this->subjectString . '.pdf' : (Customsetting::get('site_name') . ' - exported invoice.pdf'));

        return $mail;
    }

    public function telegramSummary(): TelegramSummary
    {
        return new TelegramSummary(
            title: 'Finance export gereed',
            fields: [
                'Onderwerp' => $this->subjectString ?: 'Finance export',
                'Bestand' => $this->filePath ? basename($this->filePath) : null,
            ],
            emoji: '💰',
        );
    }

    public static function makeForTest(): ?self
    {
        return new self(
            hash: 'test-hash',
            subjectString: 'Finance export (test)',
            filePath: null,
        );
    }
}
