<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CashRemittanceModel;
use App\Models\EnrolmentModel;
use App\Models\PrakhandModel;

/**
 * Mobile-first home surface for field users (Karyakarta, and anyone else
 * who wants the compact view). The actual enrolment wizard and full
 * enrolments/collections lists live under /admin/* — RBAC's "Own" level
 * already scopes those to the logged-in user, and the admin shell is
 * responsive, so this surface is a lightweight, phone-friendly front
 * door rather than a second copy of that logic.
 */
class KaryakartaController extends BaseController
{
    public function home()
    {
        $user = $this->currentUser();
        $enrM = new EnrolmentModel();

        $todayCount = $enrM->scopedTo($user)->where('created_at >=', date('Y-m-d 00:00:00'))->countAllResults();
        $cashDue    = (new CashRemittanceModel())->cashHeldByUser($user->id);
        $recent     = (new EnrolmentModel())->recentForDashboard($user, 6);
        $t          = \App\Libraries\Enrolment\I18n::t((string) (session('ui_language') ?? 'en'));

        return view('karyakarta/home', [
            'title'      => $t['homeTab'],
            't'          => $t,
            'todayCount' => $todayCount,
            'cashDue'    => $cashDue,
            'recent'     => $recent,
        ]);
    }

    public function collections()
    {
        $user  = $this->currentUser();
        $model = new CashRemittanceModel();
        $t     = \App\Libraries\Enrolment\I18n::t((string) (session('ui_language') ?? 'en'));

        return view('karyakarta/collections', [
            'title'   => $t['collectionsTab'],
            't'       => $t,
            'due'     => $model->dueForUser($user->id),
            'history' => $model->where('user_id', $user->id)->where('status', 'remitted')
                ->orderBy('remitted_at', 'DESC')->limit(20)->findAll(),
        ]);
    }

    public function profile()
    {
        $user      = $this->currentUser();
        $prakhand  = $user->prakhand_id ? (new PrakhandModel())->find($user->prakhand_id) : null;
        $t         = \App\Libraries\Enrolment\I18n::t((string) (session('ui_language') ?? 'en'));

        return view('karyakarta/profile', [
            'title'    => $t['profileTab'],
            't'        => $t,
            'user'     => $user,
            'prakhand' => $prakhand['name'] ?? null,
        ]);
    }
}
