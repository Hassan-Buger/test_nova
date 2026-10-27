<?php

namespace Application\Middleware;

use Application\Core\Request;
use Application\Core\Response;
use Application\Core\Session;
use Application\Models\User;

class AuthMiddleware
{
    public function handle(Request $request, Response $response, array $args = []): bool
    {
        $userId = (int)Session::get('user_id', 0);
        if ($userId <= 0) {
            $response->setHeader('X-Auth-Debug', 'no_user_id_sess_' . session_id());
            Session::setFlash('error', 'Please sign in to access your portal.');
            $response->redirect('/login');
            return false;
        }

        $user = (new User())->findById($userId);
        if (!$user) {
            $response->setHeader('X-Auth-Debug', 'user_not_found_id_' . $userId);
            Session::destroy();
            Session::start();
            Session::setFlash('error', 'User record not found. Please sign in again.');
            $response->redirect('/login');
            return false;
        }

        if ($user['status'] !== 'active') {
            $response->setHeader('X-Auth-Debug', 'user_inactive_' . $user['status']);
            Session::destroy();
            Session::start();
            Session::setFlash('error', 'Account is not active.');
            $response->redirect('/login');
            return false;
        }

        $authHash = hash('sha256', (string)$user['password_hash']);
        $sessionAuthHash = (string)Session::get('auth_hash', '');
        if ($sessionAuthHash !== '' && !hash_equals($sessionAuthHash, $authHash)) {
            $response->setHeader('X-Auth-Debug', 'hash_mismatch:sess=' . substr($sessionAuthHash, 0, 6) . ',db=' . substr($authHash, 0, 6));
            Session::destroy();
            Session::start();
            Session::setFlash('error', 'Your account or sign-in credentials changed. Please sign in again.');
            $response->redirect('/login');
            return false;
        }

        if ($sessionAuthHash === '') Session::set('auth_hash', $authHash);
        Session::set('role', (string)$user['role']);
        return true;
    }
}
