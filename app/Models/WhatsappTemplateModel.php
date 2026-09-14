<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class WhatsappTemplateModel extends Model
{
    protected $table         = 'whatsapp_templates';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'lang', 'status'];

    public function byName(string $name): ?array
    {
        return $this->where('name', $name)->first();
    }
}
