<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class JilaModel extends Model
{
    protected $table           = 'jilas';
    protected $primaryKey      = 'id';
    protected $returnType      = 'array';
    protected $useTimestamps   = true;
    protected $allowedFields   = ['prant_id', 'name'];
    protected $validationRules = [
        'prant_id' => 'required|is_natural_no_zero',
        'name'     => 'required|min_length[2]|max_length[100]',
    ];

    public function forPrant(int $prantId): array
    {
        return $this->where('prant_id', $prantId)->orderBy('name', 'ASC')->findAll();
    }
}
