-- ROLES / PRIVILEGES (Guideline Component C — optional item)
-- এই স্টেটমেন্টগুলো ঐচ্ছিক প্রদর্শনের জন্য — মূল database.sql import-এর অংশ না,
-- কারণ app root/no-password দিয়ে কানেক্ট করে (.env দেখুন)। viva-তে দেখাতে চাইলে
-- আলাদাভাবে phpMyAdmin-এর SQL ট্যাবে চালান।

CREATE USER IF NOT EXISTS 'relief_admin'@'localhost' IDENTIFIED BY 'Admin@Strong123';
GRANT ALL PRIVILEGES ON relief_db.* TO 'relief_admin'@'localhost';

CREATE USER IF NOT EXISTS 'relief_ngo_operator'@'localhost' IDENTIFIED BY 'Ngo@Strong123';
GRANT SELECT, INSERT ON relief_db.distribution TO 'relief_ngo_operator'@'localhost';
GRANT SELECT ON relief_db.family TO 'relief_ngo_operator'@'localhost';
GRANT SELECT ON relief_db.relief_item TO 'relief_ngo_operator'@'localhost';
GRANT SELECT, UPDATE ON relief_db.ngo_stock TO 'relief_ngo_operator'@'localhost';
GRANT EXECUTE ON PROCEDURE relief_db.sp_distribute_relief TO 'relief_ngo_operator'@'localhost';

FLUSH PRIVILEGES;

-- verify:
-- SHOW GRANTS FOR 'relief_ngo_operator'@'localhost';
