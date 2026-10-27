<?php

namespace ApplicationMiddleware;

use ApplicationConfigApp;
use ApplicationCoreRequest;
use ApplicationCoreResponse;
use ApplicationCoreSession;

class SessionTimeoutMiddleware
{
    public function handle(Request $request, Response $response, array $args = []): bool
    {
        $lastActivity = Session::get('last_activity');
        $timeoutSeconds = App::get('session_timeout', 900);

        if ($lastActivity && (time() - $lastActivity > $timeoutSeconds)) {
            $response->setHeader('X-Auth-Debug', 'session_timeout: last=' . $lastActivity . ' now=' . time());
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
