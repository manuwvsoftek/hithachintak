<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ProgrammeModel extends Model
{
    protected $table          = 'programmes';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields  = ['code', 'name', 'mode', 'rate', 'is_recurring', 'status', 'sort_order'];

    protected $validationRules = [
        'code' => 'required|alpha_dash|max_length[10]|is_unique[programmes.code,id,{id}]',
        'name' => 'required|max_length[100]',
        'mode' => 'required|in_list[min,open]',
    ];

    public function active(): array
    {
        return $this->where('status', 'active')->orderBy('sort_order', 'ASC')->findAll();
    }

    public function byCode(string $code): ?array
    {
        return $this->where('code', $code)->first();
    }
}
