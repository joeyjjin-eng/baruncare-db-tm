<?php
/**
 * TM 회원가입
 * 요청: POST { phone, name, birthdate, password }
 *  - phone: '010-1234-5678' 또는 '01012345678' (숫자 11자리, 010/011/016/017/018/019)
 *  - name: 1~50자
 *  - birthdate: 'YYYYMMDD' (8자리 숫자)
 *  - password: 8자 이상, 영문+숫자 포함
 *
 * 응답: { ok: true, data: { user: { id, name } } }
 *
 * - phone(숫자만) 이 tm_users.username 으로 저장됨
 * - role='user', is_active=1 로 즉시 사용 가능
 */
require __DIR__ . '/_bootstrap.php';
require_method('POST');

$in = json_input();

$phoneRaw = (string)($in['phone']     ?? '');
$name     = trim((string)($in['name'] ?? ''));
$birth    = trim((string)($in['birthdate'] ?? ''));
$password = (string)($in['password'] ?? '');

// 1) 휴대폰
$phoneDigits = preg_replace('/\D/', '', $phoneRaw) ?? '';
if (!preg_match('/^01[016789]\d{7,8}$/', $phoneDigits)) {
    fail('올바른 휴대폰 번호를 입력해주세요');
}

// 2) 이름
if ($name === '' || mb_strlen($name) > 50) {
    fail('이름을 1~50자로 입력해주세요');
}

// 3) 생년월일 (8자리, 1900~현재년도, 월/일 범위)
if (!preg_match('/^\d{8}$/', $birth)) {
    fail('생년월일을 8자리 숫자로 입력해주세요 (예: 19900101)');
}
$yr = (int)substr($birth, 0, 4);
$mo = (int)substr($birth, 4, 2);
$dy = (int)substr($birth, 6, 2);
$thisYear = (int)date('Y');
if ($yr < 1900 || $yr > $thisYear || $mo < 1 || $mo > 12 || $dy < 1 || $dy > 31) {
    fail('올바른 생년월일을 입력해주세요');
}
if (!checkdate($mo, $dy, $yr)) {
    fail('올바른 생년월일을 입력해주세요');
}

// 4) 비밀번호 (8자 이상, 영문+숫자 포함)
if (strlen($password) < 8) {
    fail('비밀번호는 8자 이상이어야 합니다');
}
if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    fail('비밀번호는 영문과 숫자를 모두 포함해야 합니다');
}

// 5) 중복 체크 (휴대폰 = username)
$dup = db()->prepare("SELECT id FROM tm_users WHERE username = :u LIMIT 1");
$dup->execute([':u' => $phoneDigits]);
if ($dup->fetch()) {
    fail('이미 가입된 휴대폰 번호입니다', 409);
}

// 6) 저장
$hash = password_hash($password, PASSWORD_BCRYPT);
$ins = db()->prepare("
    INSERT INTO tm_users (username, password_hash, display_name, birthdate, role, is_active)
    VALUES (:u, :p, :n, :b, 'user', 1)
");
$ins->execute([
    ':u' => $phoneDigits,
    ':p' => $hash,
    ':n' => $name,
    ':b' => $birth,
]);

$newId = (int)db()->lastInsertId();

ok(['user' => [
    'id'   => $newId,
    'name' => $name,
]]);
