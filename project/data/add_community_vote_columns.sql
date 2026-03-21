-- Add Reddit-style vote counters for community feed
ALTER TABLE community_posts
    ADD COLUMN total_upvotes INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_reactions,
    ADD COLUMN total_downvotes INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_upvotes,
    ADD COLUMN vote_score INT NOT NULL DEFAULT 0 AFTER total_downvotes;

-- Backfill counters from existing reactions
UPDATE community_posts p
LEFT JOIN (
    SELECT
        post_id,
        COALESCE(SUM(CASE WHEN reaction = 1 THEN 1 ELSE 0 END), 0) AS upvotes,
        COALESCE(SUM(CASE WHEN reaction = -1 THEN 1 ELSE 0 END), 0) AS downvotes,
        COALESCE(SUM(reaction), 0) AS score,
        COUNT(*) AS total_reactions
    FROM community_post_reactions
    GROUP BY post_id
) r ON r.post_id = p.id
SET
    p.total_reactions = COALESCE(r.total_reactions, 0),
    p.total_upvotes = COALESCE(r.upvotes, 0),
    p.total_downvotes = COALESCE(r.downvotes, 0),
    p.vote_score = COALESCE(r.score, 0);
