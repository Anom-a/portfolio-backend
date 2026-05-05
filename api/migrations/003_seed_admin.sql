-- Replace these placeholders at deploy time using ADMIN_EMAIL and a bcrypt
-- hash generated from ADMIN_PASSWORD, for example:
-- php -r 'echo password_hash(getenv("ADMIN_PASSWORD"), PASSWORD_BCRYPT), PHP_EOL;'

INSERT INTO admins (email, password_hash)
VALUES ('{{ADMIN_EMAIL}}', '{{ADMIN_PASSWORD_BCRYPT_HASH}}')
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash);
