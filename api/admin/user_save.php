<?php
/**
 * (관리자) TM 사용자 등록/수정
 *
 * 요청: POST { id?, username, name, password?, role?, is_active? }
 *   - id 없음 → 신규 생성 (username/password 필수)
 *   - id 있음 → 수정 (password 입력 시에만 변경)
 * 응답: { ok:true, data:{ id } }
 */
require __DIR__ . '/../_bootstrap.php';
require_method('POST');
require_admin();

$in       = json_input();
$id       = (int)($in['id'] ?? 0);
$username = trim((string)($in['username'] ?? ''));
$name     = trim((string)($in['name'] ?? ''));
$password = (string)($in['password'] ?? '');
$role     = (string)($in['role'] ?? 'user');
$active   = (int)($in['is_active'] ?? 1) ? 1 : 0;

if (!in_array($role, ['user', 'admin'], true)) $role = 'user';
if ($name === '')     fail('이름을 입력하세요');
if ($username === '') fail('아이디를 입력하세요');
if (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $username)) {
    fail('아이디는 영문/숫자/._- 조합으로 3~50자입니다');
}

$pdo = db();

// 아이디 중복 체크 (자기 자신 제외)
$dup = $pdo->prepare("SELECT id FROM tm_users WHERE username = :u AND id <> :id");
$dup->execute([':u' => $username, ':id' => $id]);
if ($dup->fetch()) fail('이미 사용 중인 아이디입니다');

if ($id > 0) {
    // 수정
    if ($password !== '') {
        if (strlen($password) < 4) fail('비밀번호는 4자 이상이어야 합니다');
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("
            UPDATE tm_users
            SET username=:u, display_name=:n, role=:r, is_active=:a, password_hash=:p
            WHERE id=:id
        ")->execute([
            ':u'=>$username, ':n'=>$name, ':r'=>$role, ':a'=>$active, ':p'=>$hash, ':id'=>$id
        ]);
    } else {
        $pdo->prepare("
            UPDATE tm_users
            SET username=:u, display_name=:n, role=:r, is_active=:a
            WHERE id=:id
        ")->execute([
            ':u'=>$username, ':n'=>$name, ':r'=>$role, ':a'=>$active, ':id'=>$id
        ]);
    }
    ok(['id' => $id]);
} else {
    // 신규
    if ($password === '')      fail('비밀번호를 입력하세요');
    if (strlen($password) < 4) fail('비밀번호는 4자 이상이어야 합니다');

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("
      INSERT INTO tm_users (username, password_hash, display_name, role, is_active)
      VALUES (:u, :p, :n, :r, :a)
    ");
    $stmt->execute([
        ':u'=>$username, ':p'=>$hash, ':n'=>$name, ':r'=>$role, ':a'=>$active
    ]);
    ok(['id' => (int)$pdo->lastInsertId()]);
}
