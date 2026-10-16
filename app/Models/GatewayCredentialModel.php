<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Secrets\Vault;
use CodeIgniter\Model;

/**
 * One row per Prant per provider — each Prant can settle to its own
 * Cashfree merchant account and/or its own Razorpay account (VHP holds
 * the merchant agreement at the platform level, but money flows straight
 * to the Prant's own bank account). A Prant with both configured and
 * enabled lets the person paying choose which one to use at checkout
 * (see EnrolmentService::availableGatewaysForPrant()).
 */
class GatewayCredentialModel extends Model
{
    public const PROVIDERS = ['cashfree', 'razorpay'];

    protected $table          = 'gateway_credentials';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'prant_id', 'provider', 'app_id_enc', 'secret_key_enc', 'merchant_id',
        'settlement_account_enc', 'ifsc', 'webhook_secret_enc', 'environment', 'status', 'enabled',
    ];

     public function forPrant(int $prantId, string $provider = 'cashfree'): ?array
    {
        return $this->where('prant_id', $prantId)->where('provider', $provider)->first();
    }

     /** Every provider row configured for a Prant, keyed by provider — missing providers are simply absent (not synthesized). */
    public function allForPrant(int $prantId): array
    {
        $rows = $this->where('prant_id', $prantId)->findAll();

        return array_column($rows, null, 'provider');
    }

    /**
     * Provider codes that are both switched on AND have working
     * credentials saved for this Prant — the only ones checkout may
     * actually offer. A row that's 'enabled' but never got past
     * 'pending' (credentials not yet saved) is excluded; see
     * setEnabled(), which refuses to turn a 'pending' row on in the
     * first place, but this stays defensive either way.
     *
     * @return list<string>
     */
    public function enabledGatewaysForPrant(int $prantId): array
    {
        $rows = $this->where('prant_id', $prantId)
            ->where('status', 'configured')
            ->where('enabled', 1)
            ->findAll();

        return array_values(array_column($rows, 'provider'));
    }


    /**
     * Saves gateway credentials for a Prant, encrypting the secret fields.
     * Pass plaintext values in $plain; anything omitted is left untouched.
     */
     public function saveForPrant(int $prantId, string $provider, array $plain): int|string|null
    {
        $existing = $this->forPrant($prantId, $provider);

         $row = [
            'prant_id'    => $prantId,
            'provider'    => $provider,
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

    /**
     * Turns a Prant's gateway on/off at checkout. Turning one ON is
     * refused unless it's already 'configured' — an admin can't enable a
     * gateway that has no working credentials behind it yet, which would
     * otherwise fail the moment someone tried to pay with it. Turning one
     * OFF always succeeds (and is also how you'd stop offering a gateway
     * without touching its saved credentials).
     */
    public function setEnabled(int $prantId, string $provider, bool $enabled): bool
    {
        $row = $this->forPrant($prantId, $provider);
        if (! $row || ($enabled && $row['status'] !== 'configured')) {
            return false;
        }

        $this->update($row['id'], ['enabled' => $enabled ? 1 : 0]);

        return true;
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
