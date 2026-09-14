<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\User;
use App\Models\RolePermissionModel;
use App\Models\UserModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    protected $helpers = ['form', 'url', 'text', 'app'];

    protected ?User $currentUser = null;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $userId = session('user_id');
        if ($userId) {
            $this->currentUser = (new UserModel())->find($userId);
        }
    }

    protected function currentUser(): ?User
    {
        return $this->currentUser;
    }

    /**
     * True if the logged-in user's role has at least $minLevel access to
     * $module, per the Roles & Permissions matrix. Use in views to
     * show/hide actions — routes still enforce this server-side via the
     * `permission` filter, this is only for UI decisions.
     */
    protected function can(string $module, string $minLevel = 'View'): bool
    {
        if (! $this->currentUser) {
            return false;
        }

        return (new RolePermissionModel())->atLeast($this->currentUser->role, $module, $minLevel);
    }
}
