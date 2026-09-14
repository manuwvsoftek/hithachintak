<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class CashRemittanceModel extends Model
{
    protected $table          = 'cash_remittances';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'user_id', 'enrolment_id', 'amount', 'upi_ref', 'status', 'remitted_at',
    ];

    public function cashHeldByUser(int $userId): float
    {
        return (float) ($this->where('user_id', $userId)->where('status', 'due')->selectSum('amount')->first()['amount'] ?? 0);
    }

    public function dueForUser(int $userId): array
    {
        return $this->where('user_id', $userId)->where('status', 'due')->findAll();
    }

    /** Karyakarta-level cash-in-hand summary for the Collections screen. */
    public function summaryByUser(?int $prantId = null, ?int $jilaId = null): array
    {
        $builder = $this->db->table('cash_remittances cr')
            ->select('cr.user_id, u.name, pr.name AS unit, SUM(CASE WHEN cr.status = "due" THEN cr.amount ELSE 0 END) AS cash_due,
                      MAX(cr.remitted_at) AS last_remittance')
            ->join('users u', 'u.id = cr.user_id')
            ->join('prakhands pr', 'pr.id = u.prakhand_id', 'left')
            ->groupBy('cr.user_id');

        if ($prantId !== null) {
            $builder->where('u.prant_id', $prantId);
        }
        if ($jilaId !== null) {
            $builder->where('u.jila_id', $jilaId);
        }

        return $builder->get()->getResultArray();
    }
}
