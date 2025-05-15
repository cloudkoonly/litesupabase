<?php

namespace Litesupabase\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Litesupabase\Base;

class AuthMiddleware extends Base
{

    public function __invoke(Request $request, RequestHandler $handler): \Psr\Http\Message\ResponseInterface
    {
        $response = new Response();

        // Get token from header
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader)) {
            return $this->respondWithJson($response, 401, "No token provided");
        }

        // Extract token from Bearer header
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->respondWithJson($response, 401, "Invalid token format");
        }

        $token = $matches[1];

        try {
            // Verify token
            $accessSecretKey = $this->settings['JWT_ACCESS_SECRET'];
            $decoded = JWT::decode($token, new Key($accessSecretKey, $this->settings['ALGORITHM']));

            // Add user data to request attributes
            $request = $request->withAttribute('user', $decoded);

            // Continue with the request
            return $handler->handle($request);
        } catch (\Firebase\JWT\ExpiredException $e) {
            $this->logger->warning("Access token has expired: ".$e->getMessage());

            return $this->respondWithJson($response, 402, "Access token has expired");
        } catch (\Exception $e) {
            $this->logger->warning("Invalid access token: ".$e->getMessage());
            return $this->respondWithJson($response, 401, "Invalid access token");
        }
    }
}
