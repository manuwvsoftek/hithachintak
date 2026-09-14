<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Enrolment;
use App\Libraries\Enrolment\I18n;
use App\Libraries\Enrolment\ReceiptPdfGenerator;
use App\Libraries\Enrolment\TrustInfo;
use App\Models\EnrolmentFamilyMemberModel;
use App\Models\EnrolmentModel;
use App\Models\MemberModel;
use App\Models\PrantModel;
use App\Models\ProgrammeModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Unauthenticated receipt view reached via the short link sent over
 * SMS/WhatsApp (h/rid={public_token}). The token is a random 40-bit value,
 * not the sequential id or the human-readable receipt_no, so this page
 * can be safely public without letting one member enumerate another's
 * receipt. A light per-IP lockout on repeated misses is defence in depth
 * on top of that, mirroring the login lockout in AuthController.
 */
class PublicReceiptController extends BaseController
{
    private const MAX_MISSES      = 20;
    private const LOCKOUT_SECONDS = 15 * 60;
    private const MISS_WINDOW     = 10 * 60;

    public function show(string $token)
    {
        $enrolment = $this->lookup($token);

        $member    = (new MemberModel())->find($enrolment->member_id);
        $programme = (new ProgrammeModel())->find($enrolment->programme_id);
        $prant     = (new PrantModel())->find($enrolment->prant_id);
        $collector = (new UserModel())->find($enrolment->collected_by_user_id);

        return view('receipts/public', [
            'enrolment'     => $enrolment,
            'member'        => $member,
            'programme'     => $programme,
            'prant'         => $prant,
            't'             => I18n::t($enrolment->language),
            'trustName'     => I18n::trustName($enrolment->language),
            'trustPan'      => TrustInfo::PAN,
            'collectedBy'   => $collector->name ?? '—',
            'householdRows' => (new EnrolmentFamilyMemberModel())->householdRows($enrolment->id, $member),
            'title'         => 'Receipt',
        ]);
    }

    public function pdf(string $token)
    {
        $enrolment = $this->lookup($token);

        $member    = (new MemberModel())->find($enrolment->member_id);
        $programme = (new ProgrammeModel())->find($enrolment->programme_id);
        $prant     = (new PrantModel())->find($enrolment->prant_id);

        $generator = new ReceiptPdfGenerator();
        $pdf       = $generator->generate($enrolment, $member, $programme, $prant);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $generator->filename($enrolment) . '"')
            ->setBody($pdf);
    }

    private function lookup(string $token): Enrolment
    {
        $ip       = $this->request->getIPAddress();
        $lockKey  = 'public_receipt_lock_' . $ip;
        $missKey  = 'public_receipt_miss_' . $ip;
        $cache    = service('cache');

        if ($cache->get($lockKey)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $enrolment = (new EnrolmentModel())->where('public_token', $token)->first();

        if (! $enrolment || ! $enrolment->hasReceipt()) {
            $misses = (int) $cache->get($missKey) + 1;
            $cache->save($missKey, $misses, self::MISS_WINDOW);
            if ($misses >= self::MAX_MISSES) {
                $cache->save($lockKey, true, self::LOCKOUT_SECONDS);
            }

            throw PageNotFoundException::forPageNotFound();
        }

        return $enrolment;
    }
}
