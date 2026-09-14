<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class MemberModel extends Model
{
    protected $table          = 'members';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'phone', 'email', 'name', 'first_name', 'last_name', 'age_or_dob', 'profession', 'address', 'pincode', 'preferred_lang', 'pan',
    ];

    /**
     * PAN is optional at this level (it's only mandatory for the Autopay
     * Monthly Donation programme, enforced in EnrolmentController::store()
     * before this is ever reached) but must be well-formed if present —
     * standard Indian PAN: 5 letters, 4 digits, 1 letter, e.g. AAATV1234C.
     */
    protected $validationRules = [
        'phone' => 'required|regex_match[/^[6-9][0-9]{9}$/]',
        'name'  => 'required|min_length[2]|max_length[150]',
        'pan'   => 'permit_empty|regex_match[/^[A-Z]{5}[0-9]{4}[A-Z]$/]',
    ];

    /**
     * Finds an existing member by phone, or creates a new one. Members are
     * intentionally not unique-per-phone at the DB level (a shared family
     * landline could enrol several people over time under different
     * names) — this just avoids obvious duplicate rows for the common case
     * of the same person re-enrolling.
     */
    public function findOrCreate(array $data): array
    {
        $existing = $this->where('phone', $data['phone'])->where('name', $data['name'])->first();
        if ($existing) {
            $this->update($existing['id'], $data);

            return $this->find($existing['id']);
        }

        $id = $this->insert($data, true);

        return $this->find($id);
    }
}
