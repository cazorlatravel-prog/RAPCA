<?php
/**
 * RAPCA - Sistema de Autenticación
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function login(string $email, string $password): array|false
{
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT * FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1"
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_name']  = $user['nombre'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_rol']   = $user['rol'];

    $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id")
        ->execute([':id' => $user['id']]);

    return $user;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) return null;
    return [
        'id'     => $_SESSION['user_id'],
        'nombre' => $_SESSION['user_name'],
        'email'  => $_SESSION['user_email'],
        'rol'    => $_SESSION['user_rol'],
    ];
}

function requireAuth(string $loginUrl = '/public/login.php'): array
{
    if (!isLoggedIn()) {
        header('Location: ' . $loginUrl);
        exit;
    }
    return currentUser();
}

function requireRole(string|array $roles, string $loginUrl = '/public/login.php'): array
{
    $user = requireAuth($loginUrl);
    if (is_string($roles)) $roles = [$roles];

    if (!in_array($user['rol'], $roles, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><body><h1>403 - Acceso denegado</h1><p>No tienes permisos para acceder a esta sección.</p></body></html>';
        exit;
    }
    return $user;
}

function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrf(): bool
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(csrfToken(), $token);
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Comprobar si el usuario actual es superadmin.
 */
function isSuperAdmin(): bool
{
    return ($_SESSION['user_rol'] ?? '') === 'superadmin';
}

/**
 * Comprobar si el usuario actual es admin (solo lectura).
 * Los admins pueden ver todo pero no modificar datos.
 */
function isAdmin(): bool
{
    return ($_SESSION['user_rol'] ?? '') === 'admin';
}

/**
 * Comprobar si el rol actual es de solo lectura (admin).
 * Superadmin y operadores pueden escribir, admin no.
 */
function isReadOnly(): bool
{
    return isAdmin();
}

/**
 * Comprobar si el usuario puede realizar operaciones de escritura.
 * Superadmin: sí (todo). Operador: sí (su ámbito). Admin: no.
 */
function canWrite(): bool
{
    $rol = $_SESSION['user_rol'] ?? '';
    return in_array($rol, ['superadmin', 'operador'], true);
}

/**
 * Obtener los IDs de infraestructuras a las que un operador tiene acceso.
 * Superadmin y admin ven todas las infraestructuras.
 */
function getOperadorInfraIds(int $usuarioId): array
{
    $pdo = getDB();

    // Superadmin y admin ven todas
    $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id = :uid");
    $stmt->execute([':uid' => $usuarioId]);
    $user = $stmt->fetch();

    if ($user && in_array($user['rol'], ['superadmin', 'admin'], true)) {
        return array_column(
            $pdo->query("SELECT id FROM infraestructuras WHERE activa = 1")->fetchAll(),
            'id'
        );
    }

    $stmt = $pdo->prepare(
        "SELECT infraestructura_id FROM operario_infraestructura WHERE usuario_id = :uid"
    );
    $stmt->execute([':uid' => $usuarioId]);
    return array_column($stmt->fetchAll(), 'infraestructura_id');
}
