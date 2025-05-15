<?php
declare(strict_types=1);

use Litesupabase\Admin\AdminController;
use Litesupabase\Admin\AdminMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Litesupabase\Controllers\AuthController;
use Litesupabase\Middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Middleware\Session;

return function (App $app) {

    $app->get('[/]', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/home.html')->withStatus(302);
    });

    $app->group('/admin', function (RouteCollectorProxy $group) {
        $group->get('[/]', function (Request $request, Response $response) {
            return $response->withHeader('Location', '/admin/login')->withStatus(302);
        });
        $group->get('/login', [AdminController::class, 'login']);
        $group->post('/login', [AdminController::class, 'submitLogin']);
        $group->get('/logout', [AdminController::class, 'logout']);
        $group->get('/dashboard', [AdminController::class, 'dashboard'])->add(AdminMiddleware::class);
        $group->get('/auth', [AdminController::class, 'auth'])->add(AdminMiddleware::class);
        $group->get('/users', [AdminController::class, 'users'])->add(AdminMiddleware::class);
        $group->get('/user/{id}', [AdminController::class, 'getUser'])->add(AdminMiddleware::class);
        $group->put('/user/{id}', [AdminController::class, 'updateUser'])->add(AdminMiddleware::class);
        $group->delete('/user/{id}', [AdminController::class, 'deleteUser'])->add(AdminMiddleware::class);
        $group->get('/db', [AdminController::class, 'db'])->add(AdminMiddleware::class);
        $group->get('/storage', [AdminController::class, 'storage'])->add(AdminMiddleware::class);
        $group->get('/storage/info', [AdminController::class, 'storageInfo'])->add(AdminMiddleware::class);
        $group->get('/api-document', [AdminController::class, 'api'])->add(AdminMiddleware::class);
    })->add(Session::class);

    // API Routes
    $app->group('/api', function (RouteCollectorProxy $group) {
        // Auth routes
        $group->group('/auth', function (RouteCollectorProxy $group) {
            $group->post('/signup', [AuthController::class, 'signup']);
            $group->post('/login', [AuthController::class, 'login']);
            $group->post('/logout', [AuthController::class, 'logout'])->add(AuthMiddleware::class);
            $group->post('/refresh', [AuthController::class, 'refresh']);
            $group->post('/forgot', [AuthController::class, 'forgotPassword']);
            $group->get('/config', [AuthController::class, 'config']);
            $group->get('/user', [AuthController::class, 'getUser'])->add(AuthMiddleware::class);
            $group->get('/google/callback', [AuthController::class, 'googleCallback']);
            $group->get('/github/callback', [AuthController::class, 'githubCallback']);
        });
    });

    // Register CORS middleware
    $app->add(function ($request, $handler) {
        $response = $handler->handle($request);
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    });

    // Add OPTIONS route for CORS preflight requests
    $app->options('/{routes:.+}', function ($request, $handler) {
        return $handler->handle($request)
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    });
};
