<?php
/**
 * 진행 상태 변경
 *   - 다른 시스템(케어 신청 처리/매칭/정산 시스템) 또는 관리자 도구에서 호출
 *   - transmissions.current_status 갱신 + status_history에 한 행 추가
 *
 * 요청: POST { id, status, note? }
 *   status: 알림톡전송 | 케어신청완료 | 매칭완료 | 정산완료
 * 응답: { ok:true }
 */
require __DIR__ . '/_bootstrap.php';
require_method('POST');
require_login();

$in     = json_input();
$id     = (int)($in['id'] ?? 0);
$status = trim((string)($in['status'] ?? ''));
$note   = trim((string)($in['note'] ?? '')) ?: null;

$allowed = ['알림톡전송', '케어신청완료', '매칭완료', '정산완료'];

if ($id <= 0)                              fail('id가 필요합니다');
if (!in_array($status, $allowed, true))    fail('유효하지 않은 상태값입니다');

$pdo = db();
$pdo->beginTransaction();
try {
    $exists = $pdo->prepare("SELECT id FROM transmissions WHERE id = :id");
    $exists->execute([':id' => $id]);
    if (!$exists->fetch()) { $pdo->rollBack(); fail('내역을 찾을 수 없습니다', 404); }

    $pdo->prepare("UPDATE transmissions SET current_status = :s WHERE id = :id")
        ->execute([':s' => $status, ':id' => $id]);

    $pdo->prepare("
      INSERT INTO transmission_status_history (transmission_id, status, changed_at, note)
      VALUES (:id, :s, NOW(), :n)
    ")->execute([':id' => $id, ':s' => $status, ':n' => $note]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fail('상태 변경 실패: ' . (!empty($GLOBALS['config']['debug']) ? $e->getMessage() : ''), 500);
}

ok();
