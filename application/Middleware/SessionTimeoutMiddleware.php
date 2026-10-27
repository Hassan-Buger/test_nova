<?php

namespace Application\Middleware;

use Application\Config\App;
use Application\Core\Request;
use Application\Core\Response;
use Application\Core\Session;

class SessionTimeoutMiddleware
{
    public function handle(Request $request, Response $response, array $args = []): bool
    {
        $lastActivity = Session::get('last_activity');
        $timeoutSeconds = App::get('session_timeout', 900);

        if ($lastActivity && (time() - $lastActivity > $timeoutSeconds)) {
            Session::destroy();
            Session::start();
            Session::setFlash('error', 'Your session has expired due to inactivity. Please sign in again.');
            $response->redirect('/login');
            return false;
        }

        Session::set('last_activity', time());
        return true;
    }
}
