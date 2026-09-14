<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Enrolment;
use App\Entities\User;
use App\Libraries\Enrolment\EnrolmentService;
use App\Models\AuditLogModel;
use App\Models\CashRemittanceModel;
use App\Models\EnrolmentDraftModel;
use App\Models\EnrolmentFamilyMemberModel;
use App\Models\EnrolmentModel;
use App\Models\JilaModel;
use App\Models\PrakhandModel;
use App\Models\PrantModel;
use App\Models\ProgrammeModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class EnrolmentController extends BaseController
{
    /**
     * Preset options for the Member/family "Profession" field — picking
     * "Others" reveals a free-text field on the form instead of adding a
     * 16th named option here.
     */
    public const PROFESSIONS = [
        'Agriculture', 'Armed Forces', 'Business', 'CA/CS', 'Doctor', 'Educator',
        'Engineer', 'Govt/PSU/Public Service', 'Home Maker', 'Private Service',
        'Retired', 'Social Service/NGO', 'Self Employed', 'Student', 'Others',
    ];

    private function service(): EnrolmentService
    {
        return new EnrolmentService();
    }

    private function authorize(int $id): Enrolment
    {
        $enrolment = (new EnrolmentModel())->scopedTo($this->currentUser())->find($id);
        if (! $enrolment) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $enrolment;
    }

    /**
     * A filter GET param applied identically wherever the same filtered set
     * of enrolments is needed — the list itself, the quick-stat totals
     * above it, and the CSV export — so all three always agree with each
     * other instead of the export silently ignoring an active filter.
     */
    private function applyFilters($model)
    {
        $get = $this->request->getGet();

        if (! empty($get['programme'])) {
            $model->where('programmes.code', $get['programme']);
        }
        if (! empty($get['status'])) {
            $model->where('enrolments.status', $get['status']);
        }
        if (! empty($get['mode'])) {
            $model->where('enrolments.payment_mode', $get['mode']);
        }
        if (! empty($get['from'])) {
            $model->where('enrolments.created_at >=', $get['from'] . ' 00:00:00');
        }
        if (! empty($get['to'])) {
            $model->where('enrolments.created_at <=', $get['to'] . ' 23:59:59');
        }
        if (! empty($get['q'])) {
            $search = trim($get['q']);
            $model->groupStart()
                ->like('members.name', $search)
                ->orLike('members.phone', $search)
                ->orLike('enrolments.receipt_no', $search)
                ->orLike('users.name', $search)
                ->groupEnd();
        }

        return $model;
    }

    public function index()
    {
        $actor = $this->currentUser();
        $draftCount = (new EnrolmentDraftModel())->scopedTo($actor)->countAllResults();

        if ($this->request->getGet('view') === 'drafts') {
            return view('enrolments/index', [
                'title'      => 'Enrolments',
                'view'       => 'drafts',
                'drafts'     => (new EnrolmentDraftModel())->scopedTo($actor)->orderBy('updated_at', 'DESC')->findAll(),
                'draftCount' => $draftCount,
                'programmes' => (new ProgrammeModel())->active(),
            ]);
        }

        $model = $this->applyFilters((new EnrolmentModel())->scopedTo($actor)->withDetails());
        $enrolments = $model->orderBy('enrolments.created_at', 'DESC')->paginate(25);

        // Quick stats mirror the active filters — the pager's own count is
        // reused for "Total" rather than a second COUNT query, but the sum
        // totals need their own query since paginate() has already
        // executed the builder above.
        $sums = $this->applyFilters((new EnrolmentModel())->scopedTo($actor)->withDetails())
            ->select('
                SUM(enrolments.amount) AS total_amount,
                SUM(CASE WHEN enrolments.payment_mode IN ("upi","qr") THEN enrolments.amount ELSE 0 END) AS upi_amount,
                SUM(CASE WHEN enrolments.payment_mode = "cash" THEN enrolments.amount ELSE 0 END) AS cash_amount
            ', false)
            ->first();

        // Cash still awaiting remittance to VHP's account — Hithachintak
        // no longer has this state at all (its cash is remitted instantly
        // via the gateway to complete the enrolment), so what's left here
        // is only ever other programmes' cash, tracked the same way it
        // always was.
        $visibleUserIds = array_column((new UserModel())->visibleTo($actor)->select('id')->findAll(), 'id');
        $dueRows = $visibleUserIds === [] ? [] : db_connect()->table('cash_remittances cr')
            ->select('cr.*, u.name AS user_name')
            ->join('users u', 'u.id = cr.user_id')
            ->whereIn('cr.user_id', $visibleUserIds)
            ->where('cr.status', 'due')
            ->orderBy('cr.created_at', 'ASC')
            ->get()->getResultArray();

        // One grouped query for every row on this page, rather than one
        // query per row — the family list is shown nested under each
        // enrolment's own head-of-family name in the table.
        $familyByEnrolment = (new EnrolmentFamilyMemberModel())
            ->forEnrolments(array_map(static fn ($e) => $e->id, $enrolments));

        return view('enrolments/index', [
            'title'             => 'Enrolments',
            'view'              => 'list',
            'draftCount'        => $draftCount,
            'enrolments'        => $enrolments,
            'familyByEnrolment' => $familyByEnrolment,
            'pager'             => $model->pager,
            'programmes'        => (new ProgrammeModel())->active(),
            'totalCount'        => $model->pager->getTotal(),
            'totalAmount'       => (float) ($sums?->total_amount ?? 0),
            'upiAmount'         => (float) ($sums?->upi_amount ?? 0),
            'cashAmount'        => (float) ($sums?->cash_amount ?? 0),
            'dueRows'           => $dueRows,
        ]);
    }

    /** Marks a non-Hithachintak cash remittance received — Hithachintak's own cash never reaches this state (see EnrolmentService::confirmCashRemittance()). */
    public function remitCash()
    {
        $id     = (int) $this->request->getPost('remittance_id');
        $upiRef = (string) $this->request->getPost('upi_ref');

        $model = new CashRemittanceModel();
        $row   = $model->find($id);
        if (! $row || $row['status'] !== 'due') {
            return redirect()->to('/admin/enrolments')->with('error', 'Already reconciled or not found.');
        }

        if ($row['enrolment_id']) {
            $enrolment = (new EnrolmentModel())->find($row['enrolment_id']);
            if ($enrolment && ! $enrolment->hasReceipt()) {
                // A due row still without a receipt is a Hithachintak cash
                // enrolment awaiting its instant gateway remittance — that
                // step, and the receipt it produces, can only be completed
                // through the enrolment's own Cashfree payment, never a
                // manual note here (that would bypass the gateway entirely).
                return redirect()->to('/admin/enrolments')
                    ->with('error', 'This Hithachintak cash is still awaiting the Karyakarta\'s UPI payment on the gateway — open the enrolment to complete it.');
            }
        }

        $model->update($id, [
            'status'      => 'remitted',
            'upi_ref'     => $upiRef ?: null,
            'remitted_at' => date('Y-m-d H:i:s'),
        ]);

        if ($row['enrolment_id']) {
            (new EnrolmentModel())->update($row['enrolment_id'], ['status' => 'remitted']);
        }

        (new AuditLogModel())->record($this->currentUser()->id, 'cash_remitted', 'cash_remittance', $id, ['amount' => $row['amount']]);

        return redirect()->to('/admin/enrolments')->with('success', 'Marked as remitted.');
    }

    public function newForm()
    {
        $actor = $this->currentUser();

        // The topbar language selector picks what the member sees, not the
        // admin chrome — the form's own section labels and its receipt
        // come out in this language; the rest of the admin UI stays English.
        $lang = (string) (session('ui_language') ?? 'en');

        // A Karyakarta only ever enrols within their own fixed Prakhand —
        // asking them to re-pick Prant/Jila/Prakhand (three taps plus two
        // network round-trips for the cascading dropdowns) on every single
        // Hithachintak enrolment is pure friction in the field. Lock the
        // location to their profile instead; Admin-level users, who can
        // enrol on behalf of any location, keep the free-choice pickers.
        $lockedLocation = null;
        if ($actor->role === User::ROLE_KARYAKARTA && $actor->prant_id) {
            $prant    = (new PrantModel())->find($actor->prant_id);
            $jila     = $actor->jila_id ? (new JilaModel())->find($actor->jila_id) : null;
            $prakhand = $actor->prakhand_id ? (new PrakhandModel())->find($actor->prakhand_id) : null;
            if ($prant) {
                $lockedLocation = ['prant' => $prant, 'jila' => $jila, 'prakhand' => $prakhand];
            }
        }

        // ?resume=<draftId> comes from the Enrolments list' "Pending
        // Drafts" tab — an autosaved-but-never-submitted form the same or
        // a different device left off on. A bounce-back from a failed
        // server-side validation carries the same draft_id via old() (the
        // form's own hidden field), so it resumes the same way — the
        // browser still has that draft in localStorage, since autosave
        // wrote it there before the failed submit ever reached the
        // server. Looked up scoped to the actor the same way authorize()
        // scopes a real enrolment, so a Karyakarta can only ever resume
        // their own drafts. The payload is handed to the view as JSON and
        // applied client-side (it has to rebuild dynamic family-member
        // rows, cascade the location selects, etc.) — this isn't
        // prefilling individual field values server-side.
        $resumeId = (string) ($this->request->getGet('resume') ?? '');
        if ($resumeId === '') {
            $resumeId = (string) (old('draft_id') ?? '');
        }
        $resumeDraft = null;
        if ($resumeId !== '') {
            $draft = (new EnrolmentDraftModel())->scopedTo($actor)->find($resumeId);
            if ($draft) {
                $resumeDraft = json_decode($draft['payload'], true);
            }
        }

        // Every Admin/Karyakarta account must complete their own
        // Hithachintak self-enrolment before enrolling anyone else — the
        // form is forced into Hithachintak-only, with First name and
        // Mobile number taken from the account itself (and locked, so
        // they can't quietly enrol someone else under this gate instead).
        // Dev Admin is exempt — it's a technical/system account, not part
        // of the operational admin hierarchy this gate is aimed at.
        $selfDone   = $actor->isDevAdmin() || (new EnrolmentModel())->hasCompletedHithachintak($actor->phone);
        $forceSelf  = ! $selfDone;
        $programmes = (new ProgrammeModel())->active();
        $selfFirstName = '';
        $selfLastName  = '';
        if ($forceSelf) {
            $programmes = array_values(array_filter($programmes, static fn ($p) => $p['code'] === 'hc'));
            $nameParts     = explode(' ', trim($actor->name), 2);
            $selfFirstName = $nameParts[0] ?? '';
            $selfLastName  = $nameParts[1] ?? '';
        }

        return view('enrolments/new', [
            'title'             => 'New Enrolment',
            'programmes'        => $programmes,
            'prants'            => (new PrantModel())->orderBy('name', 'ASC')->findAll(),
            'professions'       => self::PROFESSIONS,
            'lang'              => $lang,
            't'                 => \App\Libraries\Enrolment\I18n::t($lang),
            'lockedLocation'    => $lockedLocation,
            'resumeDraftId'     => $resumeId !== '' ? $resumeId : null,
            'resumeDraft'       => $resumeDraft,
            'forceSelf'         => $forceSelf,
            'selfFirstName'     => $selfFirstName,
            'selfLastName'      => $selfLastName,
            'selfPhone'         => $actor->phone,
            // Lets a quick-launch link (the PWA's home-screen shortcut,
            // the Karyakarta home screen's own button) jump straight past
            // the programme picker instead of relying on it merely being
            // first in the list.
            'preselectProgramme' => $forceSelf ? 'hc' : (string) ($this->request->getGet('programme') ?? ''),
        ]);
    }

    /**
     * Autosaves an in-progress New Enrolment form as the Karyakarta types
     * — the client generates `draft_id` (a UUID) itself and keeps saving
     * under the same id, so this is always an upsert. Best-effort: the
     * client already wrote the same payload to localStorage first, so a
     * failure here (offline, a dropped request) just means the draft
     * isn't visible in the Enrolments list yet, not that it's lost.
     */
    public function saveDraft()
    {
        $id = trim((string) $this->request->getPost('draft_id'));
        if ($id === '' || ! preg_match('/^[A-Za-z0-9-]{8,36}$/', $id)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Invalid draft id.']);
        }

        $payloadJson = (string) $this->request->getPost('payload');
        $payload     = json_decode($payloadJson, true);
        if (! is_array($payload)) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Invalid payload.']);
        }

        $actor = $this->currentUser();
        $model = new EnrolmentDraftModel();
        $existing = $model->find($id);
        if ($existing && (int) $existing['created_by_user_id'] !== $actor->id && ! $actor->hasGlobalAccess()) {
            throw PageNotFoundException::forPageNotFound();
        }

        $memberName = trim(
            (string) ($payload['member_first_name'] ?? '') . ' ' . (string) ($payload['member_last_name'] ?? '')
        );

        $model->save([
            'id'                 => $id,
            'created_by_user_id' => $existing['created_by_user_id'] ?? $actor->id,
            'prant_id'           => $payload['prant_id'] ?? null,
            'jila_id'            => $payload['jila_id'] ?? null,
            'prakhand_id'        => $payload['prakhand_id'] ?? null,
            'programme_code'     => $payload['programme_code'] ?? null,
            'member_name'        => $memberName !== '' ? $memberName : null,
            'payload'            => $payloadJson,
        ]);

        // regenerate=true rotates the CSRF token on every verified POST —
        // hand the new one back so the next autosave call (this is a
        // repeating fetch(), not a page load) doesn't fail CSRF with the
        // now-stale token it was holding.
        return $this->response->setJSON([
            'ok'        => true,
            'id'        => $id,
            csrf_token() => csrf_hash(),
        ]);
    }

    /** Fetches a draft's payload — used when resuming on a device/browser whose localStorage doesn't have it. */
    public function getDraft($id)
    {
        $draft = (new EnrolmentDraftModel())->scopedTo($this->currentUser())->find((string) $id);
        if (! $draft) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->setJSON(['payload' => json_decode($draft['payload'], true)]);
    }

    /** Discards a pending draft from the Enrolments list — the enrolment was never started, or the Karyakarta no longer wants to resume it. */
    public function deleteDraft($id)
    {
        $model = new EnrolmentDraftModel();
        $draft = $model->scopedTo($this->currentUser())->find((string) $id);
        if ($draft) {
            $model->delete($draft['id']);
        }

        return redirect()->to('/admin/enrolments?view=drafts')->with('success', 'Draft discarded.');
    }

    public function store()
    {
        $actor = $this->currentUser();
        $post  = $this->request->getPost();

        // Same gate as newForm(): until this account's own Hithachintak
        // enrolment has a receipt, every submission from here is forced
        // to register *them* — programme, First name, and Mobile number
        // are taken from the account itself, not trusted from the
        // request, so the lock can't be bypassed by editing hidden/
        // readonly fields client-side.
        $isSelfEnrolment = ! $actor->isDevAdmin() && ! (new EnrolmentModel())->hasCompletedHithachintak($actor->phone);
        if ($isSelfEnrolment) {
            $post['programme_code'] = 'hc';
            $nameParts = explode(' ', trim($actor->name), 2);
            $post['member_first_name'] = $nameParts[0] ?? '';
            $post['member_last_name']  = $nameParts[1] ?? '';
            $post['member_phone']      = $actor->phone;
        }

        if (! empty($post['member_pan'])) {
            // Normalize before validation, not after, so a lower/mixed-case
            // PAN (people rarely type it in the printed all-caps form)
            // doesn't fail the format check that's about to run on it.
            $post['member_pan'] = strtoupper(trim($post['member_pan']));
        }

        $rules = [
            'programme_code'    => 'required',
            'prant_id'          => 'required|is_natural_no_zero',
            'member_first_name' => 'required|min_length[2]|max_length[100]',
            'member_last_name'  => 'permit_empty|max_length[100]',
            'member_phone'      => 'required|regex_match[/^[6-9][0-9]{9}$/]',
            'age_or_dob'        => 'required|max_length[30]',
            'address'           => 'required|max_length[255]',
            'pincode'           => 'required|regex_match[/^[0-9]{6}$/]',
            'email'             => 'permit_empty|valid_email',
            'amount'            => 'permit_empty|numeric',
        ];

        // PAN is mandatory only for the Autopay Monthly Donation programme
        // — every other programme leaves it optional (validated for format
        // only, at the MemberModel level, if someone does supply one).
        $programme = (new ProgrammeModel())->byCode((string) ($post['programme_code'] ?? ''));
        if ($programme && $programme['is_recurring']) {
            $rules['member_pan'] = 'required|regex_match[/^[A-Z]{5}[0-9]{4}[A-Z]$/]';
        }

        if (! $this->validateData($post, $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // A Hithachintak-registered number can't be reused as the primary
        // member of a second Hithachintak enrolment (client already warns
        // about this on blur — this is the authoritative check, never
        // trust the browser to have actually run it). Other programmes
        // don't share this restriction, and this account's own forced
        // self-enrolment is exempt too — retrying/resuming a self-
        // enrolment that previously stalled (e.g. at OTP) is expected,
        // not a duplicate.
        $phoneDigits = preg_replace('/\D/', '', (string) $post['member_phone']);
        if (! $isSelfEnrolment && $programme && $programme['code'] === 'hc' && (new EnrolmentModel())->hasActiveHithachintak($phoneDigits)) {
            return redirect()->back()->withInput()
                ->with('error', 'This mobile number is already registered for Hithachintak.');
        }

        $familyFirstNames  = $this->request->getPost('family_first_name') ?? [];
        $familyLastNames   = $this->request->getPost('family_last_name') ?? [];
        $familyAges        = $this->request->getPost('family_age') ?? [];
        $familyProfessions = $this->request->getPost('family_profession') ?? [];
        $familyContacts    = $this->request->getPost('family_contact') ?? [];
        $familyMembers     = [];
        foreach ($familyFirstNames as $i => $firstName) {
            $familyMembers[] = [
                'first_name'     => $firstName,
                'last_name'      => $familyLastNames[$i] ?? null,
                'age_or_dob'     => $familyAges[$i] ?? null,
                'profession'     => $familyProfessions[$i] ?? null,
                'contact_number' => $familyContacts[$i] ?? null,
            ];
        }

        try {
            $enrolment = $this->service()->start([
                'programme_code'    => $post['programme_code'],
                'prant_id'          => $post['prant_id'],
                'jila_id'           => ($post['jila_id'] ?? '') ?: null,
                'prakhand_id'       => ($post['prakhand_id'] ?? '') ?: null,
                // The form no longer has its own language picker (the
                // topbar selector already sets this, redundantly) — take
                // it from the same session value newForm() rendered the
                // page with, not a submitted field.
                'language'          => (string) (session('ui_language') ?? 'en'),
                'payment_mode'      => ($post['payment_mode'] ?? '') === 'cash' ? 'cash' : 'online',
                'member_first_name' => $post['member_first_name'],
                'member_last_name'  => $post['member_last_name'] ?? null,
                'member_phone'      => preg_replace('/\D/', '', $post['member_phone']),
                'email'             => $post['email'] ?? null,
                'age_or_dob'        => $post['age_or_dob'] ?? null,
                'profession'        => $post['profession'] ?? null,
                'address'           => $post['address'] ?? null,
                'pincode'           => $post['pincode'] ?? null,
                'pan'               => ! empty($post['member_pan']) ? $post['member_pan'] : null,
                'amount'            => $post['amount'] ?? 0,
                'family_members'    => $familyMembers,
            ], $this->currentUser());
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        // The enrolment is real now — the autosaved draft it may have
        // started from has served its purpose. Best-effort: a missing or
        // already-cleared draft is not an error worth surfacing here.
        $draftId = trim((string) ($post['draft_id'] ?? ''));
        if ($draftId !== '') {
            (new EnrolmentDraftModel())->where('id', $draftId)->where('created_by_user_id', $this->currentUser()->id)->delete();
        }

        // Cash needs the member verified by OTP before anything is
        // collected; an online payment goes straight to the gateway — the
        // member authorizing it on their own device is the verification.
        if ($enrolment->status === Enrolment::STATUS_OTP_PENDING) {
            return redirect()->to("/admin/enrolments/{$enrolment->id}/otp");
        }

        return redirect()->to("/admin/enrolments/{$enrolment->id}/payment?auto=1");
    }

    public function showOtp($id)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);
        if ($enrolment->status !== Enrolment::STATUS_OTP_PENDING) {
            return redirect()->to($this->nextStepUrl($enrolment));
        }

        $t = \App\Libraries\Enrolment\I18n::t($enrolment->language);

        return view('enrolments/otp', [
            'title'     => $t['verifyMemberTitle'],
            'enrolment' => $enrolment,
            't'         => $t,
        ]);
    }

    public function sendOtp($id)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);

        try {
            $this->service()->issueOtp($enrolment);

            return redirect()->to("/admin/enrolments/{$id}/otp")->with('info', 'A new OTP has been sent.');
        } catch (\RuntimeException $e) {
            return redirect()->to("/admin/enrolments/{$id}/otp")->with('error', $e->getMessage());
        }
    }

    public function verifyOtp($id)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);
        $code      = (string) $this->request->getPost('otp');

        if (! $this->service()->verifyOtp($enrolment, $code)) {
            return redirect()->to("/admin/enrolments/{$id}/otp")->with('error', 'Incorrect or expired OTP.');
        }

        return redirect()->to("/admin/enrolments/{$id}/payment");
    }

    public function showPayment($id)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);
        if ($enrolment->status === Enrolment::STATUS_OTP_PENDING) {
            return redirect()->to("/admin/enrolments/{$id}/otp");
        }
        if ($enrolment->hasReceipt()) {
            return redirect()->to("/admin/enrolments/{$id}/receipt");
        }

        $t = \App\Libraries\Enrolment\I18n::t($enrolment->language);

        return view('enrolments/payment', [
            'title'     => $t['paymentTitle'],
            'enrolment' => $enrolment,
            't'         => $t,
            'programme' => (new ProgrammeModel())->find($enrolment->programme_id),
            'gatewayReady' => (new \App\Libraries\Cashfree\CashfreeClient())->isConfiguredForPrant($enrolment->prant_id),
            // Set only on the redirect straight from the New Enrolment form
            // when Online was chosen — nudges the gateway step to launch
            // itself instead of making the Karyakarta click again.
            'autoPay'   => (bool) $this->request->getGet('auto'),
        ]);
    }

    public function payUpi($id)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);

        $order = $this->service()->createCashfreeOrder(
            $enrolment,
            site_url("admin/enrolments/{$id}/pay/return"),
            base_url("webhooks/cashfree/{$enrolment->prant_id}")
        );

        if (! $order || empty($order['payment_session_id'])) {
            return redirect()->to("/admin/enrolments/{$id}/payment")
                ->with('error', 'This Prant\'s payment gateway isn\'t configured yet. Please collect cash instead, or ask a Super Admin to set it up under Settings & Integrations.');
        }

        $t = \App\Libraries\Enrolment\I18n::t($enrolment->language);

        return view('enrolments/checkout', [
            'title'             => $t['completePaymentTitle'],
            'enrolment'         => $enrolment,
            't'                 => $t,
            'paymentSessionId'  => $order['payment_session_id'],
            'cashfreeEnv'       => ENVIRONMENT === 'production' ? 'production' : 'sandbox',
        ]);
    }

    public function payReturn($id)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);

        if (! $enrolment->isPaid() && $enrolment->cashfree_order_id) {
            $order = (new \App\Libraries\Cashfree\CashfreeClient())->fetchOrder($enrolment->prant_id, $enrolment->cashfree_order_id);
            if ($order && ($order['order_status'] ?? '') === 'PAID') {
                $paymentId = $order['payments'][0]['cf_payment_id'] ?? ($order['cf_order_id'] ?? 'UNKNOWN');
                $this->service()->markCashfreePaid($enrolment->cashfree_order_id, (string) $paymentId);
            }
        }

        return redirect()->to("/admin/enrolments/{$id}/payment");
    }

    /** Sets up the Cashfree Autopay mandate for a recurring-donation enrolment. */
    public function payAutopay($id)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);

        // A mandate was already started for this enrolment — send them
        // back to wait rather than creating a second, duplicate one.
        if ($enrolment->autopay_subscription_id) {
            return redirect()->to("/admin/enrolments/{$id}/payment");
        }

        $subscription = $this->service()->createAutopaySubscription(
            $enrolment,
            site_url("admin/enrolments/{$id}/pay/autopay/return")
        );

        if (! $subscription || empty($subscription['subscription_session_id'])) {
            return redirect()->to("/admin/enrolments/{$id}/payment")
                ->with('error', 'This Prant\'s payment gateway isn\'t configured yet. Ask a Super Admin to set it up under Settings & Integrations.');
        }

        $t = \App\Libraries\Enrolment\I18n::t($enrolment->language);

        return view('enrolments/autopay_checkout', [
            'title'                  => $t['authorizeAutopay'],
            'enrolment'              => $enrolment,
            't'                      => $t,
            'subscriptionSessionId'  => $subscription['subscription_session_id'],
            'cashfreeEnv'            => ENVIRONMENT === 'production' ? 'production' : 'sandbox',
        ]);
    }

    public function autopayReturn($id)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);

        if (! $enrolment->isPaid() && $enrolment->autopay_subscription_id) {
            $subRow = (new \App\Models\AutopaySubscriptionModel())->find($enrolment->autopay_subscription_id);
            if ($subRow && $subRow->cashfree_subscription_id) {
                $subscription = (new \App\Libraries\Cashfree\CashfreeClient())->fetchSubscription($enrolment->prant_id, $subRow->cashfree_subscription_id);
                if ($subscription && ($subscription['subscription_status'] ?? '') === 'ACTIVE') {
                    // Authorization only — no receipt yet. The first real
                    // charge (and its receipt) arrives as its own
                    // SUBSCRIPTION_PAYMENT_SUCCESS webhook, same as every
                    // month after it (see EnrolmentService::recordAutopayCharge()).
                    $this->service()->activateAutopaySubscription($subRow->cashfree_subscription_id);
                }
            }
        }

        return redirect()->to("/admin/enrolments/{$id}/payment");
    }

    public function payCash($id)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);
        if ($enrolment->hasReceipt() || $enrolment->status === Enrolment::STATUS_CASH_COLLECTED) {
            return redirect()->to($this->nextStepUrl($enrolment));
        }

        $enrolment = $this->service()->markCashCollected($enrolment);

        return redirect()->to($this->nextStepUrl($enrolment));
    }

    /** Launches the Karyakarta's own Cashfree payment to remit Hithachintak cash the instant it's collected. */
    public function payRemit($id)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);
        if ($enrolment->status !== Enrolment::STATUS_CASH_COLLECTED) {
            return redirect()->to($this->nextStepUrl($enrolment));
        }

        $order = $this->service()->createRemittanceOrder(
            $enrolment,
            site_url("admin/enrolments/{$id}/remit/return"),
            base_url("webhooks/cashfree/{$enrolment->prant_id}")
        );

        if (! $order || empty($order['payment_session_id'])) {
            return redirect()->to("/admin/enrolments/{$id}/payment")
                ->with('error', 'This Prant\'s payment gateway isn\'t configured yet. Ask a Super Admin to set it up under Settings & Integrations before collecting Hithachintak cash.');
        }

        return view('enrolments/checkout', [
            'title'             => 'Remit cash via UPI',
            'enrolment'         => $enrolment,
            'paymentSessionId'  => $order['payment_session_id'],
            'cashfreeEnv'       => ENVIRONMENT === 'production' ? 'production' : 'sandbox',
        ]);
    }

    public function remitReturn($id)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);

        if (! $enrolment->isPaid() && $enrolment->cashfree_order_id) {
            $order = (new \App\Libraries\Cashfree\CashfreeClient())->fetchOrder($enrolment->prant_id, $enrolment->cashfree_order_id);
            if ($order && ($order['order_status'] ?? '') === 'PAID') {
                $paymentId = $order['payments'][0]['cf_payment_id'] ?? ($order['cf_order_id'] ?? 'UNKNOWN');
                $this->service()->confirmCashRemittance($enrolment->cashfree_order_id, (string) $paymentId);
            }
        }

        return redirect()->to("/admin/enrolments/{$id}/payment");
    }

    public function receipt($id)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);
        if (! $enrolment->hasReceipt()) {
            return redirect()->to($this->nextStepUrl($enrolment));
        }

        $member       = (new \App\Models\MemberModel())->find($enrolment->member_id);
        $programme    = (new ProgrammeModel())->find($enrolment->programme_id);
        $prant        = (new PrantModel())->find($enrolment->prant_id);
        $collector    = (new \App\Models\UserModel())->find($enrolment->collected_by_user_id);
        $t            = \App\Libraries\Enrolment\I18n::t($enrolment->language);
        $trustName    = \App\Libraries\Enrolment\I18n::trustName($enrolment->language);
        $trustPan     = \App\Libraries\Enrolment\TrustInfo::PAN;
        $collectedBy  = $collector->name ?? '—';
        $householdRows = (new EnrolmentFamilyMemberModel())->householdRows($enrolment->id, $member);

        return view('enrolments/receipt', compact('enrolment', 'member', 'programme', 'prant', 't', 'trustName', 'trustPan', 'collectedBy', 'householdRows') + ['title' => 'Receipt']);
    }

    public function receiptPdf($id)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);
        if (! $enrolment->hasReceipt()) {
            return redirect()->to($this->nextStepUrl($enrolment));
        }

        $member    = (new \App\Models\MemberModel())->find($enrolment->member_id);
        $programme = (new ProgrammeModel())->find($enrolment->programme_id);
        $prant     = (new PrantModel())->find($enrolment->prant_id);

        $generator = new \App\Libraries\Enrolment\ReceiptPdfGenerator();
        $pdf       = $generator->generate($enrolment, $member, $programme, $prant);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $generator->filename($enrolment) . '"')
            ->setBody($pdf);
    }

    public function sendReceipt($id, string $channel)
    {
        $id = (int) $id;

        $enrolment = $this->authorize($id);
        if (! $enrolment->hasReceipt() || ! in_array($channel, ['whatsapp', 'email', 'sms'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $sent = $this->service()->sendReceiptNotifications($enrolment, [$channel]);
        $ok   = $sent[$channel] ?? false;

        return redirect()->to("/admin/enrolments/{$id}/receipt")
            ->with($ok ? 'success' : 'error', $ok ? 'Receipt sent via ' . ucfirst($channel) . '.' : 'Could not send via ' . ucfirst($channel) . ' — check Settings & Integrations.');
    }

    /** Lists Autopay mandates — separate from the enrolments register since it's the pledge, not a single payment. */
    public function subscriptions()
    {
        $rows = (new \App\Models\AutopaySubscriptionModel())->scopedTo($this->currentUser())->withDetails()
            ->orderBy('autopay_subscriptions.created_at', 'DESC')->findAll();

        return view('enrolments/subscriptions', [
            'title'         => 'Autopay Subscriptions',
            'subscriptions' => $rows,
        ]);
    }

    public function exportCsv()
    {
        $model = $this->applyFilters((new EnrolmentModel())->scopedTo($this->currentUser())->withDetails());
        $rows  = $model->orderBy('enrolments.created_at', 'DESC')->findAll();

        $filename = 'enrolments-' . date('Y-m-d') . '.csv';
        $out = fopen('php://temp', 'w+');
        fputcsv($out, ['Receipt No', 'Member', 'Phone', 'Programme', 'Prant', 'Jila', 'Prakhand', 'Karyakarta', 'Amount', 'Mode', 'Status', 'Date']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r->receipt_no, $r->member_name, $r->member_phone, $r->programme_name,
                $r->prant_name, $r->jila_name, $r->prakhand_name, $r->karyakarta_name,
                $r->amount, $r->payment_mode, $r->status, $r->created_at,
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->setBody($csv);
    }

    private function nextStepUrl(Enrolment $enrolment): string
    {
        return match ($enrolment->status) {
            Enrolment::STATUS_OTP_PENDING => "/admin/enrolments/{$enrolment->id}/otp",
            Enrolment::STATUS_CASH_COLLECTED => "/admin/enrolments/{$enrolment->id}/remit",
            default => $enrolment->hasReceipt() ? "/admin/enrolments/{$enrolment->id}/receipt" : "/admin/enrolments/{$enrolment->id}/payment",
        };
    }
}
