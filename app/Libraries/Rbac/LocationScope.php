<?php

declare(strict_types=1);

namespace App\Libraries\Rbac;

use App\Entities\User;
use App\Models\UserJilaScopeModel;
use App\Models\UserPrakhandScopeModel;
use App\Models\UserPrantScopeModel;

/**
 * The single place every scopedTo()/visibleTo() method asks "which Prant/
 * Jila/Prakhand ids can this actor see" — so the None-vs-set-vs-single
 * logic (see CreateUserLocationScopes) lives in exactly one spot instead
 * of being reimplemented per model.
 *
 * Each method returns null to mean "unrestricted at this tier" (the
 * caller should skip the where() entirely — the same shape scopedTo()
 * already uses for Super Admin/Dev Admin), or a non-empty list of ids to
 * whereIn() against.
 */
class LocationScope
{
    /** @return list<int>|null */
    public static function prantIds(User $actor): ?array
    {
        if ($actor->role !== User::ROLE_PRANTA_ADMIN) {
            return $actor->prant_id ? [$actor->prant_id] : null;
        }
        if ($actor->scope_all) {
            return null;
        }
        $ids = (new UserPrantScopeModel())->idsFor($actor->id);

        return $ids ?: ($actor->prant_id ? [$actor->prant_id] : null);
    }

    /** @return list<int>|null */
    public static function jilaIds(User $actor): ?array
    {
        if ($actor->role !== User::ROLE_SUB_ADMIN) {
            return $actor->jila_id ? [$actor->jila_id] : null;
        }
        if ($actor->scope_all) {
            return null;
        }
        $ids = (new UserJilaScopeModel())->idsFor($actor->id);

        return $ids ?: ($actor->jila_id ? [$actor->jila_id] : null);
    }

    /** @return list<int>|null */
    public static function prakhandIds(User $actor): ?array
    {
        if ($actor->role !== User::ROLE_PRAKHAND_ADMIN) {
            return $actor->prakhand_id ? [$actor->prakhand_id] : null;
        }
        if ($actor->scope_all) {
            return null;
        }
        $ids = (new UserPrakhandScopeModel())->idsFor($actor->id);

        return $ids ?: ($actor->prakhand_id ? [$actor->prakhand_id] : null);
    }
}
