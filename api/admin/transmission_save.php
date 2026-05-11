<?php
/**
 * (관리자) 전송내역 수정
 *
 * 요청: POST {
 *   id,
 *   name?,            // 고객명
 *   phone?,           // 고객 연락처
 *   status?,          // 현재 상태 변경 (이전 상태와 다르면 history에도 행 추가)
 *   history?: [       // 단계별 일시 수정 (모달의 진행상태별 일시)
 *     { id, changed_at }     // 기존 status_history 행의 시각 수정
 *   ]
 * }
 * 응답: { ok:true }
 */
require __DIR__ . '/../_bootstrap.php';
require_method('POST');
require_admin();

$in    = json_input();
$id    = (int)($in['id'] ?? 0);
$name  = isset($in['name'])   ? trim((string)$in['name'])   : null;
$phone = isset($in['phone'])  ? trim((string)$in['phone'])  : null;
$newStatus = isset($in['status']) ? trim((string)$in['status']) : null;
$historyEdits = is_array($in['history'] ?? null) ? $in['history'] : [];

$allowed = ['알림톡전송', '케어신청완료', '매칭완료', '정산완료'];
if ($id <= 0) fail('id가 필요합니다');
if ($newStatus !== null && !in_array($newStatus, $allowed, true)) fail('유효하지 않은 상태');

$pdo = db();
$pdo->beginTransaction();
try {
    // 현재 행 확인
    $cur = $pdo->prepare("SELECT current_status FROM transmissions WHERE id = :id LIMIT 1");
    $cur->execute([':id' => $id]);
    $row = $cur->fetch();
    if (!$row) { $pdo->rollBack(); fail('대상 내역을 찾을 수 없습니다', 404); }

    // 1) 기본 필드 갱신
    $sets = [];
    $bind = [':id' => $id];
    if ($name !== null && $name !== '') {
        $sets[] = 'customer_name = :name';
        $bind[':name'] = $name;
    }
    if ($phone !== null && $phone !== '') {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) < 10 || strlen($digits) > 11) {
            $pdo->rollBack();
            fail('올바른 휴대폰 번호가 아닙니다');
        }
        $sets[] = 'customer_phone = :phone';
        $bind[':phone'] = normalize_phone($digits);
    }
    if ($newStatus !== null && $newStatus !== $row['current_status']) {
        $sets[] = 'current_status = :status';
        $bind[':status'] = $newStatus;
    }
    if ($sets) {
        $sql = 'UPDATE transmissions SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $pdo->prepare($sql)->execute($bind);
    }

    // 2) 상태가 바뀐 경우 history에도 새 행 추가
    if ($newStatus !== null && $newStatus !== $row['current_status']) {
        $pdo->prepare("
          INSERT INTO transmission_status_history (transmission_id, status, changed_at)
          VALUES (:id, :s, NOW())
        ")->execute([':id' => $id, ':s' => $newStatus]);
    }

    // 3) 단계별 일시 수정 (옵션)
    if ($historyEdits) {
        $upd = $pdo->prepare("
          UPDATE transmission_status_history
          SET changed_at = :t
          WHERE id = :id AND transmission_id = :tid
        ");
        foreach ($historyEdits as $h) {
            $hid = (int)($h['id'] ?? 0);
            $at  = (string)($h['changed_at'] ?? '');
            if ($hid <= 0 || $at === '') continue;
            // 형식: YYYY-MM-DD HH:MM:SS
            if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $at)) continue;
            $at = str_replace('T', ' ', $at);
            if (strlen($at) === 16) $at .= ':00'; // 분 단위면 :00 보강
            $upd->execute([':t' => $at, ':id' => $hid, ':tid' => $id]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fail('수정 실패: ' . (!empty($GLOBALS['config']['debug']) ? $e->getMessage() : ''), 500);
}

ok();
