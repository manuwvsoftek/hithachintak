<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Secrets\Vault;
use CodeIgniter\Model;

/** Single-row table (id=1): platform-wide outbound email credentials. */
class EmailSettingModel extends Model
{
    protected $table         = 'email_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'provider', 'from_address', 'from_name', 'smtp_host', 'smtp_port',
        'smtp_user', 'smtp_pass_enc', 'smtp_crypto', 'api_key_enc', 'daily_quota', 'status', 'updated_at',
    ];

    public function current(): array
    {
        return $this->find(1) ?? [];
    }

    public function saveSettings(array $plain): void
    {
        $row = [
            'provider'     => $plain['provider'] ?? 'smtp',
            'from_address' => $plain['from_address'] ?? null,
            'from_name'    => $plain['from_name'] ?? null,
            'smtp_host'    => $plain['smtp_host'] ?? null,
            'smtp_port'    => $plain['smtp_port'] ?? null,
            'smtp_user'    => $plain['smtp_user'] ?? null,
            'smtp_crypto'  => $plain['smtp_crypto'] ?? null,
            'daily_quota'  => $plain['daily_quota'] ?? null,
            'status'       => 'active',
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        if (! empty($plain['smtp_pass'])) {
            $row['smtp_pass_enc'] = Vault::encrypt($plain['smtp_pass']);
        }
        if (! empty($plain['api_key'])) {
            $row['api_key_enc'] = Vault::encrypt($plain['api_key']);
        }

        if ($this->find(1)) {
            $this->update(1, $row);
        } else {
            $row['id'] = 1;
            $this->insert($row);
        }
    }

    public function smtpPass(): ?string
    {
        return Vault::decrypt($this->current()['smtp_pass_enc'] ?? null);
    }

    public function apiKey(): ?string
    {
        return Vault::decrypt($this->current()['api_key_enc'] ?? null);
    }
}
