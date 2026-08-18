-- ============================================================
-- queries_for_report.sql
-- Guideline Component C (SQL Implementation) + Component D (Investigation & Analysis)
-- প্রতিটা query phpMyAdmin-এ চালিয়ে output-এর screenshot নিন → Screenshot Folder-এ রাখুন।
-- ============================================================
USE relief_db;

-- ------------------------------------------------------------
-- C1. INSERT — নতুন পরিবার নিবন্ধন (app-এ api/save_family.php এটাই করে)
-- demo NID 1985011234599 এর hash: SHA-256('1985011234599relief2026_secret_salt')
-- ------------------------------------------------------------
INSERT INTO family (nid_hash, head_name, phone, family_size, area_id)
VALUES (SHA2(CONCAT('1985011234599', 'relief2026_secret_salt'), 256),
        'টেস্ট পরিবার', '01711000099', 4, 1);

-- ------------------------------------------------------------
-- C2. UPDATE — পরিবারের তথ্য সংশোধন (সদস্য সংখ্যা ও ফোন আপডেট)
-- (app-এ verify_email.php-ও UPDATE চালায়: users SET is_verified = 1)
-- ------------------------------------------------------------
UPDATE family
SET family_size = 6, phone = '01711000098'
WHERE head_name = 'টেস্ট পরিবার';

-- ------------------------------------------------------------
-- C3. DELETE — ভুলভাবে নিবন্ধিত পরিবার মুছে ফেলা
-- ------------------------------------------------------------
DELETE FROM family WHERE head_name = 'টেস্ট পরিবার';

-- ------------------------------------------------------------
-- C4. SELECT + WHERE + ORDER BY + LIMIT
-- Critical flood severity এলাকার পরিবার, সদস্য সংখ্যা অনুযায়ী
-- ------------------------------------------------------------
SELECT f.head_name, f.family_size, a.area_name, a.flood_severity
FROM family f
JOIN area a ON f.area_id = a.area_id
WHERE a.flood_severity = 'Critical'
ORDER BY f.family_size DESC
LIMIT 5;

-- ------------------------------------------------------------
-- C5. GROUP BY + Aggregate (COUNT, SUM)
-- কোন NGO মোট কতগুলো বিতরণ করেছে এবং মোট পরিমাণ কত
-- ------------------------------------------------------------
SELECT n.ngo_name,
       COUNT(d.dist_id)  AS total_distributions,
       SUM(d.quantity)   AS total_quantity
FROM ngo n
LEFT JOIN distribution d ON n.ngo_id = d.ngo_id
GROUP BY n.ngo_id, n.ngo_name
ORDER BY total_distributions DESC;

-- ------------------------------------------------------------
-- C6. Multi-table JOIN (৫ টেবিল)
-- কে, কোন NGO থেকে, কী আইটেম, কোন পয়েন্টে, কোন এলাকায় পেয়েছে
-- ------------------------------------------------------------
SELECT f.head_name, n.ngo_name, ri.item_name, ri.category,
       dp.point_name, a.area_name, d.quantity, d.dist_date
FROM distribution d
JOIN family f             ON d.family_id = f.family_id
JOIN ngo n                ON d.ngo_id    = n.ngo_id
JOIN relief_item ri       ON d.item_id   = ri.item_id
JOIN distribution_point dp ON d.point_id = dp.point_id
JOIN area a               ON dp.area_id  = a.area_id
ORDER BY d.dist_date DESC;

-- ------------------------------------------------------------
-- C7. Subquery — যেসব পরিবার এখনো কোনো ত্রাণই পায়নি (coverage gap)
-- ------------------------------------------------------------
SELECT f.head_name, a.area_name, f.family_size
FROM family f
JOIN area a ON f.area_id = a.area_id
WHERE f.family_id NOT IN (SELECT DISTINCT family_id FROM distribution);

-- ------------------------------------------------------------
-- C8. Correlated subquery — প্রতিটি এলাকায় গড়ের চেয়ে বড় পরিবার
-- ------------------------------------------------------------
SELECT f.head_name, f.family_size, a.area_name
FROM family f
JOIN area a ON f.area_id = a.area_id
WHERE f.family_size > (SELECT AVG(f2.family_size)
                       FROM family f2
                       WHERE f2.area_id = f.area_id);

