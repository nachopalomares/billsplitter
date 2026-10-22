ALTER TABLE bills ADD COLUMN share_token CHAR(64) NULL UNIQUE AFTER image_path;
