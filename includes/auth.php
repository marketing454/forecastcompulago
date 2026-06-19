<?php
require_once __DIR__ . '/config.php';

use App\Models\Usuario;

function attemptLogin(string $email, string $password): bool
{
    $usuario = Usuario::findByEmail($email);
    if ($usuario === null || !$usuario['activo']) {
        return false;
    }
    if (!password_verify($password, $usuario['password_hash'])) {
        return false;
    }
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_rol'] = $usuario['rol'];
    return true;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function currentUserId(): ?int
{
    return $_SESSION['usuario_id'] ?? null;
}

function currentUserNombre(): ?string
{
    return $_SESSION['usuario_nombre'] ?? null;
}

function currentUserRol(): ?string
{
    return $_SESSION['usuario_rol'] ?? null;
}

function requireLogin(): void
{
    if (currentUserId() === null) {
        header('Location: /login.php');
        exit;
    }
}

function requireRole(string $rol): void
{
    requireLogin();
    if (currentUserRol() !== $rol) {
        http_response_code(403);
        die('No tienes permiso para ver esta página.');
    }
}
