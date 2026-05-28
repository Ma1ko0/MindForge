<?php

declare(strict_types=1);

use App\Response;
use App\Router;
use App\Request;
use App\UserController;
use App\TodoItemController;
use App\DashboardController;
use App\CalendarController;
use App\NoteController;
use App\WorkflowController;
use App\ProjectController;
use App\SearchController;
use App\TagController;
use App\HabitController;
use App\Middleware\AuthMiddleware;
use App\Middleware\JsonContentTypeMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\LoggingMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SessionMiddleware;
use App\Middleware\ValidationMiddleware;

$pdo = (new Database())->getConnection();
$router = new Router($pdo);

// REST API routes group (JSON responses)
$router->group(['prefix' => '/api', 'middleware' => [LoggingMiddleware::class, RateLimitMiddleware::class, JsonContentTypeMiddleware::class, SecurityHeadersMiddleware::class]], function (Router $router) {
    $router->get('/users/(\d+)', [UserController::class, 'getUserDataByID'])->register();
});

// Public auth routes (HTML responses for HTMX)
$router->group(['middleware' => [SessionMiddleware::class, ValidationMiddleware::class]], function (Router $router) {
    $router->post('/register', [UserController::class, 'registerHtml'])->register();
    $router->post('/login', [UserController::class, 'loginHtml'])->register();
    $router->post('/logout', [UserController::class, 'logoutHtml'])->register();
    $router->get('/auth/me', [UserController::class, 'meHtml'])->register();
});

