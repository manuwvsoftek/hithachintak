<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\EnrolmentModel;

/** JSON endpoints backing live client-side checks on the New Enrolment form. */
class MemberApiController extends BaseController
{
    /** Used on blur of the Member phone field, gated to the Hithachintak programme only. */
    public function checkHcPhone($phone)
    {
        $phone = preg_replace('/\D/', '', (string) $phone) ?? '';

        $registered = strlen($phone) === 10 && (new EnrolmentModel())->hasActiveHithachintak($phone);

        return $this->response->setJSON(['registered' => $registered]);
    }
}
