<?php
/**
 * (관리자) 청구도우미 신청 상태/메모 업데이트
 * 요청: POST JSON { id:int, status:'확인전'|'확인중'|'상담완료', memo?:string }
 * 응답: { ok:true }
 */
require __DIR__ . '/../_bootstrap.php';
require_method('POST');
$me = require_admin();

$in = json_input();
$id     = (int)($in['id'] ?? 0);
$status = trim((string)($in['status'] ?? ''));
$memo   = isset($in['memo']) ? (string)$in['memo'] : null;

if ($id <= 0) fail('id가 필요합니다.', 400);
if (!in_array($status, ['확인전','확인중','상담완료'], true)) {
    fail('진행상태 값이 올바르지 않습니다.', 400);
}
if ($memo !== null && mb_strlen($memo) > 500) {
    fail('메모는 500자 이내로 입력해주세요.', 400);
}

$row = db()->prepare('SELECT id FROM claim_applications WHERE id = ?');
$row->execute([$id]);
if (!$row->fetchColumn()) fail('해당 신청을 찾을 수 없습니다.', 404);

$sql = $memo === null
    ? 'UPDATE claim_applications SET current_status = :s, handled_by_user_id = :u WHERE id = :id'
    : 'UPDATE claim_applications SET current_status = :s, memo = :m, handled_by_user_id = :u WHERE id = :id';

$stmt = db()->prepare($sql);
$bind = [':s' => $status, ':u' => $me['id'], ':id' => $id];
if ($memo !== null) $bind[':m'] = $memo;
$stmt->execute($bind);

ok(['updated' => $stmt->rowCount()]);
