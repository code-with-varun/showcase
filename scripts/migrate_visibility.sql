-- Migration: Add 3-state visibility + copyright_info to existing assets table
-- Run this on the Hostinger MySQL database (u935057905_runit_ispark)
-- ============================================================

USE `u935057905_runit_ispark`;

-- Step 1: Add visibility column if it doesn't exist
ALTER TABLE `assets`
  ADD COLUMN IF NOT EXISTS `visibility` TINYINT(1) NOT NULL DEFAULT 2
    COMMENT '0=private(admin only), 1=unlisted(link only), 2=public(everyone)';

-- Step 2: Add copyright_info column if it doesn't exist
ALTER TABLE `assets`
  ADD COLUMN IF NOT EXISTS `copyright_info` TEXT NULL
    COMMENT 'Copyright notice, license, or attribution info';

-- Step 3: Migrate existing is_public data into visibility column
-- Old is_public=1 (public) -> visibility=2 (public)
-- Old is_public=0 (hidden) -> visibility=0 (private)
UPDATE `assets` SET `visibility` = 2 WHERE `is_public` = 1 AND `visibility` = 2;
UPDATE `assets` SET `visibility` = 0 WHERE `is_public` = 0;

-- Step 4: Add index on visibility
ALTER TABLE `assets`
  ADD INDEX IF NOT EXISTS `idx_visibility` (`visibility`);

-- Done.
SELECT 'Migration complete!' AS status;
