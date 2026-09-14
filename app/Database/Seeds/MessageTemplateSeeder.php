<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** Seeds the message "purpose" rows the platform sends against — IDs are filled in later once DLT/WhatsApp approval comes through. */
class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $db = db_connect();

        $smsPurposes = [
            'OTP Verification', 'Enrolment Confirmation', 'Receipt Notification',
            'Karyakarta Credential Issue', 'Password Reset OTP',
        ];
        foreach ($smsPurposes as $purpose) {
            if ($db->table('sms_templates')->where('purpose', $purpose)->countAllResults() === 0) {
                $db->table('sms_templates')->insert(['purpose' => $purpose, 'status' => 'pending']);
            }
        }

        $waTemplates = [
            ['name' => 'receipt_en', 'lang' => 'English'],
            ['name' => 'receipt_hi', 'lang' => 'Hindi'],
            ['name' => 'otp_alert', 'lang' => 'English'],
            ['name' => 'enrolment_welcome', 'lang' => 'English'],
            ['name' => 'remittance_reminder', 'lang' => 'Hindi'],
        ];
        foreach ($waTemplates as $t) {
            if ($db->table('whatsapp_templates')->where('name', $t['name'])->countAllResults() === 0) {
                $db->table('whatsapp_templates')->insert($t + ['status' => 'pending']);
            }
        }
    }
}
