<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\RolePermissionModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Enforces the Roles & Permissions matrix server-side for a route.
 *
 * Usage in Routes.php: 'filter' => 'permission:Enrolments,Edit'
 * The first argument is the module name (must match role_permissions.module
 * exactly), the second is the minimum level required (None < Own < View <
 * Edit < Full). "Own" only guarantees the *route* is reachable — controllers
 * are still responsible for scoping the actual data query to the current
 * user (see the various *Model::scopedTo() helpers).
 */
class RequirePermission implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        $role    = $session->get('user_role');

        if (! $role) {
            return redirect()->to('/login');
        }

        [$module, $minLevel] = array_pad($arguments ?? [], 2, 'View');

        $model = new RolePermissionModel();
        if (! $model->atLeast($role, $module, $minLevel)) {
            return service('response')
                ->setStatusCode(403)
                ->setBody(view('errors/forbidden', ['module' => $module]));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
