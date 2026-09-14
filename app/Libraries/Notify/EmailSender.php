<?php

declare(strict_types=1);

namespace App\Libraries\Notify;

use App\Models\EmailSettingModel;
use CodeIgniter\Email\Email;

/**
 * Sends receipt/notification emails using whichever provider is configured
 * under Settings & Integrations > Email:
 *  - smtp: any SMTP relay, including Amazon SES's SMTP interface (the
 *    practical way to use SES without pulling in the AWS SDK)
 *  - sendgrid: SendGrid's HTTP API (v3/mail/send)
 */
class EmailSender
{
    public function __construct(private readonly EmailSettingModel $settings = new EmailSettingModel())
    {
    }

    public function isConfigured(): bool
    {
        $s = $this->settings->current();

        return ! empty($s['from_address']) && $s['status'] === 'active';
    }

    /**
     * @param string $html HTML body. A plain-text alternative is derived automatically.
     */
    public function send(string $toEmail, string $toName, string $subject, string $html): bool
    {
        $settings = $this->settings->current();

        if (! $this->isConfigured()) {
            log_message('info', 'Email gateway not configured — email not sent. to={to} subject={subject}', [
                'to' => $toEmail, 'subject' => $subject,
            ]);

            return ENVIRONMENT !== 'production';
        }

        return match ($settings['provider']) {
            'sendgrid' => $this->sendViaSendgrid($settings, $toEmail, $toName, $subject, $html),
            default    => $this->sendViaSmtp($settings, $toEmail, $toName, $subject, $html),
        };
    }

    private function sendViaSmtp(array $settings, string $toEmail, string $toName, string $subject, string $html): bool
    {
        $email = new Email([
            'protocol'   => 'smtp',
            'SMTPHost'   => $settings['smtp_host'] ?? '',
            'SMTPPort'   => (int) ($settings['smtp_port'] ?? 587),
            'SMTPUser'   => $settings['smtp_user'] ?? '',
            'SMTPPass'   => $this->settings->smtpPass() ?? '',
            'SMTPCrypto' => $settings['smtp_crypto'] ?? 'tls',
            'mailType'   => 'html',
            'charset'    => 'UTF-8',
        ]);

        $email->setFrom($settings['from_address'], $settings['from_name'] ?? 'Hithachintak Abhiyan');
        $email->setTo($toEmail, $toName);
        $email->setSubject($subject);
        $email->setMessage($html);

        $ok = $email->send(false);
        if (! $ok) {
            log_message('error', 'SMTP send failed: {debug}', ['debug' => $email->printDebugger(['headers'])]);
        }

        return $ok;
    }

    private function sendViaSendgrid(array $settings, string $toEmail, string $toName, string $subject, string $html): bool
    {
        $apiKey = $this->settings->apiKey();
        if (! $apiKey) {
            log_message('error', 'SendGrid send failed: no API key configured.');

            return false;
        }

        $payload = [
            'personalizations' => [[
                'to' => [['email' => $toEmail, 'name' => $toName]],
            ]],
            'from'    => ['email' => $settings['from_address'], 'name' => $settings['from_name'] ?? 'Hithachintak Abhiyan'],
            'subject' => $subject,
            'content' => [['type' => 'text/html', 'value' => $html]],
        ];

        try {
            $client   = service('curlrequest', ['timeout' => 10]);
            $response = $client->request('POST', 'https://api.sendgrid.com/v3/mail/send', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'content-type'  => 'application/json',
                ],
                'json'        => $payload,
                'http_errors' => false,
            ]);

            $ok = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
            if (! $ok) {
                log_message('error', 'SendGrid send failed: HTTP {code} — {body}', [
                    'code' => $response->getStatusCode(), 'body' => $response->getBody(),
                ]);
            }

            return $ok;
        } catch (\Throwable $e) {
            log_message('error', 'SendGrid send exception: {msg}', ['msg' => $e->getMessage()]);

            return false;
        }
    }
}
