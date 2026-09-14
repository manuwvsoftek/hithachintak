<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class SmsTemplateModel extends Model
{
    protected $table         = 'sms_templates';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['purpose', 'dlt_template_id', 'msg91_template_id', 'status'];

    public function byPurpose(string $purpose): ?array
    {
        return $this->where('purpose', $purpose)->first();
    }
}
