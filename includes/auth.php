<?php
declare(strict_types=1);
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) session_start();

function auth_user(): ?array {
    return $_SESSION['tnc_user'] ?? null;
}

function is_logged_in(): bool {
    return isset($_SESSION['tnc_user']['id']);
}

function has_role(string ...$roles): bool {
    $user = auth_user();
    return $user && in_array($user['role'], $roles, true);
}

function require_admin(): void {
    if (!is_logged_in() || !has_role('superadmin','admin','hr')) {
        header('Location: /tnc-portal/admin/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function require_applicant(): void {
    if (!is_logged_in() || !has_role('applicant')) {
        header('Location: /tnc-portal/applicant/login.php');
        exit;
    }
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['tnc_user'] = [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'email'     => $user['email'],
        'role'      => $user['role'],
        'full_name' => $user['full_name'],
    ];
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
    }
    session_destroy();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function require_superadmin(): void {
    if (!is_logged_in() || !has_role('superadmin')) {
        header('HTTP/1.0 403 Forbidden');
        die('403 Forbidden - Target Administrative endpoint strictly prohibited without Super Administrator credentials.');
    }
}

function log_activity(string $action, ?string $entity_type = null, ?string $entity_id = null): void {
    $user = auth_user();
    if (!$user) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    try {
        require_once __DIR__ . '/db.php';
        db()->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, created_at) VALUES (?,?,?,?,?,NOW())")
            ->execute([(int)$user['id'], $action, $entity_type, $entity_id, $ip]);
    } catch (Exception $e) {
        // Suppress failure securely to avoid halting core execution workflows
    }
}
