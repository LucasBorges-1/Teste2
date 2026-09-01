<?php
require_once __DIR__ . '/../../includes/db.php';

/**
 * Autenticação do painel via cookie assinado (stateless).
 *
 * Por que não usar $_SESSION: na Vercel cada página do admin roda como uma
 * Serverless Function separada e isolada, então uma sessão criada no
 * container de login.php não existe no container de index.php. O cookie
 * abaixo carrega os dados necessários e sua própria assinatura HMAC, então
 * qualquer função consegue validar o login sem precisar de estado
 * compartilhado no servidor.
 */

function authAssinar(string $dados): string
{
    return hash_hmac('sha256', $dados, AUTH_SECRET);
}

function authSetCookie(int $adminId, string $username): void
{
    $exp = time() + AUTH_TTL;
    $dados = $adminId . '|' . $username . '|' . $exp;
    $valor = base64_encode($dados) . '.' . authAssinar($dados);

    setcookie(AUTH_COOKIE_NAME, $valor, [
        'expires'  => $exp,
        'path'     => '/admin',
        'httponly' => true,
        'secure'   => true,
        'samesite' => 'Lax',
    ]);
    // também disponível na própria requisição atual, sem precisar de reload
    $_COOKIE[AUTH_COOKIE_NAME] = $valor;
}

function authClearCookie(): void
{
    setcookie(AUTH_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/admin',
        'httponly' => true,
        'secure'   => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[AUTH_COOKIE_NAME]);
}

function authDoCookie(): ?array
{
    if (empty($_COOKIE[AUTH_COOKIE_NAME])) {
        return null;
    }

    $partes = explode('.', $_COOKIE[AUTH_COOKIE_NAME], 2);
    if (count($partes) !== 2) {
        return null;
    }

    [$dadosB64, $assinatura] = $partes;
    $dados = base64_decode($dadosB64, true);
    if ($dados === false) {
        return null;
    }

    if (!hash_equals(authAssinar($dados), $assinatura)) {
        return null;
    }

    $campos = explode('|', $dados);
    if (count($campos) !== 3) {
        return null;
    }

    [$adminId, $username, $exp] = $campos;
    if (!ctype_digit($adminId) || !ctype_digit($exp) || (int) $exp < time()) {
        return null;
    }

    return ['id' => (int) $adminId, 'username' => $username];
}

function adminLogado(): ?array
{
    $dadosCookie = authDoCookie();
    if (!$dadosCookie) {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, username FROM admins WHERE id = ?');
    $stmt->execute([$dadosCookie['id']]);
    $admin = $stmt->fetch();
    return $admin ?: null;
}

function exigirLogin(): array
{
    $admin = adminLogado();
    if (!$admin) {
        header('Location: /admin/login.php');
        exit;
    }
    return $admin;
}

function tentarLogin(string $username, string $senha): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($senha, $admin['password_hash'])) {
        authSetCookie((int) $admin['id'], $admin['username']);
        return true;
    }

    // pequeno atraso para dificultar ataques de força bruta
    usleep(400000);
    return false;
}

function fazerLogout(): void
{
    authClearCookie();
}

/**
 * Token CSRF sem estado no servidor: carrega seu próprio timestamp e
 * assinatura HMAC, então qualquer função consegue validar sem precisar
 * de sessão compartilhada. Válido por 1 hora.
 */
function csrfToken(): string
{
    $ts = (string) time();
    return $ts . '.' . authAssinar('csrf|' . $ts);
}

function csrfValido(?string $token): bool
{
    if (!$token || !str_contains($token, '.')) {
        return false;
    }

    [$ts, $assinatura] = explode('.', $token, 2);
    if (!ctype_digit($ts)) {
        return false;
    }

    if (!hash_equals(authAssinar('csrf|' . $ts), $assinatura)) {
        return false;
    }

    return (time() - (int) $ts) < 3600;
}
