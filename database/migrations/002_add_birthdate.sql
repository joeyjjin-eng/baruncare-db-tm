-- ============================================================
-- tm_users 에 생년월일(birthdate) 컬럼 추가
-- 실행: mysql -u root -p baruncare_tm < database/migrations/002_add_birthdate.sql
-- ============================================================

ALTER TABLE tm_users
  ADD COLUMN birthdate CHAR(8) NULL AFTER display_name
  COMMENT 'YYYYMMDD 형식 (예: 19900101)';