// Protected web routes (HTML responses for HTMX)
$router->group(['middleware' => [SessionMiddleware::class, AuthMiddleware::class, ValidationMiddleware::class]], function (Router $router) {
    $router->get('/dashboard', [DashboardController::class, 'indexHtml'])->register();
    $router->get('/dashboard/stats', [DashboardController::class, 'statsHtml'])->register();
    $router->get('/dashboard/recent', [DashboardController::class, 'recentHtml'])->register();
    $router->post('/dashboard/quick-add', [DashboardController::class, 'quickAddHtml'])->register();
    $router->get('/dashboard/today', [DashboardController::class, 'todayHtml'])->register();
    $router->patch('/dashboard/today/(\d+)/toggle', [DashboardController::class, 'toggleTodayHtml'])->register();

    $router->get('/calendar', [CalendarController::class, 'indexHtml'])->register();
    $router->get('/calendar/(\d{4})/(\d{1,2})', [CalendarController::class, 'indexHtml'])->register();

    $router->get('/workflow', [WorkflowController::class, 'indexHtml'])->register();
    $router->get('/workflow/day/(\d{4}-\d{2}-\d{2})', [WorkflowController::class, 'dayHtml'])->register();
    $router->post('/workflow/blocks', [WorkflowController::class, 'createBlockHtml'])->register();
    $router->get('/workflow/blocks/(\d+)/edit', [WorkflowController::class, 'editBlockHtml'])->register();
    $router->get('/workflow/blocks/(\d+)/cancel', [WorkflowController::class, 'cancelEditBlockHtml'])->register();
    $router->put('/workflow/blocks/(\d+)', [WorkflowController::class, 'updateBlockHtml'])->register();
    $router->patch('/workflow/blocks/(\d+)/toggle', [WorkflowController::class, 'toggleBlockHtml'])->register();
    $router->delete('/workflow/blocks/(\d+)', [WorkflowController::class, 'deleteBlockHtml'])->register();
    $router->get('/workflow/templates', [WorkflowController::class, 'templatesHtml'])->register();
    $router->post('/workflow/templates', [WorkflowController::class, 'createTemplateHtml'])->register();
    $router->post('/workflow/templates/apply', [WorkflowController::class, 'applyTemplateHtml'])->register();
    $router->get('/workflow/templates/(\d+)', [WorkflowController::class, 'templateHtml'])->register();
    $router->delete('/workflow/templates/(\d+)', [WorkflowController::class, 'deleteTemplateHtml'])->register();
    $router->post('/workflow/templates/(\d+)/blocks', [WorkflowController::class, 'addTemplateBlockHtml'])->register();
    $router->delete('/workflow/templates/(\d+)/blocks/(\d+)', [WorkflowController::class, 'deleteTemplateBlockHtml'])->register();

    $router->get('/daily', [NoteController::class, 'dailyHtml'])->register();
    $router->get('/notes', [NoteController::class, 'indexHtml'])->register();
    $router->get('/notes/search', [NoteController::class, 'searchHtml'])->register();
    $router->post('/notes', [NoteController::class, 'createHtml'])->register();
    $router->get('/notes/(\d+)', [NoteController::class, 'viewHtml'])->register();
    $router->get('/notes/(\d+)/edit', [NoteController::class, 'editHtml'])->register();
    $router->put('/notes/(\d+)', [NoteController::class, 'updateHtml'])->register();
    $router->delete('/notes/(\d+)', [NoteController::class, 'deleteHtml'])->register();

    $router->get('/search', [SearchController::class, 'searchHtml'])->register();

    $router->get('/tags', [TagController::class, 'indexHtml'])->register();
    $router->get('/tags/([^/]+)', [TagController::class, 'viewHtml'])->register();

    $router->get('/habits', [HabitController::class, 'indexHtml'])->register();
    $router->get('/habits/widget', [HabitController::class, 'widgetHtml'])->register();
    $router->post('/habits', [HabitController::class, 'createHtml'])->register();
    $router->get('/habits/(\d+)/edit', [HabitController::class, 'editHtml'])->register();
    $router->get('/habits/(\d+)/cancel', [HabitController::class, 'cancelEditHtml'])->register();
    $router->put('/habits/(\d+)', [HabitController::class, 'updateHtml'])->register();
    $router->patch('/habits/(\d+)/check', [HabitController::class, 'checkHtml'])->register();
    $router->patch('/habits/(\d+)/uncheck', [HabitController::class, 'uncheckHtml'])->register();
    $router->patch('/habits/(\d+)/toggle-active', [HabitController::class, 'toggleActiveHtml'])->register();
    $router->delete('/habits/(\d+)', [HabitController::class, 'deleteHtml'])->register();

    $router->get('/projects', [ProjectController::class, 'indexHtml'])->register();
    $router->post('/projects', [ProjectController::class, 'createHtml'])->register();
    $router->get('/projects/(\d+)', [ProjectController::class, 'viewHtml'])->register();
    $router->get('/projects/(\d+)/edit', [ProjectController::class, 'editHtml'])->register();
    $router->put('/projects/(\d+)', [ProjectController::class, 'updateHtml'])->register();
    $router->delete('/projects/(\d+)', [ProjectController::class, 'deleteHtml'])->register();
    $router->post('/projects/(\d+)/lists', [ProjectController::class, 'createListHtml'])->register();
    $router->post('/projects/(\d+)/notes', [ProjectController::class, 'createNoteHtml'])->register();

    $router->get('/todo', [TodoItemController::class, 'pageHtml'])->register();
    $router->get('/todo/lists', [TodoItemController::class, 'getTodoListsHtml'])->register();
    $router->post('/todo/lists', [TodoItemController::class, 'createTodoListHtml'])->register();
    $router->get('/todo/lists/(\d+)', [TodoItemController::class, 'getTodoListHtml'])->register();
    $router->post('/todo/lists/(\d+)/items', [TodoItemController::class, 'createTodoItemHtml'])->register();
    $router->patch('/todo/lists/(\d+)/items/(\d+)', [TodoItemController::class, 'toggleTodoItemHtml'])->register();
    $router->delete('/todo/lists/(\d+)', [TodoItemController::class, 'deleteTodoListHtml'])->register();
    $router->delete('/todo/lists/(\d+)/items/(\d+)', [TodoItemController::class, 'deleteTodoItemHtml'])->register();
});

// OpenAPI docs endpoint (accessible without /api prefix)
$router->get('/openapi.yaml', function (Request $request) {
    header('Content-Type: application/x-yaml');
    echo file_get_contents(__DIR__ . '/../../openapi.yaml');
})->register();

// CORS preflight
$router->options('/(.*)', function (Request $request) {
    new Response()->empty();
})->register();

$request = new Request();
$router->dispatch($request);
