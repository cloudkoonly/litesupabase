<?php

namespace Litesupabase;

use DI\Attribute\Inject;
use Litesupabase\Library\Cache;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Litesupabase\Library\DB;
use Slim\Views\PhpRenderer;
use SlimSession\Helper;

abstract class Base
{
    #[Inject]
    protected DB $db;

    #[Inject]
    protected Cache $cache;
    #[Inject]
    protected LoggerInterface $logger;
    #[Inject]
    protected PhpRenderer $phpRenderer;

    #[Inject]
    public Helper $session;

    #[Inject('settings')]
    public array $settings;

    protected function respondWithJson(Response $response, $status=200, string $message="ok", array $data=[]): Response
    {
        $return = [
            "code"      => $status,
            "message"   => $message,
            "data"      => $data
        ];
        $json = json_encode($return, JSON_PRETTY_PRINT);
        $response->getBody()->write($json);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    protected function respondWithHtml(Response $response, $html = ''):Response
    {
        $response->getBody()->write($html);
        return $response;
    }

    public function isLogged():bool
    {
        return $this->session->exists('email');
    }
}