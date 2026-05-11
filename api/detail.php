<?php
/**
 * 한 건의 상세 + 진행 상태 이력 (모달용)
 * 요청: GET ?id=123
 * 응답: { ok:true, data:{ row:{...}, history:[{status, at}, ...] } }
 */
require __DIR__ . '/_bootstrap.php';
require_method('GET');
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) fail('id가 필요합니다');

$stmt = db()->prepare("
  SELECT id,
         customer_name  AS name,
         customer_phone AS phone,
         current_status AS status,
         DATE_FORMAT(created_at, '%Y.%m.%d %H:%i:%s') AS sentAt
  FROM transmissions
  WHERE id = :id
  LIMIT 1
");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) fail('내역을 찾을 수 없습니다', 404);

$h = db()->prepare("
  SELECT status,
         DATE_FORMAT(changed_at, '%Y-%m-%d %H:%i:%s') AS at
  FROM transmission_status_history
  WHERE transmission_id = :id
  ORDER BY changed_at DESC, id DESC
");
$h->execute([':id' => $id]);
$history = $h->fetchAll();

ok(['row' => $row, 'history' => $history]);
