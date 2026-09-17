<?php

namespace Application\Controllers\Client;

use Application\Core\Controller;
use Application\Core\Request;
use Application\Core\Response;
use Application\Core\Session;
use Application\Models\SignatureRequest;

class SignatureController extends Controller
{
    /**
     * List all pending signature requests for the logged-in client.
     */
    public function index(Request $request, Response $response): void
    {
        $userId = (int)Session::get('user_id');
        $sigReqModel = new SignatureRequest();
        $pending = $sigReqModel->getPendingForClientUser($userId);

        $this->render('client/signatures/index', [
            'pageTitle' => 'Pending Signatures',
            'pending'   => $pending,
        ], 'main');
    }
}
