-- ============================================================
-- 002 · 청구도우미 신청 테이블 추가
--   고객용(baruncare-db/index.html) 폼이 INSERT 함
--   관리자(baruncare-tm/admin)에서 조회·상태변경
-- ============================================================
USE baruncare_tm;

CREATE TABLE IF NOT EXISTS claim_applications (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_name     VARCHAR(50)  NOT NULL                                COMMENT '고객명',
  customer_phone    VARCHAR(20)  NOT NULL                                COMMENT '휴대폰 (010-1234-5678 형식 권장)',
  gender            ENUM('male','female') NOT NULL                       COMMENT '성별',
  birth_date        DATE         NOT NULL                                COMMENT '생년월일',
  agree_privacy     TINYINT(1)   NOT NULL DEFAULT 0                      COMMENT '개인정보 수집 및 이용 동의(필수)',
  agree_third_party TINYINT(1)   NOT NULL DEFAULT 0                      COMMENT '개인정보 제3자 제공 동의(필수)',
  agree_marketing   TINYINT(1)   NOT NULL DEFAULT 0                      COMMENT '마케팅 활용 동의(선택)',
  current_status    VARCHAR(20)  NOT NULL DEFAULT '확인전'
                    COMMENT '확인전 / 확인중 / 상담완료',
  memo              VARCHAR(500)          DEFAULT NULL                   COMMENT '관리자 메모',
  ip_address        VARCHAR(45)           DEFAULT NULL                   COMMENT '신청자 IP',
  user_agent        VARCHAR(500)          DEFAULT NULL                   COMMENT 'User-Agent',
  handled_by_user_id BIGINT UNSIGNED NULL                                COMMENT '담당 처리한 관리자',
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP      COMMENT '신청 일시',
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP    COMMENT '최종 수정',
  PRIMARY KEY (id),
  KEY idx_claim_app_created (created_at),
  KEY idx_claim_app_status  (current_status),
  KEY idx_claim_app_phone   (customer_phone),
  KEY idx_claim_app_name    (customer_name),
  CONSTRAINT fk_claim_app_handled_user FOREIGN KEY (handled_by_user_id)
    REFERENCES tm_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='청구도우미 신청 내역 (고객 폼 → 관리자 조회)';
