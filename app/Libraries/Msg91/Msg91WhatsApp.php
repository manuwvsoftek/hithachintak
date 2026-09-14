<?php

declare(strict_types=1);

namespace App\Libraries\Msg91;

use App\Models\WhatsappSettingModel;
use App\Models\WhatsappTemplateModel;

/**
 * Thin client for MSG91's WhatsApp Business API — used for receipts, OTP
 * alerts and remittance reminders. Templates must already be approved via
 * WhatsApp/Meta (see Settings & Integrations > WhatsApp).
 *
 * @see https://docs.msg91.com/reference/send-whatsapp-message-template
 */
class Msg91WhatsApp
{
    private const API_URL = 'https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/';

    public function __construct(
        private readonly WhatsappSettingModel $settings = new WhatsappSettingModel(),
        private readonly WhatsappTemplateModel $templates = new WhatsappTemplateModel(),
    ) {
    }

    public function isConfigured(): bool
    {
        return (bool) $this->settings->apiKey();
    }

    /**
     * Sends an approved WhatsApp template message.
     *
     * @param array<string, string> $components Template placeholder values in order, e.g. ['Ramesh Iyer', 'HC/2026/000441', '₹60']
     */
    public function sendTemplate(string $templateName, string $phoneNumber, array $components, string $lang = 'en'): bool
    {
        $phone = $this->normalizePhone($phoneNumber);

        if (! $this->isConfigured()) {
            log_message('info', 'MSG91 WhatsApp not configured — message not sent. template={template} phone={phone} components={components}', [
                'template'   => $templateName,
                'phone'      => $phone,
                'components' => json_encode($components),
            ]);

            return ENVIRONMENT !== 'production';
        }

        $template = $this->templates->byName($templateName);
        if (! $template || $template['status'] !== 'approved') {
            log_message('error', 'MSG91 WhatsApp send failed: template "{tpl}" not approved.', ['tpl' => $templateName]);

            return false;
        }

        $settings = $this->settings->current();

        $payload = [
            'integrated_number' => $settings['integrated_number'] ?? null,
            'content_type'      => 'template',
            'payload'           => [
                'messaging_product' => 'whatsapp',
                'type'              => 'template',
                'template'          => [
                    'name'     => $templateName,
                    'language' => ['code' => $lang, 'policy' => 'deterministic'],
                    'to_and_components' => [[
                        'to'         => [$phone],
                        'components' => [
                            'body_1' => ['type' => 'text', 'value' => implode(', ', $components)],
                        ],
                    ]],
                ],
            ],
        ];

        try {
            $client = service('curlrequest', ['timeout' => 10]);

            $response = $client->request('POST', self::API_URL, [
                'headers' => [
                    'authkey'      => $this->settings->apiKey(),
                    'content-type' => 'application/json',
                ],
                'json'        => $payload,
                'http_errors' => false,
            ]);

            $ok = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
            if (! $ok) {
                log_message('error', 'MSG91 WhatsApp send failed: HTTP {code} — {body}', [
                    'code' => $response->getStatusCode(),
                    'body' => $response->getBody(),
                ]);
            }

            return $ok;
        } catch (\Throwable $e) {
            log_message('error', 'MSG91 WhatsApp send exception: {msg}', ['msg' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * "Namaste, Dhanyavaad for your contribution to VHP! Download your
     * receipt here: {link} VHP" — the approved receipt_<lang> template's
     * single body variable must hold the link.
     */
    public function sendReceipt(string $phone, string $link, string $lang = 'en'): bool
    {
        return $this->sendTemplate('receipt_' . $lang, $phone, [$link], $lang);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }

        return $digits;
    }
}
