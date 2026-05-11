<?php
/**
 * (관리자) 전송내역 목록 + TM 이름 JOIN
 * 요청: GET ?page=&pageSize=&tm=&name=&phone=&status=&startDate=&endDate=
 * 응답: { ok, data:{ rows:[{id, tmName, name, phone, status, sentAt}], total, page, pageSize } }
 */
require __DIR__ . '/../_bootstrap.php';
require_method('GET');
require_admin();

$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = min(200, max(1, (int)($_GET['pageSize'] ?? 20)));
$tm       = trim((string)($_GET['tm']    ?? ''));
$name     = trim((string)($_GET['name']  ?? ''));
$phone    = preg_replace('/\D/', '', (string)($_GET['phone'] ?? '')) ?? '';
$status   = trim((string)($_GET['status']?? ''));
$start    = trim((string)($_GET['startDate'] ?? ''));
$end      = trim((string)($_GET['endDate']   ?? ''));

$where = [];
$bind  = [];
if ($tm !== '') {
    $where[] = 'u.display_name LIKE :tm';
    $bind[':tm'] = '%' . $tm . '%';
}
if ($name !== '') {
    $where[] = 't.customer_name LIKE :name';
    $bind[':name'] = '%' . $name . '%';
}
if ($phone !== '') {
    $where[] = "REPLACE(t.customer_phone, '-', '') LIKE :phone";
    $bind[':phone'] = '%' . $phone . '%';
}
if ($status !== '') {
    $where[] = 't.current_status = :status';
    $bind[':status'] = $status;
}
if ($start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
    $where[] = 't.created_at >= :start';
    $bind[':start'] = $start . ' 00:00:00';
}
if ($end !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    $where[] = 't.created_at <= :end';
    $bind[':end'] = $end . ' 23:59:59';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$cnt = db()->prepare("
  SELECT COUNT(*) FROM transmissions t
  LEFT JOIN tm_users u ON u.id = t.sent_by_user_id
  {$whereSql}
");
$cnt->execute($bind);
$total = (int)$cnt->fetchColumn();

$offset = ($page - 1) * $pageSize;
$sql = "
  SELECT t.id,
         COALESCE(u.display_name, '(삭제됨)') AS tmName,
         t.customer_name  AS name,
         t.customer_phone AS phone,
         t.current_status AS status,
         DATE_FORMAT(t.created_at, '%Y.%m.%d %H:%i:%s') AS sentAt
  FROM transmissions t
  LEFT JOIN tm_users u ON u.id = t.sent_by_user_id
  {$whereSql}
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT {$pageSize} OFFSET {$offset}
";
$stmt = db()->prepare($sql);
$stmt->execute($bind);
$rows = $stmt->fetchAll();

ok([
    'rows'     => $rows,
    'total'    => $total,
    'page'     => $page,
    'pageSize' => $pageSize,
]);
