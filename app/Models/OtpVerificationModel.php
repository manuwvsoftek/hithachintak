<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class OtpVerificationModel extends Model
{
    protected $table          = 'otp_verifications';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = false; // created_at set manually below

    protected $allowedFields = [
        'phone', 'purpose', 'code_hash', 'expires_at', 'consumed_at', 'attempts', 'ip_address', 'created_at',
    ];

    public const MAX_ATTEMPTS       = 5;
    public const TTL_SECONDS        = 300;
    public const RESEND_COOLDOWN    = 30;
    public const MAX_PER_HOUR       = 5;

    /**
     * Issues a fresh OTP, invalidating any prior unconsumed code for the
     * same phone+purpose. Returns the plaintext code (caller sends it via
     * SMS/WhatsApp — it is never persisted in plaintext).
     *
     * @throws \RuntimeException if the phone has hit the hourly send cap
     *                           (basic anti-abuse guard for the SMS budget).
     */
    public function issue(string $phone, string $purpose, ?string $ip = null): string
    {
        $recentCount = $this->where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('created_at >=', date('Y-m-d H:i:s', time() - 3600))
            ->countAllResults();

        if ($recentCount >= self::MAX_PER_HOUR) {
            throw new \RuntimeException('Too many OTP requests for this number. Please try again later.');
        }

        $last = $this->where('phone', $phone)->where('purpose', $purpose)
            ->orderBy('created_at', 'DESC')->first();
        if ($last && strtotime($last['created_at']) > time() - self::RESEND_COOLDOWN) {
            throw new \RuntimeException('Please wait before requesting another OTP.');
        }

        // Invalidate any previous unconsumed codes for this phone+purpose.
        $this->where('phone', $phone)->where('purpose', $purpose)
            ->where('consumed_at', null)
            ->set(['consumed_at' => date('Y-m-d H:i:s')])
            ->update();

        $code = (string) random_int(100000, 999999);

        $this->insert([
            'phone'      => $phone,
            'purpose'    => $purpose,
            'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_SECONDS),
            'attempts'   => 0,
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $code;
    }

    /**
     * Verifies a submitted code. Returns true and marks the OTP consumed on
     * success; increments the attempt counter and returns false otherwise.
     */
    public function verify(string $phone, string $purpose, string $submittedCode): bool
    {
        $row = $this->where('phone', $phone)->where('purpose', $purpose)
            ->where('consumed_at', null)
            ->orderBy('created_at', 'DESC')->first();

        if (! $row) {
            return false;
        }

        if (strtotime($row['expires_at']) < time()) {
            return false;
        }

        if ($row['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! password_verify($submittedCode, $row['code_hash'])) {
            $this->update($row['id'], ['attempts' => $row['attempts'] + 1]);

            return false;
        }

        $this->update($row['id'], ['consumed_at' => date('Y-m-d H:i:s')]);

        return true;
    }
}
