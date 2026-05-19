<?php
/**
 * (관리자) 청구도우미 신청 목록
 * 요청: GET ?page=&pageSize=&name=&phone=&status=&startDate=&endDate=
 * 응답: { ok, data:{ rows:[{id,name,phone,status,appliedAt}], total, page, pageSize } }
 */
require __DIR__ . '/../_bootstrap.php';
require_method('GET');
require_admin();

$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = min(200, max(1, (int)($_GET['pageSize'] ?? 30)));
$name     = trim((string)($_GET['name']  ?? ''));
$phone    = preg_replace('/\D/', '', (string)($_GET['phone'] ?? '')) ?? '';
$status   = trim((string)($_GET['status']?? ''));
$start    = trim((string)($_GET['startDate'] ?? ''));
$end      = trim((string)($_GET['endDate']   ?? ''));

$where = [];
$bind  = [];
if ($name !== '') {
    $where[] = 'customer_name LIKE :name';
    $bind[':name'] = '%' . $name . '%';
}
if ($phone !== '') {
    $where[] = "REPLACE(customer_phone, '-', '') LIKE :phone";
    $bind[':phone'] = '%' . $phone . '%';
}
if (in_array($status, ['확인전','확인중','상담완료'], true)) {
    $where[] = 'current_status = :status';
    $bind[':status'] = $status;
}
if ($start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
    $where[] = 'created_at >= :start';
    $bind[':start'] = $start . ' 00:00:00';
}
if ($end !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    $where[] = 'created_at <= :end';
    $bind[':end'] = $end . ' 23:59:59';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$cnt = db()->prepare("SELECT COUNT(*) FROM claim_applications {$whereSql}");
$cnt->execute($bind);
$total = (int)$cnt->fetchColumn();

$offset = ($page - 1) * $pageSize;
$sql = "
  SELECT id,
         customer_name  AS name,
         customer_phone AS phone,
         current_status AS status,
         DATE_FORMAT(created_at, '%Y.%m.%d %H:%i:%s') AS appliedAt
    FROM claim_applications
    {$whereSql}
ORDER BY created_at DESC, id DESC
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
