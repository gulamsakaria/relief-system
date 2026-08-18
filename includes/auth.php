<?php
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function isApiRequest(): bool {
    return str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');
}

function requireLogin(): void {
    if (!isset($_SESSION['user'])) {
        if (isApiRequest()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => t('api_session_expired')]);
            exit;
        }
        header('Location: index.php');
        exit;
    }
}

/**
 * @param array $allowedRoles e.g. ['admin'] or ['admin','ngo_operator']
 */
function requireRole(array $allowedRoles): void {
    requireLogin();
    if (!in_array($_SESSION['user']['role'], $allowedRoles, true)) {
        http_response_code(403);
        if (isApiRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => t('api_session_expired')]);
            exit;
        }
        die('<div style="font-family:sans-serif;padding:40px;">🚫 এই পেজ দেখার অনুমতি আপনার নেই (Unauthorized role).</div>');
    }
}

/**
 * CSRF protection. Every state-changing form/AJAX call embeds this token
 * (see header.php's CSRF_TOKEN JS constant and the hidden field on plain
 * <form> pages) and every api/*.php POST handler that writes to the
 * database calls requireCsrf() before doing anything else.
 * Without this, a malicious page could silently make the logged-in
 * operator's browser submit a distribution/registration on their behalf.
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function requireCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => t('api_csrf_invalid')]);
        exit;
    }
}
