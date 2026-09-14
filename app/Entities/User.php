<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class User extends Entity
{
    public const ROLE_DEV_ADMIN    = 'dev_admin';
    public const ROLE_SUPER_ADMIN    = 'super_admin';
    public const ROLE_PRANTA_ADMIN   = 'pranta_admin';
    public const ROLE_SUB_ADMIN      = 'sub_admin';
    public const ROLE_PRAKHAND_ADMIN = 'prakhand_admin';
    public const ROLE_KARYAKARTA     = 'karyakarta';

    public const ROLE_LABELS = [
        self::ROLE_DEV_ADMIN      => 'Dev Admin',
        self::ROLE_SUPER_ADMIN    => 'Super Admin',
        self::ROLE_PRANTA_ADMIN   => 'Pranta Admin',
        self::ROLE_SUB_ADMIN      => 'Jila Admin',
        self::ROLE_PRAKHAND_ADMIN => 'Prakhand Admin',
        self::ROLE_KARYAKARTA     => 'Karyakarta',
    ];

    /**
     * Higher rank = more authority. Used to stop a role managing its peers
     * or superiors. Mirrors the location hierarchy one level deeper than
     * before: Prant -> Jila -> Prakhand -> the Karyakarta themself.
     *
     * Dev Admin sits above Super Admin here — it has every module Super
     * Admin has (see RolePermissionSeeder), plus Settings & Integrations
     * and Roles & Permissions on top — so outranks()/manageableRoles()
     * never let a Super Admin manage, block, or reassign the one Dev
     * Admin account through Users & Hierarchy, and UserModel::visibleTo()
     * hides that account from every role's user list outright (see its
     * own comment) — it is provisioned only via DevAdminSeeder, same as
     * Super Admin is provisioned only via DevSuperAdminSeeder.
     */
    public const ROLE_RANK = [
        self::ROLE_DEV_ADMIN      => 5,
        self::ROLE_SUPER_ADMIN    => 4,
        self::ROLE_PRANTA_ADMIN   => 3,
        self::ROLE_SUB_ADMIN      => 2,
        self::ROLE_PRAKHAND_ADMIN => 1,
        self::ROLE_KARYAKARTA     => 0,
    ];

    public static function rank(string $role): int
    {
        return self::ROLE_RANK[$role] ?? -1;
    }

    /** True if this user outranks $otherRole (strictly — peers can't manage each other). */
    public function outranks(string $otherRole): bool
    {
        return self::rank($this->attributes['role']) > self::rank($otherRole);
    }

    protected $casts = [
        'id'                  => 'integer',
        'prant_id'            => '?integer',
        'jila_id'             => '?integer',
        'prakhand_id'         => '?integer',
        'must_reset_password' => 'boolean',
        'scope_all'           => 'boolean',
    ];

    public function isSuperAdmin(): bool
    {
        return $this->attributes['role'] === self::ROLE_SUPER_ADMIN;
    }

    public function isDevAdmin(): bool
    {
        return $this->attributes['role'] === self::ROLE_DEV_ADMIN;
    }

    /**
     * Both of the two unscoped, all-India roles — used everywhere a
     * data-visibility bypass or a free-choice location picker currently
     * checks isSuperAdmin() alone, so Dev Admin gets the exact same
     * unrestricted access Super Admin has (plus Settings & Integrations
     * and Roles & Permissions, which are gated separately through the
     * permission matrix, not through this).
     */
    public function hasGlobalAccess(): bool
    {
        return $this->isSuperAdmin() || $this->isDevAdmin();
    }

    public function isBlocked(): bool
    {
        return $this->attributes['status'] === 'blocked';
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->attributes['role']] ?? $this->attributes['role'];
    }

    /**
     * The Prant scope this user's data access is confined to, or null for
     * Super Admin/Dev Admin (all-India / unscoped).
     */
    public function scopePrantId(): ?int
    {
        return $this->hasGlobalAccess() ? null : $this->attributes['prant_id'];
    }
}
