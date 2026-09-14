<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ReportTemplateModel extends Model
{
    protected $table          = 'report_templates';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields  = ['user_id', 'name', 'filters_json', 'fields_json'];

    protected $validationRules = [
        'user_id' => 'required|is_natural_no_zero',
        'name'    => 'required|max_length[100]',
    ];

    public function forUser(int $userId): array
    {
        return $this->where('user_id', $userId)->orderBy('name', 'ASC')->findAll();
    }
}
