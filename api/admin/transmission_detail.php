<?php
/**
 * (관리자) 전송내역 한 건 상세 + 진행상태 이력 (행 id 포함)
 * 요청: GET ?id=
 * 응답: { ok, data:{ row, history:[{id, status, at}] } }
 */
require __DIR__ . '/../_bootstrap.php';
require_method('GET');
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) fail('id가 필요합니다');

$rowStmt = db()->prepare("
  SELECT t.id,
         COALESCE(u.display_name, '(삭제됨)') AS tmName,
         t.customer_name  AS name,
         t.customer_phone AS phone,
         t.current_status AS status,
         DATE_FORMAT(t.created_at, '%Y.%m.%d %H:%i:%s') AS sentAt
  FROM transmissions t
  LEFT JOIN tm_users u ON u.id = t.sent_by_user_id
  WHERE t.id = :id LIMIT 1
");
$rowStmt->execute([':id' => $id]);
$row = $rowStmt->fetch();
if (!$row) fail('내역을 찾을 수 없습니다', 404);

$hStmt = db()->prepare("
  SELECT id, status,
         DATE_FORMAT(changed_at, '%Y-%m-%d %H:%i:%s') AS at
  FROM transmission_status_history
  WHERE transmission_id = :id
  ORDER BY changed_at DESC, id DESC
");
$hStmt->execute([':id' => $id]);
$history = $hStmt->fetchAll();

ok(['row' => $row, 'history' => $history]);
