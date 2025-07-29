<?php

namespace Litesupabase\Controllers;

use Curl\Curl;
use Exception;
use Litesupabase\Library\GoogleAuth;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Litesupabase\Base;
use Litesupabase\Database\UserRepository;
use Firebase\JWT\JWT;
use Throwable;

class CallbackController extends Base
{
    /**
     * @throws Throwable
     */
    public function action(Request $request, Response $response): Response
    {
        $id = $request->getAttribute('id');
        if (!in_array($id,['google','github'])) {
            return $this->respondWithHtml($response, 404);
        }
        $this->session['csrf_token'] = $this->generateToken();
        $html = $this->phpRenderer->fetch('callback.phtml', ['vendor' => $id, 'csrfToken'=>$this->session['csrf_token']]);
        return $this->respondWithHtml($response, $html);
    }
}
