<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Request;
use Middleware;

class AuthMiddleware extends Middleware
{
    public function handle(Request $request, callable $next)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('HX-Retarget: #main-content');
            header('HX-Reswap: innerHTML');
            header('HX-Trigger: auth-changed');
            http_response_code(401);
            echo '<div hx-get="/views/login/index.html" hx-trigger="load" hx-swap="outerHTML"></div>';
            exit;
        }

        return $next($request);
    }
}
