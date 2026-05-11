<?php
/**
 * 운영 서버에서 이 파일을 _config.php 로 복사한 뒤 값을 채워 사용하세요.
 * _config.php는 .gitignore로 GitHub에 올라가지 않습니다.
 *
 *   cp _config.example.php _config.php
 *   vi _config.php
 */
return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'baruncare_tm',
        'user'     => 'baruncare',            // schema.sql 마지막의 CREATE USER 사용 시
        'password' => 'CHANGE_ME_STRONG_PW',
        'charset'  => 'utf8mb4',
    ],

    // 알림톡 게이트웨이 (예: 알리고 https://smartsms.aligo.in)
    'alimtalk' => [
        'enabled'       => false,             // true 로 바꾸면 실제 발송
        'provider'      => 'aligo',
        'api_key'       => 'YOUR_API_KEY',
        'user_id'       => 'YOUR_API_USER_ID',
        'sender_key'    => 'YOUR_SENDER_KEY', // 카카오 비즈채널 발신자키
        'template_code' => 'YOUR_TEMPLATE_CODE',
        'sender_phone'  => '0264231644',      // 발신 전화번호
        'apply_url'     => 'https://baruncare.example.com/apply',
    ],

    'session' => [
        'name'     => 'BARUNCARE_SID',
        'secure'   => false,                  // HTTPS 사용 시 true
        'samesite' => 'Lax',
    ],

    'debug' => false,                         // true 면 상세 에러 노출 (개발 시만)
];
