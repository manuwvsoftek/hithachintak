<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Enrolment;
use App\Entities\User;
use App\Models\EnrolmentModel;
use App\Models\UserModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $user = $this->currentUser();

        $monthStart = date('Y-m-01 00:00:00');

        // CAMPAIGN LITE MODE — every stat below is its own query, and this
        // is almost certainly the single most-hit page in the app. Rather
        // than running all of it on every load (or permanently disabling
        // it), it's now gated behind an explicit "Show counts" click
        // (?showcounts=1) — hidden, and free, until someone actually wants
        // the numbers. None of this touches the enrolment submit path.
        $totalEnrolments   = 0;
        $thisWeek          = 0;
        $awaitingPayment   = 0;
        $collected         = 0.0;
        $activeKaryakartas = 0;
        $byProgramme       = [];
        $byMode            = [];
        $recent            = [];
        $showCounts        = (bool) $this->request->getGet('showcounts');

        if ($showCounts) {
            $headcount = (new EnrolmentModel())->scopedTo($user)->builder()
                ->join('enrolment_family_members efm', 'efm.enrolment_id = enrolments.id', 'left')
                ->whereIn('enrolments.status', Enrolment::RECEIPTED_STATUSES)
                ->select('COUNT(DISTINCT enrolments.id) AS enrolment_count, COUNT(efm.id) AS family_count', false)
                ->get()->getRowArray();
            $totalEnrolments = (int) ($headcount['enrolment_count'] ?? 0) + (int) ($headcount['family_count'] ?? 0);

            $thisWeek = (new EnrolmentModel())->scopedTo($user)
                ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-7 days')))->countAllResults();

            $awaitingPayment = (new EnrolmentModel())->scopedTo($user)
                ->whereIn('status', Enrolment::AWAITING_STATUSES)->countAllResults();

            $collected = (float) ((new EnrolmentModel())->scopedTo($user)
                ->whereIn('status', Enrolment::RECEIPTED_STATUSES)
                ->selectSum('amount')->first()->amount ?? 0);

            $activeKaryakartas = (new UserModel())->visibleTo($user)
                ->where('role', User::ROLE_KARYAKARTA)->where('status', 'active')->countAllResults();

            $byProgramme = (new EnrolmentModel())->scopedTo($user)->builder()
                ->select('programmes.name, COUNT(*) AS cnt')
                ->join('programmes', 'programmes.id = enrolments.programme_id')
                ->where('enrolments.created_at >=', $monthStart)
                ->groupBy('programmes.name')
                ->get()->getResultArray();

            $byMode = (new EnrolmentModel())->scopedTo($user)->builder()
                ->select("COALESCE(payment_mode, 'pending') AS mode, SUM(amount) AS total")
                ->whereIn('status', ['paid', 'remitted', 'cash_pending_remit', 'cash_collected'])
                ->where('created_at >=', $monthStart)
                ->groupBy('mode')
                ->get()->getResultArray();

            $recent = (new EnrolmentModel())->recentForDashboard($user, 5);
        }

        return view('dashboard/index', [
            'title'             => 'Dashboard',
            'showCounts'        => $showCounts,
            'totalEnrolments'   => $totalEnrolments,
            'thisWeek'          => $thisWeek,
            'awaitingPayment'   => $awaitingPayment,
            'collected'         => $collected,
            'activeKaryakartas' => $activeKaryakartas,
            'byProgramme'       => $byProgramme,
            'byMode'            => $byMode,
            'recent'            => $recent,
        ]);
    }
}
