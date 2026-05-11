<?php
/**
 * 모든 API 엔드포인트의 공통 초기화.
 *   - 세션 시작
 *   - JSON 응답 헤더
 *   - DB 핸들 (PDO)
 *   - 공통 헬퍼: ok / fail / json_input / require_method / require_login
 */
declare(strict_types=1);

if (!file_exists(__DIR__ . '/_config.php')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => '_config.php 가 없습니다. _config.example.php 를 복사해서 만드세요.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require __DIR__ . '/_config.php';

if (!empty($config['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// 세션 쿠키 옵션
session_name($config['session']['name'] ?? 'BARUNCARE_SID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (bool)($config['session']['secure'] ?? false),
    'httponly' => true,
    'samesite' => $config['session']['samesite'] ?? 'Lax',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ============================================================
// 공통 헬퍼
// ============================================================
function json_input(): array {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '') {
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }
    return $_POST ?: [];
}

function respond(int $code, array $body): never {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function ok(mixed $data = null): never {
    respond(200, ['ok' => true, 'data' => $data]);
}

function fail(string $msg, int $code = 400): never {
    respond($code, ['ok' => false, 'error' => $msg]);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    global $config;
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['db']['host'],
        $config['db']['port'],
        $config['db']['name'],
        $config['db']['charset'] ?? 'utf8mb4'
    );
    try {
        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (Throwable $e) {
        fail('DB 연결 실패: ' . (!empty($GLOBALS['config']['debug']) ? $e->getMessage() : ''), 500);
    }
    return $pdo;
}

function require_method(string $method): void {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        fail('Method not allowed', 405);
    }
}

function require_login(): array {
    if (empty($_SESSION['user_id'])) fail('로그인이 필요합니다', 401);
    return [
        'id'   => (int)$_SESSION['user_id'],
        'name' => (string)($_SESSION['user_name'] ?? ''),
        'role' => (string)($_SESSION['user_role'] ?? 'user'),
    ];
}

function require_admin(): array {
    $u = require_login();
    if (($u['role'] ?? '') !== 'admin') fail('관리자 권한이 필요합니다', 403);
    return $u;
}

// 휴대폰 번호 정규화: 01012345678 → 010-1234-5678
function normalize_phone(string $raw): string {
    $d = preg_replace('/\D/', '', $raw) ?? '';
    if (preg_match('/^(\d{3})(\d{4})(\d{4})$/', $d, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
    if (preg_match('/^(\d{3})(\d{3})(\d{4})$/', $d, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
    return $raw;
}
