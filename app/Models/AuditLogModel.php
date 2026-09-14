<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table          = 'audit_logs';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = false;

    protected $allowedFields = ['user_id', 'action', 'entity', 'entity_id', 'meta_json', 'ip_address', 'created_at'];

    public function record(?int $userId, string $action, ?string $entity = null, ?int $entityId = null, array $meta = []): void
    {
        $this->insert([
            'user_id'    => $userId,
            'action'     => $action,
            'entity'     => $entity,
            'entity_id'  => $entityId,
            'meta_json'  => $meta === [] ? null : json_encode($meta),
            'ip_address' => service('request')->getIPAddress(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