-- ------------------------------------------------------------
-- C9. VIEW ব্যবহার — fairness report (dashboard.php এটাই দেখায়)
-- ------------------------------------------------------------
SELECT * FROM v_area_fairness;

-- ------------------------------------------------------------
-- C10. PROCEDURE কল — duplicate blocking-এর demo
-- একই hash + একই category ৭ দিনের মধ্যে দুইবার → দ্বিতীয়বার BLOCKED
-- ------------------------------------------------------------
CALL sp_distribute_relief(
    SHA2(CONCAT('1985011234502', 'relief2026_secret_salt'), 256),
    1, 1, 3, 5.00, @status);
SELECT @status;  -- প্রথমবার: SUCCESS

CALL sp_distribute_relief(
    SHA2(CONCAT('1985011234502', 'relief2026_secret_salt'), 256),
    2, 2, 3, 5.00, @status);   -- item 2 (ডাল) — একই Food category, অন্য NGO
SELECT @status;  -- দ্বিতীয়বার: BLOCKED + duplicate_log-এ entry

SELECT * FROM duplicate_log ORDER BY attempted_at DESC LIMIT 3;

-- ------------------------------------------------------------
-- C11. TRIGGER demo — procedure বাইপাস করে সরাসরি INSERT করলেও block হয়
-- (defense-in-depth প্রমাণ; এই statement টি ইচ্ছাকৃতভাবে ERROR দেবে)
-- ------------------------------------------------------------
-- INSERT INTO distribution (family_id, ngo_id, item_id, point_id, quantity)
-- VALUES (2, 3, 3, 3, 4.00);
-- Expected: ERROR 1644 (45000): DUPLICATE BLOCKED: This family already
-- received this category within 7 days

-- ============================================================
-- Component D — Investigation & Analysis (গবেষণাধর্মী প্রশ্ন)
-- ============================================================

-- ------------------------------------------------------------
-- D1. প্রশ্ন ১: কোন এলাকাগুলো জনসংখ্যা-অনুপাতে ন্যায্য অংশের অর্ধেকও পায়নি?
-- (HAVING সহ) — এগুলোই পরবর্তী বিতরণের priority এলাকা
-- ------------------------------------------------------------
SELECT area_name, population, total_received, expected_share, fairness_ratio
FROM v_area_fairness
WHERE fairness_ratio < 0.5 OR fairness_ratio IS NULL
ORDER BY fairness_ratio ASC;

-- ------------------------------------------------------------
-- D2. প্রশ্ন ২: কোন relief category-র চাহিদা/সরবরাহ সবচেয়ে বেশি,
-- এবং category-প্রতি গড় বিতরণ পরিমাণ কত? (GROUP BY + HAVING + AVG)
-- ------------------------------------------------------------
SELECT ri.category,
       COUNT(d.dist_id)          AS times_distributed,
       ROUND(AVG(d.quantity),2)  AS avg_quantity,
       SUM(d.quantity)           AS total_quantity
FROM distribution d
JOIN relief_item ri ON d.item_id = ri.item_id
GROUP BY ri.category
HAVING COUNT(d.dist_id) >= 2
ORDER BY times_distributed DESC;

-- ------------------------------------------------------------
-- D3. প্রশ্ন ৩ (bonus): flood_severity অনুযায়ী পরিবার-প্রতি গড় সাহায্য —
-- Critical এলাকা কি আসলেই বেশি অগ্রাধিকার পাচ্ছে?
-- ------------------------------------------------------------
SELECT a.flood_severity,
       COUNT(DISTINCT f.family_id)                          AS families,
       COUNT(d.dist_id)                                     AS distributions,
       ROUND(COUNT(d.dist_id)/COUNT(DISTINCT f.family_id),2) AS dist_per_family
FROM area a
JOIN family f ON f.area_id = a.area_id
LEFT JOIN distribution d ON d.family_id = f.family_id
GROUP BY a.flood_severity
ORDER BY FIELD(a.flood_severity,'Critical','High','Medium','Low');
