<?php

namespace Litesupabase\Admin;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Litesupabase\Base;

class AdminMiddleware extends Base
{

    public function __invoke(Request $request, RequestHandler $handler): \Psr\Http\Message\ResponseInterface
    {
        $response = new Response();

        if (!$this->isLogged() && !$this->isLoggedInKoonly($request)) {
            return $response->withHeader('Location', '/admin/login')->withStatus(302);
        }
        // Continue with the request
        return $handler->handle($request);
    }

    /**
     * @throws \Exception
     */
    public function isLoggedInKoonly(): bool
    {
        $cid = $_GET['cid']?$_GET['cid']:'';
        $token = $_GET['token']?$_GET['token']:'';
        if (empty($token)) return false;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.cloudkoonly.com/api/validateToken');
        curl_setopt($curl, CURLOPT_POST, true);
        $postData = ['cid'=>$cid, 'token' => $token];
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        curl_close($curl);
        if ($response === false) {
            echo 'cURL error: ' . curl_error($curl);
        } else {
            $result = json_decode($response, true);
            if (isset($result['statusCode']) && $result['statusCode']===200 && isset($result['data']['status']) && $result['data']['status']==='ok') {
                $this->session['email'] = 'admin';
                $this->session['sso_token'] = $this->generateToken();
                $this->writeSsoToken();
                return true;
            }
        }
        return false;
    }
}
