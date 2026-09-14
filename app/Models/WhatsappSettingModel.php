<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Secrets\Vault;
use CodeIgniter\Model;

/** Single-row table (id=1): platform-wide MSG91 WhatsApp Business API credentials. */
class WhatsappSettingModel extends Model
{
    protected $table         = 'whatsapp_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['integrated_number', 'api_key_enc', 'waba_namespace_id', 'status', 'updated_at'];

    public function current(): array
    {
        return $this->find(1) ?? [];
    }

    public function saveSettings(array $plain): void
    {
        $row = [
            'integrated_number' => $plain['integrated_number'] ?? null,
            'waba_namespace_id' => $plain['waba_namespace_id'] ?? null,
            'status'            => 'active',
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

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

    public function apiKey(): ?string
    {
        return Vault::decrypt($this->current()['api_key_enc'] ?? null);
    }
}
