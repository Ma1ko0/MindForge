<?php

declare(strict_types=1);

namespace App;

use App\User\UserNotFoundException;
use App\User\UserRepository;

class UserController extends Controller
{
    public function getUserDataByID(string $userId)
    {
        $userrepo = new UserRepository($this->pdo);
        $user = null;
        try {
            $user = $userrepo->getUserById($userId);
        } catch (UserNotFoundException) {
            $this->logger->logging("User not found", ERROR);
            $this->response->error("User not found", 404);
        }
        $this->logger->logging("User found", INFO);
        $this->response->success($user, 200);
    }

    public function registerHtml(): void
    {
        $username = trim((string)($this->request->getPostData('username') ?? ''));
        $email    = trim((string)($this->request->getPostData('email') ?? ''));
        $password = (string)($this->request->getPostData('password') ?? '');
        $confirm  = (string)($this->request->getPostData('passwordConfirm') ?? '');

        if ($username === '' || $email === '' || $password === '') {
            $this->response->text($this->alert('Bitte alle Felder ausfüllen.'), 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->response->text($this->alert('Ungültige E-Mail-Adresse.'), 400);
        }
        if (strlen($password) < 8) {
            $this->response->text($this->alert('Passwort muss mindestens 8 Zeichen lang sein.'), 400);
        }
        if ($password !== $confirm) {
            $this->response->text($this->alert('Passwörter stimmen nicht überein.'), 400);
        }

        $repo = new UserRepository($this->pdo);

        if ($repo->usernameExists($username)) {
            $this->response->text($this->alert('Benutzername ist bereits vergeben.'), 409);
        }
        if ($repo->emailExists($email)) {
            $this->response->text($this->alert('E-Mail-Adresse ist bereits registriert.'), 409);
        }

        $userId = 0;
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $userId = $repo->createUser($username, $email, $hash);
        } catch (\Throwable $ex) {
            $this->logger->logging('Register failed: ' . $ex->getMessage(), ERROR);
            $this->response->text($this->alert('Registrierung fehlgeschlagen.'), 500);
        }

        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = strtolower($username);
        session_regenerate_id(true);

        $this->redirectAfterAuth('/dashboard');
    }

    public function loginHtml(): void
    {
        $email    = trim((string)($this->request->getPostData('email') ?? ''));
        $password = (string)($this->request->getPostData('password') ?? '');

        if ($email === '' || $password === '') {
            $this->response->text($this->alert('Bitte E-Mail und Passwort eingeben.'), 400);
        }

        $repo = new UserRepository($this->pdo);
        try {
            $user = $repo->getUserByEmail($email);
        } catch (UserNotFoundException) {
            $this->response->text($this->alert('E-Mail oder Passwort falsch.'), 401);
            return;
        } catch (\InvalidArgumentException) {
            $this->response->text($this->alert('Ungültige E-Mail-Adresse.'), 400);
            return;
        }

        if (!password_verify($password, $user->getPasswordHash())) {
            $this->response->text($this->alert('E-Mail oder Passwort falsch.'), 401);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['username'] = $user->getUsername();

        $this->redirectAfterAuth('/dashboard');
    }

    public function logoutHtml(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();

        $this->redirectAfterAuth('/views/login/index.html');
    }

    private function redirectAfterAuth(string $path): void
    {
        header('HX-Retarget: #main-content');
        header('HX-Reswap: innerHTML');
        header('HX-Trigger: auth-changed');
        $body = '<div hx-get="' . htmlspecialchars($path) . '" hx-trigger="load" hx-swap="outerHTML"></div>';
        $this->response->text($body);
    }

    public function meHtml(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->response->text(
                '<button hx-get="/views/login/index.html" hx-swap="innerHTML" hx-target="#main-content" class="btn btn-outline-light w-100" type="button">Login</button>'
            );
        }

        $username = htmlspecialchars((string)($_SESSION['username'] ?? 'user'));
        $html  = '<div class="text-white-50 small mb-2 text-center">' . $username . '</div>';
        $html .= '<button hx-post="/logout" hx-swap="none" class="btn btn-outline-light w-100" type="button">Logout</button>';
        $this->response->text($html);
    }

    private function alert(string $message): string
    {
        return '<div class="alert alert-danger" role="alert">' . htmlspecialchars($message) . '</div>';
    }
}
