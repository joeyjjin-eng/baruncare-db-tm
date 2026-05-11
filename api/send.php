<?php
/**
 * 알림톡 전송 + 내역 저장
 * 요청:  POST { name, phone }
 * 응답:  { ok:true, data:{ id, sent } }
 */
require __DIR__ . '/_bootstrap.php';
require_method('POST');
$me = require_login();

$in    = json_input();
$name  = trim((string)($in['name'] ?? ''));
$phone = (string)($in['phone'] ?? '');
$phoneDigits = preg_replace('/\D/', '', $phone) ?? '';

if ($name === '') fail('고객 이름을 입력하세요');
if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 11) fail('올바른 휴대폰 번호를 입력하세요 (10~11자리)');

$displayPhone = normalize_phone($phoneDigits);

$pdo = db();
$pdo->beginTransaction();
try {
    // 1) 메인 행 생성
    $insTx = $pdo->prepare("
      INSERT INTO transmissions
        (customer_name, customer_phone, current_status, sent_by_user_id, send_result, provider, created_at)
      VALUES
        (:name, :phone, '알림톡전송', :uid, 'pending', :provider, NOW())
    ");
    $insTx->execute([
        ':name'     => $name,
        ':phone'    => $displayPhone,
        ':uid'      => $me['id'],
        ':provider' => !empty($config['alimtalk']['enabled']) ? ($config['alimtalk']['provider'] ?? null) : null,
    ]);
    $tid = (int)$pdo->lastInsertId();

    // 2) 상태 이력 첫 단계
    $pdo->prepare("
      INSERT INTO transmission_status_history (transmission_id, status, changed_at)
      VALUES (?, '알림톡전송', NOW())
    ")->execute([$tid]);

    // 3) 실제 알림톡 발송 (옵션)
    $result = ['success' => true, 'message_id' => null, 'reason' => null];
    if (!empty($config['alimtalk']['enabled'])) {
        $result = send_alimtalk_via_aligo($name, $phoneDigits, $config['alimtalk']);
    }

    // 4) 발송 결과 반영
    $pdo->prepare("
      UPDATE transmissions
      SET send_result = :r, provider_msg_id = :mid, fail_reason = :reason
      WHERE id = :id
    ")->execute([
        ':r'      => $result['success'] ? 'success' : 'failed',
        ':mid'    => $result['message_id'],
        ':reason' => $result['success'] ? null : ($result['reason'] ?? 'unknown'),
        ':id'     => $tid,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fail('전송 중 오류: ' . (!empty($GLOBALS['config']['debug']) ? $e->getMessage() : '서버 오류'), 500);
}

ok([
    'id'      => $tid,
    'sent'    => $result['success'],
    'message' => $result['success'] ? '알림톡이 전송되었습니다.' : ($result['reason'] ?? '발송 실패'),
]);


/* ============================================================
   알리고 알림톡 발송 (실제 연동 예시)
   - https://smartsms.aligo.in
   - $cfg 키: api_key / user_id / sender_key / template_code / sender_phone / apply_url
   - 응답 코드: 0 이면 정상 (그 외는 실패)
   ============================================================ */
function send_alimtalk_via_aligo(string $name, string $phoneDigits, array $cfg): array {
    $message = "{$name}님, 바른케어플러스 신청 사이트입니다.\n"
             . "아래 링크에서 신청을 완료해 주세요.\n"
             . ($cfg['apply_url'] ?? '');

    $payload = http_build_query([
        'apikey'      => $cfg['api_key']      ?? '',
        'userid'      => $cfg['user_id']      ?? '',
        'senderkey'   => $cfg['sender_key']   ?? '',
        'tpl_code'    => $cfg['template_code']?? '',
        'sender'      => preg_replace('/\D/', '', $cfg['sender_phone'] ?? ''),
        'receiver_1'  => $phoneDigits,
        'recvname_1'  => $name,
        'subject_1'   => '바른케어플러스 신청 안내',
        'message_1'   => $message,
    ]);

    $ch = curl_init('https://kakaoapi.aligo.in/akv10/alimtalk/send/');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['success' => false, 'message_id' => null, 'reason' => $err ?: 'curl_error'];
    }

    $data = json_decode((string)$raw, true) ?: [];
    $okFlag = isset($data['code']) && (int)$data['code'] === 0;
    return [
        'success'    => $okFlag,
        'message_id' => $data['info']['mid'] ?? null,
        'reason'     => $okFlag ? null : ($data['message'] ?? 'unknown'),
    ];
}
