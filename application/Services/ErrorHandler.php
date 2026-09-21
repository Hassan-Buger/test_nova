<?php

namespace Application\Services;

use Application\Core\Request;
use Application\Core\Response;
use Application\Core\Session;
use Application\Exceptions\SystemSetupException;

final class ErrorHandler
{
    public const GENERAL_MESSAGE='We could not complete your request at this time. Please try again later or contact support if the problem continues.';
    public const CLIENT_DATA_MESSAGE='The client data operation could not be completed. Please try again later or contact support.';
    public const SETUP_MESSAGE='This feature is currently unavailable because the system setup is incomplete. Please contact the administrator.';

    public static function handle(\Throwable $error,Request $request,Response $response): void
    {
        self::report($error,$request);
        if (str_starts_with($request->getUri(), '/api/v1')) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, Idempotency-Key, If-Match');
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'INTERNAL_SERVER_ERROR',
                    'message' => $error->getMessage()
                ]
            ], 500);
            return;
        }
        $isClientData=str_starts_with($request->getUri(),'/staff/clients/import') || str_starts_with($request->getUri(),'/staff/directors/import') || $request->getUri()==='/staff/clients/export';
        $message=$error instanceof SystemSetupException?self::SETUP_MESSAGE:($isClientData?self::CLIENT_DATA_MESSAGE:self::GENERAL_MESSAGE);
        if($request->isAjax()){$response->json(['success'=>false,'message'=>$message],500);return;}
        if($isClientData && !headers_sent()){
            Session::setFlash('error',$message);
            $response->redirect('/staff/clients');
            return;
        }
        $response->setStatusCode(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Request unavailable</title></head><body style="margin:0;background:#eef4f1;font-family:system-ui,-apple-system,sans-serif;color:#213330;display:grid;place-items:center;min-height:100vh;padding:24px"><main style="max-width:560px;background:#fff;border-radius:24px;padding:34px;box-shadow:0 18px 48px -30px rgba(16,54,45,.55);text-align:center"><div style="font-size:36px">&#9888;</div><h1 style="font-size:22px;margin:12px 0">We could not complete that request</h1><p style="color:#61756e;line-height:1.6">'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p><a href="/" style="display:inline-block;margin-top:8px;background:#0d9488;color:#fff;text-decoration:none;padding:12px 18px;border-radius:12px;font-weight:700">Return safely</a></main></body></html>';
    }

    public static function report(\Throwable $error,?Request $request=null): void
    {
        $root=\Application\Core\Application::$ROOT_DIR ?: dirname(__DIR__,2);
        $directory=$root.'/storage/logs';
        if(!is_dir($directory))@mkdir($directory,0700,true);
        $record=sprintf("[%s] %s: %s in %s:%d\nURI: %s\nTrace:\n%s\n---\n",date('c'),get_class($error),$error->getMessage(),$error->getFile(),$error->getLine(),$request?->getUri()??'bootstrap',$error->getTraceAsString());
        if(is_dir($directory)&&is_writable($directory))@error_log($record,3,$directory.'/application.log');else error_log($record);
    }
}
