<?php
/**
 * 전송 내역 목록 (필터 + 페이지네이션)
 * 요청: GET ?page=1&pageSize=15&name=&phone=&startDate=YYYY-MM-DD&endDate=YYYY-MM-DD
 * 응답: { ok:true, data:{ rows:[...], total, page, pageSize } }
 */
require __DIR__ . '/_bootstrap.php';
require_method('GET');
require_login();

$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = min(100, max(1, (int)($_GET['pageSize'] ?? 15)));
$name     = trim((string)($_GET['name']  ?? ''));
$phoneRaw = (string)($_GET['phone'] ?? '');
$phone    = preg_replace('/\D/', '', $phoneRaw) ?? '';
$start    = trim((string)($_GET['startDate'] ?? ''));
$end      = trim((string)($_GET['endDate']   ?? ''));

$where = [];
$bind  = [];

if ($name !== '') {
    $where[] = 'customer_name LIKE :name';
    $bind[':name'] = '%' . $name . '%';
}
if ($phone !== '') {
    // 저장은 010-1234-5678 형식이지만, 하이픈 제거 후 LIKE 비교
    $where[] = "REPLACE(customer_phone, '-', '') LIKE :phone";
    $bind[':phone'] = '%' . $phone . '%';
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

// 총 건수
$cnt = db()->prepare("SELECT COUNT(*) FROM transmissions {$whereSql}");
$cnt->execute($bind);
$total = (int)$cnt->fetchColumn();

// 페이지 데이터 (LIMIT/OFFSET 은 정수 캐스팅 후 직접 삽입 — PDO LIMIT 바인딩 이슈 회피)
$offset = ($page - 1) * $pageSize;
$sql = "
  SELECT id,
         customer_name  AS name,
         customer_phone AS phone,
         current_status AS status,
         DATE_FORMAT(created_at, '%Y.%m.%d %H:%i:%s') AS sentAt
  FROM transmissions
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
