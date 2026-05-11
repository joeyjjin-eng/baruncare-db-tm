-- ============================================================
-- 마이그레이션 001: tm_users 에 role 컬럼 추가
-- 기존에 schema.sql 으로 테이블을 이미 만든 환경에서만 실행하세요.
--
-- 실행:
--   mysql -u root -p baruncare_tm < database/migrations/001_add_role.sql
-- ============================================================

USE baruncare_tm;

-- 1) role 컬럼 추가 (이미 있으면 에러는 무시 가능)
ALTER TABLE tm_users
  ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER display_name;

ALTER TABLE tm_users
  ADD INDEX idx_tm_users_role (role);

-- 2) 기본 admin 사용자에게 관리자 권한 부여
UPDATE tm_users SET role = 'admin' WHERE username = 'admin';
