-- ============================================================
-- 바른케어플러스 TM · DB 스키마 (MariaDB 10.3 호환)
-- 실행: mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS baruncare_tm
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE baruncare_tm;

-- ------------------------------------------------------------
-- 1) TM 사용자 (로그인 계정)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tm_users (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL                  COMMENT '로그인 아이디',
  password_hash VARCHAR(255) NOT NULL                  COMMENT 'bcrypt 해시 (password_hash)',
  display_name  VARCHAR(50)  NOT NULL                  COMMENT '표시 이름 (예: 홍길동)',
  role          VARCHAR(20)  NOT NULL DEFAULT 'user'   COMMENT 'user / admin',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tm_users_username (username),
  KEY idx_tm_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TM 로그인 사용자';

-- ------------------------------------------------------------
-- 2) 알림톡 전송 내역 (메인)
--    한 행 = 한 명의 고객에게 보낸 알림톡 1건
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transmissions (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_name     VARCHAR(50)  NOT NULL                COMMENT '고객 이름',
  customer_phone    VARCHAR(20)  NOT NULL                COMMENT '010-1234-5678 형식',
  current_status    VARCHAR(30)  NOT NULL DEFAULT '알림톡전송'
    COMMENT '알림톡전송 / 케어신청완료 / 매칭완료 / 정산완료',
  sent_by_user_id   BIGINT UNSIGNED NULL                 COMMENT '보낸 TM 사용자 FK',
  -- 게이트웨이 연동 추적용
  provider          VARCHAR(30)  NULL                    COMMENT '게이트웨이 (aligo 등)',
  provider_msg_id   VARCHAR(100) NULL                    COMMENT '게이트웨이 메시지 ID',
  send_result       VARCHAR(20)  NULL                    COMMENT 'pending / success / failed',
  fail_reason       VARCHAR(255) NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP   COMMENT '= 전송일시',
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_transmissions_created (created_at),
  KEY idx_transmissions_phone   (customer_phone),
  KEY idx_transmissions_name    (customer_name),
  KEY idx_transmissions_status  (current_status),
  KEY idx_transmissions_user    (sent_by_user_id),
  CONSTRAINT fk_transmissions_user FOREIGN KEY (sent_by_user_id)
    REFERENCES tm_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='알림톡 전송 내역';

-- ------------------------------------------------------------
-- 3) 진행 상태 이력
--    상태가 바뀔 때마다 한 행씩 추가 (상세 모달 타임라인용)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transmission_status_history (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  transmission_id BIGINT UNSIGNED NOT NULL                COMMENT 'transmissions FK',
  status          VARCHAR(30)  NOT NULL                   COMMENT '단계 이름',
  changed_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note            VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_status_history_tid  (transmission_id),
  KEY idx_status_history_time (changed_at),
  CONSTRAINT fk_status_history_tid FOREIGN KEY (transmission_id)
    REFERENCES transmissions(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='전송별 단계 이력';

-- ------------------------------------------------------------
-- 4) 전용 DB 사용자 생성 (선택, 보안을 위해 권장)
--    아래 주석 해제 후 비밀번호 변경하여 실행
-- ------------------------------------------------------------
-- CREATE USER IF NOT EXISTS 'baruncare'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PW';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON baruncare_tm.* TO 'baruncare'@'localhost';
-- FLUSH PRIVILEGES;
