<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PrakhandModel extends Model
{
    protected $table           = 'prakhands';
    protected $primaryKey      = 'id';
    protected $returnType      = 'array';
    protected $useTimestamps   = true;
    protected $allowedFields   = ['jila_id', 'name'];
    protected $validationRules = [
        'jila_id' => 'required|is_natural_no_zero',
        'name'    => 'required|min_length[2]|max_length[150]',
    ];

    public function forJila(int $jilaId): array
    {
        return $this->where('jila_id', $jilaId)->orderBy('name', 'ASC')->findAll();
    }
}
