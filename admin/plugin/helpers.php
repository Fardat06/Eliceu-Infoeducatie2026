<?php
// admin/helpers.php — funcții utilitare pentru modulul de administrare

if (!function_exists('e')) {
    function e($v): string
    {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('json_out')) {
    function json_out(array $arr, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf_admin'])) {
            $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_admin'];
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check($token): bool
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf_admin']) || !is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($_SESSION['csrf_admin'], $token);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
    }
}