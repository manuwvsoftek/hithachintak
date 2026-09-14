<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Blocks access to any route it's attached to unless a user is logged in.
 * Redirects browsers to the login page; returns 401 JSON for AJAX/API calls.
 */
class AuthGuard implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('user_id')) {
            if ($request->isAJAX() || str_starts_with($request->getPath(), 'api/')) {
                return service('response')->setJSON(['error' => 'Not authenticated'])->setStatusCode(401);
            }

            $session->setFlashdata('redirect_after_login', current_url());

            return redirect()->to('/login');
        }

        $status = $session->get('user_status');
        if ($status === 'blocked') {
            $session->destroy();

            return redirect()->to('/login')->with('error', 'Your account has been blocked. Contact your administrator.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
