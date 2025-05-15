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

        if (!$this->isLogged()) {
            return $response->withHeader('Location', '/admin/login')->withStatus(302);
        }
        // Continue with the request
        return $handler->handle($request);
    }
}
