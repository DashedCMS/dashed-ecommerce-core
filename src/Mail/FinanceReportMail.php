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

class FinanceReportMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public string $hash;

    public ?string $subjectString = null;

    public ?string $filePath;

    public function __construct(string $hash, ?string $subjectString = null, ?string $filePath = null)
    {
        $this->hash = $hash;
        $this->subjectString = $subjectString;
        $this->filePath = $filePath;
    }

    public static function emailTemplateName(): string
    {
        return 'Geëxporteerd financieel rapport';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden met een geëxporteerd financieel rapport als bijlage.';
    }

    public static function defaultSubject(): string
    {
        return 'Geëxporteerd financieel rapport';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Je financiële rapport staat klaar', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>In de bijlage vind je het gevraagde financiële rapport.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
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
                $this->subjectString ?: Translation::get('exported-finance-report-email-subject', 'finance-report', 'Geëxporteerd financieel rapport'),
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
                ->subject($this->subjectString ?: Translation::get('exported-finance-report-email-subject', 'finance-report', 'Geëxporteerd financieel rapport'))
                ->with([
                    'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                ]);
        }

        $relativePath = $this->filePath ?: 'dashed/tmp-exports/' . $this->hash . '/financial-reports/financial-report.pdf';
        $mail->attachFromStorageDisk('public', $relativePath, $this->subjectString ? $this->subjectString . '.pdf' : (Customsetting::get('site_name') . ' - exported finance report.pdf'));

        return $mail;
    }
}
