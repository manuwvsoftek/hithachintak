<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\User;
use App\Libraries\Msg91\Msg91Sms;
use App\Libraries\Rbac\LocationScope;
use App\Libraries\Secrets\Vault;
use App\Models\AuditLogModel;
use App\Models\JilaModel;
use App\Models\PrakhandModel;
use App\Models\PrantModel;
use App\Models\UserDraftModel;
use App\Models\UserJilaScopeModel;
use App\Models\UserModel;
use App\Models\UserPrakhandScopeModel;
use App\Models\UserPrantScopeModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class UsersController extends BaseController
{
    public const AYAMS = [
        'Bajrang Dal', 'Dharma Prasar', 'Dharma Yatra', 'Dharmacharya Sampark', 'Durgavahini', 'Goraksha', 'Mandir & Archak Purohit', 'Matrushakti', 'Naitik Shiksha', 'Prachar Prasar', 'Samajik Samarasata', 'Sangathan', 'Sanskrit & Ved Vidyalay', 'Satsang', 'Seva', 'Vidhi Prakostha', 'Vishesh Sampark'
    ];

    public function index()
    {
        $userModel = new UserModel();
        $actor     = $this->currentUser();

       $query = $userModel->visibleTo($actor)
            ->select('users.*, prakhands.name AS prakhand_name, jilas.name AS jila_name, prants.name AS prant_name')
            ->join('prakhands', 'prakhands.id = users.prakhand_id', 'left')
            ->join('jilas', 'jilas.id = users.jila_id', 'left')
            ->join('prants', 'prants.id = users.prant_id', 'left')
            ->join('users creators', 'creators.id = users.created_by', 'left');
        if ($role = $this->request->getGet('role')) {
            $query->where('users.role', $role);
        }
        if ($search = $this->request->getGet('q')) {
            // Qualified with the table — the left join on `prakhands` (for
            // its display name column) also carries its own `name`, so an
            // unqualified like('name', …) is ambiguous the moment both are
            // in the query (MySQL error 1052; SQLite let it slide).
            $query->groupStart()->like('users.name', $search)->orLike('users.phone', $search)->groupEnd();
        }

        $users = $query->orderBy('name', 'ASC')->findAll();
        $locationLabels = $this->locationLabelsFor($users);
        $canManageMap   = $this->canManageMap($actor, $users);

        // Dev Admin never appears here — not in the directory (already
        // excluded by visibleTo() itself), not in these hierarchy counts,
        // and not as a role-filter option below, so its existence isn't
        // surfaced to anyone through this page at all.
        $visibleRoleLabels = array_diff_key(User::ROLE_LABELS, [User::ROLE_DEV_ADMIN => true]);

        $hierarchy = [];
        foreach ($visibleRoleLabels as $role => $label) {
            $hierarchy[] = [
                'role'  => $label,
                'count' => (new UserModel())->visibleTo($actor)->where('role', $role)->where('status', 'active')->countAllResults(),
            ];
        }

        // Same resume pattern as the New Enrolment form: an explicit
        // ?resume=<id> from the drafts panel, or a bounce-back from a
        // failed server-side validation (old('draft_id') survives that
        // redirect even though the rest of the POST data doesn't).
        $resumeId = (string) ($this->request->getGet('resume') ?? '');
        if ($resumeId === '') {
            $resumeId = (string) (old('draft_id') ?? '');
        }
        $resumeDraft = null;
        if ($resumeId !== '') {
            $draft = (new UserDraftModel())->scopedTo($actor)->find($resumeId);
            if ($draft) {
                $resumeDraft = json_decode($draft['payload'], true);
            }
        }
         $prants = (new PrantModel())->orderBy('name', 'ASC')->findAll();
        return view('users/index', [
            'title'      => 'Users & Hierarchy',
            'activeTab'  => 'directory',
            'users'      => $users,
            'draftCount' => $this->draftCount($actor),
            'pager'      => $userModel->pager,
            'locationLabels' => $locationLabels,
            'canManageMap' => $canManageMap,
            'hierarchy'  => $hierarchy,
            'prants'     => $prants,
            'actorPrants' => $this->actorPrantOptions($actor, $prants),
            'roleLabels' => $visibleRoleLabels,
            'hasGlobalAccess' => $actor->hasGlobalAccess(),
            'currentUserId' => $actor->id,
            'manageableRoles' => $this->manageableRoles($actor),
            'actor' => $actor,
            'drafts'         => (new UserDraftModel())->scopedTo($actor)->orderBy('updated_at', 'DESC')->findAll(),
            'resumeDraftId'  => $resumeId !== '' ? $resumeId : null,
            'resumeDraft'    => $resumeDraft,
            'ayams'       => self::AYAMS,
        ]);
        //  return view('users/index', [
        //     'title'      => 'Users & Hierarchy',
        //     'activeTab'  => 'directory',
        //     'draftCount' => $this->draftCount($actor),
        //     'users'      => $users,
        //     'pager'      => $userModel->pager,
        //     'locationLabels' => $locationLabels,
        //     'hierarchy'  => $hierarchy,
        //     'roleLabels' => $visibleRoleLabels,
        //     'hasGlobalAccess' => $actor->hasGlobalAccess(),
        //     'currentUserId' => $actor->id,
        //     'actor' => $actor,
        // ]);
    }

     /** The New User form — its own page now (see index()'s comment). */
    public function newForm()
    {
        $actor  = $this->currentUser();
        $prants = (new PrantModel())->orderBy('name', 'ASC')->findAll();

        // Same resume pattern as the New Enrolment form: an explicit
        // ?resume=<id> from the Pending Drafts page, or a bounce-back from
        // a failed server-side validation (old('draft_id') survives that
        // redirect even though the rest of the POST data doesn't).
        $resumeId = (string) ($this->request->getGet('resume') ?? '');
        if ($resumeId === '') {
            $resumeId = (string) (old('draft_id') ?? '');
        }
        $resumeDraft = null;
        if ($resumeId !== '') {
            $draft = (new UserDraftModel())->scopedTo($actor)->find($resumeId);
            if ($draft) {
                $resumeDraft = json_decode($draft['payload'], true);
            }
        }

        return view('users/new', [
            'title'      => 'New User',
            'activeTab'  => 'new',
            'draftCount' => $this->draftCount($actor),
            'prants'     => $prants,
            'actorPrants' => $this->actorPrantOptions($actor, $prants),
            'roleLabels' => $this->visibleRoleLabels(),
            'hasGlobalAccess' => $actor->hasGlobalAccess(),
            'manageableRoles' => $this->manageableRoles($actor),
            'actor' => $actor,
            'resumeDraftId'  => $resumeId !== '' ? $resumeId : null,
            'resumeDraft'    => $resumeDraft,
            'ayams'          => self::AYAMS,
        ]);
    }

    /** Pending (autosaved, never submitted) New User forms — its own page now (see index()'s comment). */
    public function drafts()
    {
        $actor = $this->currentUser();

        return view('users/drafts', [
            'title'      => 'Pending User Drafts',
            'activeTab'  => 'drafts',
            'draftCount' => $this->draftCount($actor),
            'drafts'     => (new UserDraftModel())->scopedTo($actor)->orderBy('updated_at', 'DESC')->findAll(),
            'roleLabels' => $this->visibleRoleLabels(),
            'actor'      => $actor,
        ]);
    }

    /**
     * Dev Admin never appears here — not in the directory (already
     * excluded by visibleTo() itself), not in the hierarchy counts, and
     * not as a role-filter option, so its existence isn't surfaced to
     * anyone through this page at all.
     *
     * @return array<string, string>
     */
    private function visibleRoleLabels(): array
    {
        return array_diff_key(User::ROLE_LABELS, [User::ROLE_DEV_ADMIN => true]);
    }

    private function draftCount(User $actor): int
    {
        return (new UserDraftModel())->scopedTo($actor)->countAllResults();
    }

     /**
     * The directory table has one "location" cell per row, but what belongs
     * in it depends on the row's own role's tier — a Pranta/Kshetra Admin's
     * is their Prant(s), a Jila/Vibhag Admin's is their Jila(s), a Prakhand
     * Admin's (and a Karyakarta's) is their Prakhand. Single-value roles
     * read straight off the row's own joined name column; a role that can
     * hold more than one (scope_all, or more than one row in its junction
     * table) needs one batched count query per tier instead of an N+1 — so
     * this runs once per index() call across every visible row, not once
     * per row.
     *
     * @param list<User> $users
     *
     * @return array<int, string>
     */
    private function locationLabelsFor(array $users): array
    {
        $userIds = array_map(static fn (User $u) => $u->id, $users);
        if (! $userIds) {
            return [];
        }

        $countsFor = static function (string $modelClass, string $column) use ($userIds): array {
            $rows = (new $modelClass())
                ->select("user_id, COUNT(*) AS c")
                ->whereIn('user_id', $userIds)
                ->groupBy('user_id')
                ->findAll();

            return array_column($rows, 'c', 'user_id');
        };
        $prantCounts    = $countsFor(UserPrantScopeModel::class, 'prant_id');
        $jilaCounts     = $countsFor(UserJilaScopeModel::class, 'jila_id');
        $prakhandCounts = $countsFor(UserPrakhandScopeModel::class, 'prakhand_id');

        $labels = [];
        foreach ($users as $u) {
            if (in_array($u->role, [User::ROLE_KENDREEYA_ADMIN, User::ROLE_PRANTA_ADMIN], true)) {
                $labels[$u->id] = $this->tierLabel($u->scope_all, (int) ($prantCounts[$u->id] ?? 0), $u->prant_name, 'All Prants');
            } elseif ($u->role === User::ROLE_SUB_ADMIN) {
                $labels[$u->id] = $this->tierLabel($u->scope_all, (int) ($jilaCounts[$u->id] ?? 0), $u->jila_name, 'All Jilas in ' . ($u->prant_name ?? '—'));
            } elseif ($u->role === User::ROLE_PRAKHAND_ADMIN) {
                $labels[$u->id] = $this->tierLabel($u->scope_all, (int) ($prakhandCounts[$u->id] ?? 0), $u->prakhand_name, 'All Prakhands in ' . ($u->jila_name ?? '—'));
            } else {
                // Karyakarta — single fixed Prakhand, same as before this
                // column covered the other roles too.
                $labels[$u->id] = $u->prakhand_name ?? '—';
            }
        }

        return $labels;
    }

      /**
     * Whether $actor can Edit/Block each row — same rule as
     * isOwnCreation(), computed once per visible page of the directory
     * instead of a lookup per row (the row's own joined creator_role
     * column already carries what isOwnCreation() would otherwise fetch
     * with a query of its own).
     *
     * @param list<User> $users
     *
     * @return array<int, bool>
     */
    private function canManageMap(User $actor, array $users): array
    {
        if ($actor->hasGlobalAccess()) {
            return array_fill_keys(array_map(static fn (User $u) => $u->id, $users), true);
        }

        $map = [];
        foreach ($users as $u) {
            $map[$u->id] = (int) $u->created_by === $actor->id
                || ! $u->created_by
                || ($u->creator_role !== null && $actor->outranks($u->creator_role));
        }

        return $map;
    }


    private function tierLabel(bool $scopeAll, int $multiCount, ?string $oneName, string $allLabel): string
    {
        if ($scopeAll) {
            return $allLabel;
        }
        $name = $oneName ?? '—';

        return $multiCount > 1 ? $name . ' +' . ($multiCount - 1) . ' more' : $name;
    }

    /**
     * Which roles $actor is allowed to create/manage — strictly below their
     * own rank, and never Super Admin or Dev Admin (those two are
     * provisioned only via their own seeders, not through this UI — Dev
     * Admin's rank already puts it out of reach of every actor's "< own
     * rank" check, but the explicit exclusion documents that on purpose
     * rather than leaving it as an accident of the rank numbers).
     *
     * @return list<string>
     */
    private function manageableRoles(User $actor): array
    {
        return array_values(array_filter(
            array_keys(User::ROLE_LABELS),
            static fn ($role) => ! in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_DEV_ADMIN], true)
                && User::rank($role) < User::rank($actor->role)
        ));
    }

     /**
     * A Pranta Admin (or Kendreeya Admin, which is assigned a set of Prants
     * the same way — see LocationScope::prantIds()) assigned to more than
     * one Prant (multi-select, or "All Prants") needs a picker on the
     * New/Edit User forms instead of the usual fixed "your Prant" text —
     * this is that option list, or null when a fixed single Prant is
     * enough (every other actor, and one assigned to exactly one Prant).
     *
     * @param list<array<string, mixed>> $allPrants every Prant, already loaded by the caller
     * @return list<array<string, mixed>>|null
     */
    private function actorPrantOptions(User $actor, array $allPrants): ?array
    {
        if (! in_array($actor->role, [User::ROLE_PRANTA_ADMIN, User::ROLE_KENDREEYA_ADMIN], true)) {
            return null;
        }

        $ids = LocationScope::prantIds($actor);
        if ($ids === null) {
            return $allPrants; // scope_all — any Prant is fair game
        }
        if (count($ids) <= 1) {
            return null; // exactly one assigned Prant — the fixed text is enough
        }

        return array_values(array_filter($allPrants, static fn ($p) => in_array((int) $p['id'], $ids, true)));
    }


    

    /** Loads a target user, but only if $actor is allowed to manage them (in-scope and outranked). Returns null otherwise. */
    private function manageableTarget(User $actor, int $id): ?User
    {
        $target = (new UserModel())->visibleTo($actor)->find($id);
        if (! $target || ! $actor->outranks($target->role)) {
            return null;
        }

        return $target;
    }

      /**
     * Edit and Block are narrowed to the account's own creator, or an
     * actor who outranks that creator, for every non-globally-scoped
     * role (Pranta/Kendreeya/Jila/Prakhand Admin) — they can still see a
     * user another admin added within their own shared location
     * (visibleTo() already covers that), just not change or block one
     * neither they nor a superior of whoever created it. A target with
     * no creator on record (legacy rows from before this column existed)
     * is never locked out of editing for want of one. Super Admin/Dev
     * Admin are exempt, same as everywhere else in this controller.
     * Password actions (view/reset) are deliberately NOT gated by this —
     * only Edit and Block are.
     */
    private function isOwnCreation(User $actor, User $target): bool
    {
        if ($actor->hasGlobalAccess() || (int) $target->created_by === $actor->id) {
            return true;
        }
        if (! $target->created_by) {
            return true;
        }
        $creator = (new UserModel())->find($target->created_by);

        return $creator && $actor->outranks($creator->role);
    }

      /**
     * The Prant the user being created/edited belongs to, from the actor's
     * point of view. A globally-scoped actor (or a Pranta Admin with
     * "All Prants") picks from the full list; a Pranta Admin with more
     * than one specifically assigned Prant picks from a form field too,
     * but only a value actually within their own assigned set is ever
     * trusted — never a raw POST value for a scope they don't hold.
     * Every other actor (a single-Prant Pranta Admin included) is simply
     * fixed to their own Prant, matching their own form field being a
     * disabled display rather than an input.
     */
    private function resolveActorPrantId(User $actor): ?int
    {
        $posted = $this->request->getPost('prant_id') ? (int) $this->request->getPost('prant_id') : null;

        if ($actor->hasGlobalAccess()) {
            return $posted;
        }

        if (in_array($actor->role, [User::ROLE_PRANTA_ADMIN, User::ROLE_KENDREEYA_ADMIN], true)) {
            $allowedIds = LocationScope::prantIds($actor);
            if ($allowedIds === null) {
                return $posted; // scope_all — any Prant is fair game
            }
            if (count($allowedIds) > 1) {
                return ($posted !== null && in_array($posted, $allowedIds, true)) ? $posted : null;
            }
        }

        return $actor->prant_id;
    }

      /**
     * The Jila a user being created/edited belongs to — mirrors
     * resolveActorPrantId() one tier down. A Jila Admin (Sub Admin) or
     * Prakhand Admin actor is fixed to their own single Jila (trusted,
     * never the raw POST value). A Vibhag Admin actor picks from their
     * own assigned Jila set, with the same "the posted value must
     * actually be one of their own" guard. Every other actor (global
     * access, Pranta Admin, Kshetra Admin) picks freely from POST — their
     * own fixed context is the Prant tier, not Jila, so the cascading
     * Jila dropdown (scoped to whatever Prant was already resolved)
     * already constrains them in practice.
     */
    private function resolveActorJilaId(User $actor): ?int
    {
        $posted = $this->request->getPost('jila_id') ? (int) $this->request->getPost('jila_id') : null;

        if (in_array($actor->role, [User::ROLE_SUB_ADMIN, User::ROLE_PRAKHAND_ADMIN], true)) {
            return $actor->jila_id;
        }

        return $posted;
    }


    /**
     * Prant/Jila/Prakhand for a user $actor is creating or editing.
     * Never trusts a submitted value for a level the actor is themself
     * fixed to — otherwise e.g. a Jila Admin could plant a user in
     * another Jila by editing the POST body. A level the actor isn't
     * fixed to (because they outrank it) is free to pick from the form.
     *
     * A role's own tier (Prant for Pranta Admin, Jila for Jila Admin,
     * Prakhand for Prakhand Admin) can additionally be assigned "All" or
     * a multi-select set instead of a single value — scope_all/prantIds/
     * jilaIds/prakhandIds carry that; the single *_id fields still get a
     * value (the first of the set) so every legacy column stays populated
     * for code that hasn't been routed through LocationScope.
     *
     * @return array{0: ?int, 1: ?int, 2: ?int, 3: bool, 4: list<int>, 5: list<int>, 6: list<int>}
     */
    private function resolveLocationFields(User $actor): array
    {
        $role = (string) $this->request->getPost('role');
        $prantId = $this->resolveActorPrantId($actor);
        $jilaId  = $this->resolveActorJilaId($actor);

        // $prantId = $actor->hasGlobalAccess()
        //     ? ($this->request->getPost('prant_id') ? (int) $this->request->getPost('prant_id') : null)
        //     : $actor->prant_id;

        // $jilaId = in_array($actor->role, [User::ROLE_SUB_ADMIN, User::ROLE_PRAKHAND_ADMIN], true)
        //     ? $actor->jila_id
        //     : ($this->request->getPost('jila_id') ? (int) $this->request->getPost('jila_id') : null);

        $prakhandId = $actor->role === User::ROLE_PRAKHAND_ADMIN
            ? $actor->prakhand_id
            : ($this->request->getPost('prakhand_id') ? (int) $this->request->getPost('prakhand_id') : null);

        $scopeAll   = false;
        $prantIds   = [];
        $jilaIds    = [];
        $prakhandIds = [];

       if (in_array($role, [User::ROLE_PRANTA_ADMIN, User::ROLE_KENDREEYA_ADMIN], true)) {
            $scopeAll = (bool) $this->request->getPost('prant_scope_all');
            $prantIds = array_values(array_unique(array_map('intval', (array) $this->request->getPost('prant_ids'))));
            $prantId  = $prantIds[0] ?? $prantId;
        } elseif ($role === User::ROLE_SUB_ADMIN) {
            $scopeAll = (bool) $this->request->getPost('jila_scope_all');
            $jilaIds  = array_values(array_unique(array_map('intval', (array) $this->request->getPost('jila_ids'))));
            $jilaId   = $jilaIds[0] ?? $jilaId;
        } elseif ($role === User::ROLE_PRAKHAND_ADMIN) {
            $scopeAll    = (bool) $this->request->getPost('prakhand_scope_all');
            $prakhandIds = array_values(array_unique(array_map('intval', (array) $this->request->getPost('prakhand_ids'))));
            $prakhandId  = $prakhandIds[0] ?? $prakhandId;
        }

        return [$prantId, $jilaId, $prakhandId, $scopeAll, $prantIds, $jilaIds, $prakhandIds];
    }

    /**
     * A role below Pranta Admin needs its matching location actually set,
     * or it silently ends up scoped to nothing (WHERE jila_id = NULL never
     * matches a real row) — confusing to debug from "why can't this admin
     * see anything" rather than a clear error at creation time. A role
     * with a multi-select tier of its own satisfies this via "All" or at
     * least one selected id instead of a single value.
     */
    private function locationErrorFor(
        string $role,
        ?int $prantId,
        ?int $jilaId,
        ?int $prakhandId,
        bool $scopeAll,
        array $prantIds,
        array $jilaIds,
        array $prakhandIds
    ): ?string {
        if ($role === User::ROLE_SUPER_ADMIN) {
            return null;
        }

    // $prantIds/$jilaIds are only populated when the actor has a full
        // multi-select field to submit from (Super Admin creating this
        // role's own tier directly, or a higher actor assigning a role
        // whose own tier matches theirs — see resolveLocationFields()).
        // A Kendreeya Admin creating a Pranta Admin instead picks ONE of
        // their own already-validated Prants via the single-picker path,
        // which only ever resolves $prantId — so either form counts here.
        if (in_array($role, [User::ROLE_PRANTA_ADMIN, User::ROLE_KENDREEYA_ADMIN], true)) {
            return ($scopeAll || $prantIds || $prantId) ? null : 'Select at least one Prant, or choose All Prants.';
        }

        if (! $prantId) {
            return 'Select a Prant for this role.';
        }

        if ($role === User::ROLE_SUB_ADMIN) {
            return ($scopeAll || $jilaIds || $jilaId) ? null : 'Select at least one Jila, or choose All Jilas.';
        }

        if ($role === User::ROLE_PRAKHAND_ADMIN && ! $jilaId) {
            return 'Select a Jila for this role.';
        }

        if ($role === User::ROLE_PRAKHAND_ADMIN) {
            return ($scopeAll || $prakhandIds) ? null : 'Select at least one Prakhand, or choose All Prakhands.';
        }

        return null;
    }

    /**
     * Keeps each junction table in sync with the target's current role —
     * clearing the other two tiers' rows too, so a role change (e.g. Jila
     * Admin demoted to Karyakarta) doesn't leave stale scope rows behind.
     */
    private function persistLocationScopes(string $role, int $userId, array $prantIds, array $jilaIds, array $prakhandIds): void
    {
        // (new UserPrantScopeModel())->replaceFor($userId, $role === User::ROLE_PRANTA_ADMIN ? $prantIds : []);
        // (new UserJilaScopeModel())->replaceFor($userId, $role === User::ROLE_SUB_ADMIN ? $jilaIds : []);
         (new UserPrantScopeModel())->replaceFor($userId, in_array($role, [User::ROLE_PRANTA_ADMIN, User::ROLE_KENDREEYA_ADMIN], true) ? $prantIds : []);
         (new UserJilaScopeModel())->replaceFor($userId, $role === User::ROLE_SUB_ADMIN ? $jilaIds : []);
        (new UserPrakhandScopeModel())->replaceFor($userId, $role === User::ROLE_PRAKHAND_ADMIN ? $prakhandIds : []);
    }

    public function create()
    {
        $actor           = $this->currentUser();
        $manageableRoles = $this->manageableRoles($actor);
        $role            = (string) $this->request->getPost('role');
        $rules = [
            'name'  => 'required|min_length[2]|max_length[150]',
            'phone' => 'required|regex_match[/^[6-9][0-9]{9}$/]|is_unique[users.phone]',
            'role'  => 'required|in_list[' . implode(',', $manageableRoles) . ']',
        ];
         if ($role === User::ROLE_KARYAKARTA) {
            $rules['dob']  = 'required|valid_date[Y-m-d]';
            $rules['ayam'] = 'required|in_list[' . implode(',', self::AYAMS) . ']';
        }
        if (! $this->validate($rules)) {
            // withInput() so old('draft_id') survives this redirect — the
            // New User form's autosave then resumes from localStorage
            // under that same id, recovering everything the admin typed
            // instead of losing it to this round trip.
            return redirect()->to('/admin/users')->withInput()->with('errors', $this->validator->getErrors());
        }

        [$prantId, $jilaId, $prakhandId, $scopeAll, $prantIds, $jilaIds, $prakhandIds] = $this->resolveLocationFields($actor);
        $role = (string) $this->request->getPost('role');

        if ($error = $this->locationErrorFor($role, $prantId, $jilaId, $prakhandId, $scopeAll, $prantIds, $jilaIds, $prakhandIds)) {
            return redirect()->to('/admin/users')->withInput()->with('error', $error);
        }

        // An admin can type a specific password for the account instead of
        // getting a random one — useful when handing credentials over in
        // person. A password the admin chose is treated as the account's
        // real one (no forced change), same as if they'd set it via Reset
        // password later; a generated one is still a genuine one-time temp
        // credential, so first login still forces a change.
        $adminPassword = trim((string) $this->request->getPost('password'));
        if ($adminPassword !== '' && strlen($adminPassword) < 6) {
            return redirect()->to('/admin/users/new')->withInput()->with('error', 'Password must be at least 6 characters.');
        }
        $tempPassword = $adminPassword !== '' ? $adminPassword : bin2hex(random_bytes(5));
        $phone        = preg_replace('/\D/', '', $this->request->getPost('phone'));

        $userModel = new UserModel();
        $id = $userModel->insert([
            'name'                => $this->request->getPost('name'),
            'phone'               => $phone,
            'password_hash'       => password_hash($tempPassword, PASSWORD_DEFAULT),
            'password_plain'      => Vault::encrypt($tempPassword),
            'role'                => $role,
            'prant_id'            => $prantId,
            'jila_id'             => $jilaId,
            'prakhand_id'         => $prakhandId,
            'scope_all'           => $scopeAll,
            'address'             => $this->request->getPost('address') ?: null,
            'aadhar_number'       => $this->request->getPost('aadhar_number') ?: null,
            'email'               => $this->request->getPost('email') ?: null,
            'profession'          => $this->request->getPost('profession') ?: null,
            'dob'                 => $this->request->getPost('dob') ?: null,
            'ayam'                => $this->request->getPost('ayam') ?: null,
            'gender'              => $this->request->getPost('gender') ?: null,
            'status'              => 'active',
            'must_reset_password' => $adminPassword === '',
            'created_by'          => $actor->id,
        ], true);

        // insert() returns false on a model-level validation failure (or
        // the role value being rejected outright by the database — e.g. a
        // users.role ENUM that hasn't been widened for a newly added role
        // yet) without throwing. Proceeding past that with (int) false =
        // 0 used to crash persistLocationScopes() with a confusing
        // foreign-key error on the scope tables instead of surfacing
        // what actually went wrong.
        if (! $id) {
            return redirect()->to('/admin/users/new')->withInput()->with('errors', $userModel->errors() ?: ['Could not create this account — please check the details and try again.']);
        }

        $this->persistLocationScopes($role, (int) $id, $prantIds, $jilaIds, $prakhandIds);

        $draftId = trim((string) $this->request->getPost('draft_id'));
        if ($draftId !== '') {
            (new UserDraftModel())->scopedTo($actor)->delete($draftId);
        }

        (new Msg91Sms())->sendKaryakartaCredentials($phone, $this->request->getPost('name'), $tempPassword);
        (new AuditLogModel())->record($actor->id, 'user_created', 'user', $id, ['role' => $role]);

        // Shown once here for convenience, and also retrievable afterward
        // from this user's "View password" action — any admin who manages
        // this account (same scoping as Reset password) can look it up
        // again later without having to reset it.
        return redirect()->to('/admin/users')
            ->with('success', "Account created and credentials sent by SMS. Temporary password: {$tempPassword}");
    }

    public function showEdit($id)
    {
        $id = (int) $id;

        $actor  = $this->currentUser();
        $target = $this->manageableTarget($actor, $id);
        if (! $target  || ! $this->isOwnCreation($actor, $target)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
         $prants = (new PrantModel())->orderBy('name', 'ASC')->findAll();

        return view('users/edit', [
            'title'           => 'Edit User',
            'target'          => $target,
            'actor'           => $actor,
            'roleLabels'      => User::ROLE_LABELS,
            'manageableRoles' => $this->manageableRoles($actor),
            'hasGlobalAccess' => $actor->hasGlobalAccess(),
            'prants'          => $prants,
            'actorPrants'     => $this->actorPrantOptions($actor, $prants),
            'jilas'           => $target->prant_id ? (new JilaModel())->forPrant($target->prant_id) : [],
            'prakhands'       => $target->jila_id ? (new PrakhandModel())->forJila($target->jila_id) : [],
            // Falls back to the target's legacy single prant_id/jila_id/
            // prakhand_id when they have no junction rows yet — otherwise
            // every admin edited before multi-select existed would open
            // this form and see their real, still-in-effect assignment
            // rendered as if it had been cleared.
            'targetPrantIds'    => \App\Libraries\Rbac\LocationScope::prantIds($target) ?? [],
            'targetJilaIds'     => \App\Libraries\Rbac\LocationScope::jilaIds($target) ?? [],
            'targetPrakhandIds' => \App\Libraries\Rbac\LocationScope::prakhandIds($target) ?? [],
            'ayams'       => self::AYAMS,
        ]);
    }

    public function update($id)
    {
        $id = (int) $id;

        $actor  = $this->currentUser();
    
        $target = $this->manageableTarget($actor, $id);
        if (! $target || ! $this->isOwnCreation($actor, $target)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $manageableRoles = $this->manageableRoles($actor);
       // $role = $actor->role;
       $role = (string) $this->request->getPost('role'); 
       $rules = [
            'name'  => 'required|min_length[2]|max_length[150]',
            'phone' => "required|regex_match[/^[6-9][0-9]{9}\$/]|is_unique[users.phone,id,{$id}]",
            'role'  => 'required|in_list[' . implode(',', $manageableRoles) . ']',
        ];
        if ($role === User::ROLE_KARYAKARTA || $role === User::ROLE_SUB_ADMIN || $role === User::ROLE_PRAKHAND_ADMIN) {
            $rules['dob']  = 'required|valid_date[Y-m-d]';
            $rules['ayam'] = 'required|in_list[' . implode(',', self::AYAMS) . ']';
        }
        if (! $this->validate($rules)) {
            return redirect()->to('/admin/users/' . $id . '/edit')->with('errors', $this->validator->getErrors());
        }

        [$prantId, $jilaId, $prakhandId, $scopeAll, $prantIds, $jilaIds, $prakhandIds] = $this->resolveLocationFields($actor);
        $role = (string) $this->request->getPost('role');

        if ($error = $this->locationErrorFor($role, $prantId, $jilaId, $prakhandId, $scopeAll, $prantIds, $jilaIds, $prakhandIds)) {
            return redirect()->to('/admin/users/' . $id . '/edit')->with('error', $error);
        }

        // skipValidation: we've already run the equivalent checks above with
        // the real $id interpolated. The model's own is_unique[...,{id}]
        // rule needs 'id' in the data AND its own validation rule to
        // resolve that placeholder (CI4 throws otherwise) — simplest to
        // just rely on the manual validation already done and skip the
        // model's redundant, placeholder-fragile pass.
        $userModel = (new UserModel())->skipValidation(true);
        $updated   = $userModel->update($id, [
            'name'          => $this->request->getPost('name'),
            'phone'         => preg_replace('/\D/', '', $this->request->getPost('phone')),
            'role'          => $role,
            'prant_id'      => $prantId,
            'jila_id'       => $jilaId,
            'prakhand_id'   => $prakhandId,
            'scope_all'     => $scopeAll,
            'address'       => $this->request->getPost('address') ?: null,
            'aadhar_number' => $this->request->getPost('aadhar_number') ?: null,
            'email'         => $this->request->getPost('email') ?: null,
            'profession'    => $this->request->getPost('profession') ?: null,
            'dob'           => $this->request->getPost('dob') ?: null,
            'ayam'          => $this->request->getPost('ayam') ?: null,
            'gender'        => $this->request->getPost('gender') ?: null,
        ]);

        if (! $updated) {
            return redirect()->to('/admin/users/' . $id . '/edit')->with('errors', $userModel->errors());
        }

        $this->persistLocationScopes($role, $id, $prantIds, $jilaIds, $prakhandIds);

        (new AuditLogModel())->record($actor->id, 'user_updated', 'user', $id);

        return redirect()->to('/admin/users')->with('success', 'User updated.');
    }

    public function toggleBlock($id)
    {
        $id = (int) $id;

        $actor  = $this->currentUser();
        $target = $this->manageableTarget($actor, $id);
        if (! $target || ! $this->isOwnCreation($actor, $target)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $newStatus = $target->status === 'blocked' ? 'active' : 'blocked';
        (new UserModel())->update($id, ['status' => $newStatus]);
        (new AuditLogModel())->record($actor->id, 'user_' . $newStatus, 'user', $id);

        return redirect()->to('/admin/users')->with('success', 'User ' . ($newStatus === 'blocked' ? 'blocked' : 'unblocked') . '.');
    }

    public function resetPassword($id)
    {
        $id = (int) $id;

        $actor  = $this->currentUser();
        $target = $this->manageableTarget($actor, $id);
        if (! $target) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $adminPassword = trim((string) $this->request->getPost('password'));
        if ($adminPassword !== '' && strlen($adminPassword) < 6) {
            return redirect()->to('/admin/users/' . $id . '/password')->with('error', 'Password must be at least 6 characters.');
        }
        $tempPassword = $adminPassword !== '' ? $adminPassword : bin2hex(random_bytes(5));

        (new UserModel())->update($id, [
            'password_hash'       => password_hash($tempPassword, PASSWORD_DEFAULT),
            'password_plain'      => Vault::encrypt($tempPassword),
            'must_reset_password' => $adminPassword === '',
        ]);

        (new Msg91Sms())->sendKaryakartaCredentials($target->phone, $target->name, $tempPassword);
        (new AuditLogModel())->record($actor->id, 'user_password_reset_by_admin', 'user', $id);

        return redirect()->to('/admin/users/' . $id . '/password')
            ->with('success', "New credentials sent by SMS. Temporary password: {$tempPassword}");
    }

    /**
     * Reveals the account's current password — scoped exactly like Reset
     * password (same manageableTarget() check: the actor's hierarchy must
     * cover this account). Returns nothing once the account holder has set
     * their own password since (password_plain is cleared at that point in
     * AccountController::setPassword() and AuthController::submitNewPassword()) —
     * that one is theirs, not visible here.
     */
    public function viewPassword($id)
    {
        $id = (int) $id;

        $actor  = $this->currentUser();
        $target = $this->manageableTarget($actor, $id);
        if (! $target) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $password = Vault::decrypt($target->password_plain);
        (new AuditLogModel())->record($actor->id, 'user_password_viewed', 'user', $id);

        return view('users/password', [
            'title'    => 'View Password',
            'target'   => $target,
            'password' => $password,
        ]);
    }

    /**
     * Autosaves an in-progress New User form as the admin types — same
     * pattern as EnrolmentController::saveDraft(): the client generates
     * draft_id (a UUID) itself and keeps saving under the same id, so this
     * is always an upsert. Best-effort: the client already wrote the same
     * payload to localStorage first, so a failure here (offline, a dropped
     * request) just means the draft isn't in the resume list yet, not that
     * it's lost.
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

        $actor    = $this->currentUser();
        $model    = new UserDraftModel();
        $existing = $model->find($id);
        if ($existing && (int) $existing['created_by_user_id'] !== $actor->id && ! $actor->hasGlobalAccess()) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->save([
            'id'                 => $id,
            'created_by_user_id' => $existing['created_by_user_id'] ?? $actor->id,
            'name'               => trim((string) ($payload['name'] ?? '')) ?: null,
            'role'               => $payload['role'] ?? null,
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
        $draft = (new UserDraftModel())->scopedTo($this->currentUser())->find((string) $id);
        if (! $draft) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->setJSON(['payload' => json_decode($draft['payload'], true)]);
    }

    /** Discards a pending New User draft — never started, or no longer wanted. */
    public function deleteDraft($id)
    {
        $model = new UserDraftModel();
        $draft = $model->scopedTo($this->currentUser())->find((string) $id);
        if ($draft) {
            $model->delete($draft['id']);
        }

        return redirect()->to('/admin/users/drafts')->with('success', 'Draft discarded.');
    }
}
