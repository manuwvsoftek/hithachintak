<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\User;
use App\Models\EnrolmentModel;
use App\Models\UserModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $user = $this->currentUser();

        $monthStart = date('Y-m-01 00:00:00');

        $totalEnrolments = (new EnrolmentModel())->scopedTo($user)->countAllResults();
        $thisWeek        = (new EnrolmentModel())->scopedTo($user)
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-7 days')))->countAllResults();

        // "Collected" = money already taken from the member, whether it has
        // reached VHP's account yet or is still sitting with a Karyakarta
        // as cash awaiting remittance (tracked separately, below).
        $collected = (float) ((new EnrolmentModel())->scopedTo($user)
            ->whereIn('status', ['paid', 'remitted', 'cash_collected', 'cash_pending_remit'])
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

        return view('dashboard/index', [
            'title'             => 'Dashboard',
            'totalEnrolments'   => $totalEnrolments,
            'thisWeek'          => $thisWeek,
            'collected'         => $collected,
            'activeKaryakartas' => $activeKaryakartas,
            'byProgramme'       => $byProgramme,
            'byMode'            => $byMode,
            'recent'            => $recent,
        ]);
    }
}
