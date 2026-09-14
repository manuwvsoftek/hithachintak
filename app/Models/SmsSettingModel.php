<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Secrets\Vault;
use CodeIgniter\Model;

/** Single-row table (id=1): platform-wide MSG91 SMS credentials. */
class SmsSettingModel extends Model
{
    protected $table         = 'sms_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['auth_key_enc', 'sender_id', 'dlt_entity_id', 'route', 'status', 'updated_at'];

    public function current(): array
    {
        return $this->find(1) ?? [];
    }

    public function saveSettings(array $plain): void
    {
        $row = [
            'sender_id'     => $plain['sender_id'] ?? 'VHPHTC',
            'dlt_entity_id' => $plain['dlt_entity_id'] ?? null,
            'route'         => $plain['route'] ?? 'transactional',
            'status'        => 'active',
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        if (! empty($plain['auth_key'])) {
            $row['auth_key_enc'] = Vault::encrypt($plain['auth_key']);
        }

        if ($this->find(1)) {
            $this->update(1, $row);
        } else {
            $row['id'] = 1;
            $this->insert($row);
        }
    }

    public function authKey(): ?string
    {
        return Vault::decrypt($this->current()['auth_key_enc'] ?? null);
    }
}
