<?php
require __DIR__ . '/_bootstrap.php';
require_method('POST');

$in = json_input();
$username = trim((string)($in['username'] ?? ''));
$password = (string)($in['password'] ?? '');

if ($username === '' || $password === '') fail('아이디와 비밀번호를 입력하세요');

$stmt = db()->prepare("
  SELECT id, password_hash, display_name, role, is_active
  FROM tm_users WHERE username = :u LIMIT 1
");
$stmt->execute([':u' => $username]);
$user = $stmt->fetch();

if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
    fail('아이디 또는 비밀번호가 올바르지 않습니다', 401);
}

session_regenerate_id(true);
$_SESSION['user_id']   = (int)$user['id'];
$_SESSION['user_name'] = $user['display_name'];
$_SESSION['user_role'] = $user['role'] ?? 'user';

ok(['user' => [
    'id'   => (int)$user['id'],
    'name' => $user['display_name'],
    'role' => $user['role'] ?? 'user',
]]);
