<?php
/**
 * (관리자) TM 사용자 삭제
 *
 * 요청: POST { id }
 *   - 본인 계정은 삭제할 수 없음 (관리자 잠금 사고 방지)
 *   - 해당 사용자가 보낸 transmissions.sent_by_user_id 는 NULL로 SET (FK ON DELETE SET NULL)
 * 응답: { ok:true }
 */
require __DIR__ . '/../_bootstrap.php';
require_method('POST');
$me = require_admin();

$in = json_input();
$id = (int)($in['id'] ?? 0);
if ($id <= 0)        fail('id가 필요합니다');
if ($id === $me['id']) fail('본인 계정은 삭제할 수 없습니다');

$pdo = db();
$stmt = $pdo->prepare("DELETE FROM tm_users WHERE id = :id");
$stmt->execute([':id' => $id]);

if ($stmt->rowCount() === 0) fail('대상 사용자를 찾을 수 없습니다', 404);

ok();
