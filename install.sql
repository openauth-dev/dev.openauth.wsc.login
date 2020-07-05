ALTER TABLE wcf1_user ADD openAuthID INT(10);
ALTER TABLE wcf1_user ADD openAuthAvatar VARCHAR(200);
ALTER TABLE wcf1_user ADD enableOpenAuthAvatar TINYINT(1) NOT NULL DEFAULT 0;
