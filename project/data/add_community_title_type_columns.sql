-- Add dedicated title and type for community posts

ALTER TABLE community_posts
    ADD COLUMN post_title VARCHAR(300) NOT NULL DEFAULT '' AFTER user_name,
    ADD COLUMN post_type ENUM('text','media','link') NOT NULL DEFAULT 'text' AFTER post_title;

-- Backfill title from the first line of existing content
UPDATE community_posts
SET post_title = TRIM(SUBSTRING_INDEX(content, '\n', 1))
WHERE post_title = '' OR post_title IS NULL;
