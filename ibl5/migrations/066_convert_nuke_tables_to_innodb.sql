-- Migration 066: Convert remaining MyISAM nuke_* tables to InnoDB
--
-- Completes the engine migration started in 001. No MyISAM-specific features
-- are in use. ALTER TABLE preserves all indexes, triggers, and data.
-- nuke_config retains its latin1 charset — engine change only.

ALTER TABLE nuke_antiflood        ENGINE=InnoDB;
ALTER TABLE nuke_authors          ENGINE=InnoDB;
ALTER TABLE nuke_banned_ip        ENGINE=InnoDB;
ALTER TABLE nuke_blocks           ENGINE=InnoDB;
ALTER TABLE nuke_comments         ENGINE=InnoDB;
ALTER TABLE nuke_config           ENGINE=InnoDB;
ALTER TABLE nuke_counter          ENGINE=InnoDB;
ALTER TABLE nuke_modules          ENGINE=InnoDB;
ALTER TABLE nuke_optimize_gain    ENGINE=InnoDB;
ALTER TABLE nuke_pages            ENGINE=InnoDB;
ALTER TABLE nuke_pages_categories ENGINE=InnoDB;
ALTER TABLE nuke_poll_desc        ENGINE=InnoDB;
ALTER TABLE nuke_session          ENGINE=InnoDB;
ALTER TABLE nuke_stats_date       ENGINE=InnoDB;
ALTER TABLE nuke_stats_hour       ENGINE=InnoDB;
ALTER TABLE nuke_stats_month      ENGINE=InnoDB;
ALTER TABLE nuke_stats_year       ENGINE=InnoDB;
ALTER TABLE nuke_stories          ENGINE=InnoDB;
ALTER TABLE nuke_stories_cat      ENGINE=InnoDB;
ALTER TABLE nuke_topics           ENGINE=InnoDB;
ALTER TABLE nuke_users            ENGINE=InnoDB;
