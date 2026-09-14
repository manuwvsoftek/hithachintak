<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Secrets\Vault;
use CodeIgniter\Model;

/**
 * One row per Prant — each Prant settles to its own Cashfree merchant
 * account (VHP holds the merchant agreement at the platform level, but
 * money flows straight to the Prant's own bank account).
 */
class GatewayCredentialModel extends Model
{
    protected $table          = 'gateway_credentials';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'prant_id', 'provider', 'app_id_enc', 'secret_key_enc', 'merchant_id',
        'settlement_account_enc', 'ifsc', 'webhook_secret_enc', 'environment', 'status',
    ];

    public function forPrant(int $prantId): ?array
    {
        return $this->where('prant_id', $prantId)->first();
    }

    /**
     * Saves gateway credentials for a Prant, encrypting the secret fields.
     * Pass plaintext values in $plain; anything omitted is left untouched.
     */
    public function saveForPrant(int $prantId, array $plain): int|string|null
    {
        $existing = $this->forPrant($prantId);

        $row = [
            'prant_id'    => $prantId,
            'provider'    => $plain['provider'] ?? 'cashfree',
            'merchant_id' => $plain['merchant_id'] ?? ($existing['merchant_id'] ?? null),
            'environment' => $plain['environment'] ?? ($existing['environment'] ?? 'sandbox'),
            'status'      => 'configured',
        ];

        if (! empty($plain['app_id'])) {
            $row['app_id_enc'] = Vault::encrypt($plain['app_id']);
        }
        if (! empty($plain['secret_key'])) {
            $row['secret_key_enc'] = Vault::encrypt($plain['secret_key']);
        }

        if ($existing) {
            $this->update($existing['id'], $row);

            return $existing['id'];
        }

        return $this->insert($row, true);
    }

    public function decrypted(array $row): array
    {
        return [
            'app_id'      => Vault::decrypt($row['app_id_enc'] ?? null),
            'secret_key'  => Vault::decrypt($row['secret_key_enc'] ?? null),
            'merchant_id' => $row['merchant_id'] ?? null,
            'environment' => $row['environment'] ?? 'sandbox',
        ];
    }
}
