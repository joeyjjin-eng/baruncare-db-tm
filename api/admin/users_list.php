<?php
/**
 * (관리자) TM 사용자 목록
 * 요청: GET
 * 응답: { ok:true, data:{ rows:[{id, username, name, role, is_active, createdAt}] } }
 */
require __DIR__ . '/../_bootstrap.php';
require_method('GET');
require_admin();

$rows = db()->query("
  SELECT id,
         username,
         display_name AS name,
         role,
         is_active,
         DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS createdAt
  FROM tm_users
  ORDER BY id DESC
")->fetchAll();

ok(['rows' => $rows]);
