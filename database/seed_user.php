<?php
/**
 * 최초 TM 사용자 1명 등록 (CLI 전용)
 *
 * 사용:
 *   cd /var/www/html/baruncare-tm
 *   php database/seed_user.php admin 1234 "관리자" admin
 *   php database/seed_user.php tm01  1234 "홍길동" user
 *
 * 인자: <username> <password> <displayName> <role>
 *   role 생략 시 'user'. admin 페이지에 접근하려면 'admin'.
 * 이미 같은 username이 있으면 비밀번호/이름/권한을 새 값으로 갱신합니다.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI 전용 스크립트입니다.\n");
    exit(1);
}

$cfgPath = __DIR__ . '/../api/_config.php';
if (!file_exists($cfgPath)) {
    fwrite(STDERR, "api/_config.php 가 없습니다. _config.example.php 를 복사하여 만들어 주세요.\n");
    exit(1);
}
$config = require $cfgPath;

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? '1234';
$display  = $argv[3] ?? '관리자';
$role     = $argv[4] ?? 'admin';
if (!in_array($role, ['admin', 'user'], true)) $role = 'user';

$hash = password_hash($password, PASSWORD_BCRYPT);

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config['db']['host'],
    $config['db']['port'],
    $config['db']['name']
);

try {
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "DB 연결 실패: " . $e->getMessage() . "\n");
    exit(1);
}

$pdo->prepare("
  INSERT INTO tm_users (username, password_hash, display_name, role, is_active)
  VALUES (:u, :p, :n, :r, 1)
  ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    display_name  = VALUES(display_name),
    role          = VALUES(role),
    is_active     = 1
")->execute([':u' => $username, ':p' => $hash, ':n' => $display, ':r' => $role]);

echo "OK: 사용자 '{$username}' 등록/갱신 완료.\n";
echo "    아이디:   {$username}\n";
echo "    비밀번호: {$password}\n";
echo "    이름:     {$display}\n";
echo "    권한:     {$role}\n";
