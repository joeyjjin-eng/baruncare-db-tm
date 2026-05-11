<?php
/**
 * (관리자) 전송내역 삭제
 *   transmission_status_history 는 FK ON DELETE CASCADE 로 함께 삭제됨
 *
 * 요청: POST { id }
 * 응답: { ok:true }
 */
require __DIR__ . '/../_bootstrap.php';
require_method('POST');
require_admin();

$in = json_input();
$id = (int)($in['id'] ?? 0);
if ($id <= 0) fail('id가 필요합니다');

$stmt = db()->prepare("DELETE FROM transmissions WHERE id = :id");
$stmt->execute([':id' => $id]);
if ($stmt->rowCount() === 0) fail('대상 내역을 찾을 수 없습니다', 404);

ok();
