<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Enrolment\I18n;
use CodeIgniter\Model;

class PrantModel extends Model
{
    protected $table            = 'prants';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = ['name', 'code', 'notes', 'languages', 'trust_name', 'trust_address', 'trust_pincode', 'trust_pan', 'trust_phone', 'trust_email'];
    protected $validationRules  = [
        'name'          => 'required|min_length[2]|max_length[100]|is_unique[prants.name,id,{id}]',
        'trust_pincode' => 'permit_empty|regex_match[/^[0-9]{6}$/]',
        'trust_pan'     => 'permit_empty|regex_match[/^[A-Z]{5}[0-9]{4}[A-Z]$/]',
        'trust_phone'   => 'permit_empty|regex_match[/^[0-9]{10}$/]',
        'trust_email'   => 'permit_empty|valid_email',
    ];
    protected $validationMessages = [
        'trust_pincode' => ['regex_match' => 'Enter a valid 6-digit PIN code.'],
        'trust_pan'     => ['regex_match' => 'Enter a valid PAN, e.g. AAATV1234C.'],
        'trust_phone'   => ['regex_match' => 'Enter a valid 10-digit phone number.'],
        'trust_email'   => ['valid_email' => 'Enter a valid email address.'],
    ];

    /**
     * This Prant's enrolment-form languages, in the order they should be
     * pinned to the top of the language dropdown. An unconfigured Prant
     * (languages null/empty) returns every language I18n knows about, so
     * the form never ends up with fewer options than before this existed.
     */
    public function languageCodes(?array $prantRow): array
    {
        $raw = trim((string) ($prantRow['languages'] ?? ''));
        if ($raw === '') {
            return I18n::LANGUAGES;
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
