<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EnrolmentFamilyMemberModel extends Model
{
    protected $table          = 'enrolment_family_members';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['enrolment_id', 'name', 'first_name', 'last_name', 'age_or_dob', 'profession', 'contact_number'];

    protected $validationRules = [
        'enrolment_id' => 'required|is_natural_no_zero',
        'name'         => 'required|max_length[150]',
        'first_name'   => 'required|max_length[100]',
        'age_or_dob'   => 'required|max_length[30]',
    ];

    public function forEnrolment(int $enrolmentId): array
    {
        return $this->where('enrolment_id', $enrolmentId)->findAll();
    }

    /**
     * Full household roster for the receipt's member table — the head of
     * family first (from the `members` row), then each additional family
     * member in the order they were added.
     *
     * @return list<array{name: string, age_or_dob: string, profession: string, isHead: bool}>
     */
    public function householdRows(int $enrolmentId, array $headMember): array
    {
        $toRow = static fn (array $person, bool $isHead) => [
            'name'       => $person['name'] ?? '',
            'age_or_dob' => $person['age_or_dob'] ?? '',
            'profession' => $person['profession'] ?? '',
            'isHead'     => $isHead,
        ];

        $rows = [$toRow($headMember, true)];
        foreach ($this->forEnrolment($enrolmentId) as $fm) {
            $rows[] = $toRow($fm, false);
        }

        return $rows;
    }

    /**
     * All family members for a set of enrolments in one query, grouped by
     * enrolment_id — for a list/table view, avoids an N+1 query per row.
     *
     * @param list<int> $enrolmentIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function forEnrolments(array $enrolmentIds): array
    {
        if ($enrolmentIds === []) {
            return [];
        }

        $grouped = [];
        foreach ($this->whereIn('enrolment_id', $enrolmentIds)->orderBy('id', 'ASC')->findAll() as $row) {
            $grouped[$row['enrolment_id']][] = $row;
        }

        return $grouped;
    }
}
