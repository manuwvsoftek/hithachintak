<?php

declare(strict_types=1);

namespace App\Libraries\Msg91;

use App\Models\SmsSettingModel;
use App\Models\SmsTemplateModel;
use Config\App;

/**
 * Thin client for MSG91's Flow (template) API — used for OTP, enrolment
 * confirmations, receipt notifications, Karyakarta credential issue and
 * password-reset OTP. Every message purpose maps to a DLT-registered
 * template configured under Settings & Integrations > SMS (MSG91).
 *
 * @see https://docs.msg91.com/reference/send-sms-flow
 */
class Msg91Sms
{
    private const API_URL = 'https://control.msg91.com/api/v5/flow/';

    public function __construct(
        private readonly SmsSettingModel $settings = new SmsSettingModel(),
        private readonly SmsTemplateModel $templates = new SmsTemplateModel(),
    ) {
    }

    public function isConfigured(): bool
    {
        return (bool) $this->settings->authKey();
    }

    /**
     * Sends a templated transactional SMS. Returns true on a 2xx response
     * from MSG91. In development, when no auth key is configured yet, this
     * logs the message instead of sending it (so OTP flows are testable
     * without a live MSG91 account) and returns true.
     *
     * @param array<string, string> $vars Template variables (e.g. ['OTP' => '482913'])
     */
    public function sendTemplate(string $purpose, string $phoneNumber, array $vars): bool
    {
        $phone = $this->normalizePhone($phoneNumber);

        if (! $this->isConfigured()) {
            log_message('info', 'MSG91 not configured — SMS not sent. purpose={purpose} phone={phone} vars={vars}', [
                'purpose' => $purpose,
                'phone'   => $phone,
                'vars'    => json_encode($vars),
            ]);

            return ENVIRONMENT !== 'production';
        }

        $template = $this->templates->byPurpose($purpose);
        if (! $template || empty($template['msg91_template_id'])) {
            log_message('error', 'MSG91 send failed: no template configured for purpose "{purpose}".', ['purpose' => $purpose]);

            return false;
        }

        $settings = $this->settings->current();

        $recipient = ['mobiles' => $phone];
        foreach ($vars as $key => $value) {
            $recipient[$key] = (string) $value;
        }

        $payload = [
            'template_id' => $template['msg91_template_id'],
            'sender'      => $settings['sender_id'] ?? 'VHPHTC',
            'recipients'  => [$recipient],
        ];

        if (! empty($settings['dlt_entity_id'])) {
            $payload['DLT_TE_ID'] = $settings['dlt_entity_id'];
        }

        try {
            $client = service('curlrequest', ['timeout' => 10]);

            $response = $client->request('POST', self::API_URL, [
                'headers' => [
                    'authkey'      => $this->settings->authKey(),
                    'content-type' => 'application/json',
                ],
                'json'            => $payload,
                'http_errors'     => false,
            ]);

            $ok = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
            if (! $ok) {
                log_message('error', 'MSG91 SMS send failed: HTTP {code} — {body}', [
                    'code' => $response->getStatusCode(),
                    'body' => $response->getBody(),
                ]);
            }

            return $ok;
        } catch (\Throwable $e) {
            log_message('error', 'MSG91 SMS send exception: {msg}', ['msg' => $e->getMessage()]);

            return false;
        }
    }

    public function sendOtp(string $phone, string $code): bool
    {
        return $this->sendTemplate('OTP Verification', $phone, ['OTP' => $code]);
    }

    public function sendPasswordResetOtp(string $phone, string $code): bool
    {
        return $this->sendTemplate('Password Reset OTP', $phone, ['OTP' => $code]);
    }

    public function sendEnrolmentConfirmation(string $phone, string $memberName, string $programme): bool
    {
        return $this->sendTemplate('Enrolment Confirmation', $phone, ['NAME' => $memberName, 'PROGRAMME' => $programme]);
    }

    /**
     * "Namaste, Dhanyavaad for your contribution to VHP! Download your
     * receipt here: {LINK} VHP" — the DLT-registered "Receipt Notification"
     * template must contain a matching {{LINK}} placeholder.
     */
    public function sendReceiptNotification(string $phone, string $link): bool
    {
        return $this->sendTemplate('Receipt Notification', $phone, ['LINK' => $link]);
    }

    public function sendKaryakartaCredentials(string $phone, string $name, string $tempPassword): bool
    {
        return $this->sendTemplate('Karyakarta Credential Issue', $phone, ['NAME' => $name, 'PASSWORD' => $tempPassword]);
    }

    /** MSG91 expects E.164-ish digits without a leading +, e.g. 91XXXXXXXXXX. */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }

        return $digits;
    }
}
