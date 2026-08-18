DROP DATABASE IF EXISTS relief_db;
CREATE DATABASE relief_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE relief_db;

CREATE TABLE division (
    division_id INT AUTO_INCREMENT PRIMARY KEY,
    division_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE district (
    district_id INT AUTO_INCREMENT PRIMARY KEY,
    district_name VARCHAR(50) NOT NULL,
    division_id INT NOT NULL,
    FOREIGN KEY (division_id) REFERENCES division(division_id)
) ENGINE=InnoDB;
CREATE INDEX idx_district_division ON district(division_id);

CREATE TABLE upazila (
    upazila_id INT AUTO_INCREMENT PRIMARY KEY,
    upazila_name VARCHAR(60) NOT NULL,
    district_id INT NOT NULL,
    FOREIGN KEY (district_id) REFERENCES district(district_id)
) ENGINE=InnoDB;
CREATE INDEX idx_upazila_district ON upazila(district_id);

CREATE TABLE area (
    area_id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(100) NOT NULL UNIQUE,
    district VARCHAR(50) NOT NULL,
    upazila_id INT NULL,
    population INT NOT NULL CHECK (population > 0),
    flood_severity ENUM('Low','Medium','High','Critical') DEFAULT 'Medium',
    FOREIGN KEY (upazila_id) REFERENCES upazila(upazila_id)
) ENGINE=InnoDB;

CREATE TABLE family (
    family_id INT AUTO_INCREMENT PRIMARY KEY,
    nid_hash CHAR(64) NOT NULL UNIQUE,
    id_type ENUM('NID','Birth Certificate') NOT NULL DEFAULT 'NID',
    head_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    family_size INT DEFAULT 1 CHECK (family_size BETWEEN 1 AND 20),
    area_id INT NULL,
    upazila_id INT NULL,
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES area(area_id) ON DELETE RESTRICT,
    FOREIGN KEY (upazila_id) REFERENCES upazila(upazila_id)
) ENGINE=InnoDB;
CREATE INDEX idx_nid_hash ON family(nid_hash);
CREATE INDEX idx_head_name ON family(head_name(20));

CREATE TABLE family_member (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    member_name VARCHAR(100) NOT NULL,
    id_type ENUM('NID','Birth Certificate') NOT NULL,
    id_hash CHAR(64) NOT NULL UNIQUE,
    FOREIGN KEY (family_id) REFERENCES family(family_id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE INDEX idx_member_family ON family_member(family_id);

CREATE TABLE ngo (
    ngo_id INT AUTO_INCREMENT PRIMARY KEY,
    ngo_name VARCHAR(100) NOT NULL UNIQUE,
    contact_phone VARCHAR(15),
    reg_no VARCHAR(30) UNIQUE,
    official_email VARCHAR(150) UNIQUE,
    logo_path VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE relief_item (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(50) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    category ENUM('Food','Medical','Shelter','Cash') NOT NULL
) ENGINE=InnoDB;

CREATE TABLE ngo_stock (
    stock_id INT AUTO_INCREMENT PRIMARY KEY,
    ngo_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity_available DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (quantity_available >= 0),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ngo_item (ngo_id, item_id),
    FOREIGN KEY (ngo_id) REFERENCES ngo(ngo_id),
    FOREIGN KEY (item_id) REFERENCES relief_item(item_id)
) ENGINE=InnoDB;

CREATE TABLE distribution_point (
    point_id INT AUTO_INCREMENT PRIMARY KEY,
    point_name VARCHAR(100) NOT NULL,
    area_id INT NULL,
    upazila_id INT NULL,
    FOREIGN KEY (area_id) REFERENCES area(area_id),
    FOREIGN KEY (upazila_id) REFERENCES upazila(upazila_id)
) ENGINE=InnoDB;

CREATE TABLE distribution (
    dist_id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    ngo_id INT NOT NULL,
    item_id INT NOT NULL,
    point_id INT NOT NULL,
    quantity DECIMAL(8,2) NOT NULL CHECK (quantity > 0),
    dist_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES family(family_id),
    FOREIGN KEY (ngo_id)    REFERENCES ngo(ngo_id),
    FOREIGN KEY (item_id)   REFERENCES relief_item(item_id),
    FOREIGN KEY (point_id)  REFERENCES distribution_point(point_id)
) ENGINE=InnoDB;
CREATE INDEX idx_dist_family_date ON distribution(family_id, dist_date);

CREATE TABLE duplicate_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    nid_hash CHAR(64) NOT NULL,
    attempted_ngo_id INT,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(200),
    FOREIGN KEY (attempted_ngo_id) REFERENCES ngo(ngo_id)
) ENGINE=InnoDB;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','ngo_operator') NOT NULL,
    ngo_id INT NULL,
    email VARCHAR(150) UNIQUE,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verify_token VARCHAR(64) NULL,
    verify_expires DATETIME NULL,
    reset_token VARCHAR(64) NULL,
    reset_expires DATETIME NULL,
    FOREIGN KEY (ngo_id) REFERENCES ngo(ngo_id)
) ENGINE=InnoDB;

CREATE TABLE query_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    ngo_id INT,
    queried_nid_hash CHAR(64),
    matched_family_id INT NULL,
    matched_member_id INT NULL,
    query_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (matched_family_id) REFERENCES family(family_id),
    FOREIGN KEY (matched_member_id) REFERENCES family_member(member_id)
) ENGINE=InnoDB;

-- VIEW
CREATE VIEW v_person_registry AS
SELECT family_id, nid_hash AS id_hash, head_name AS person_name, 'Head' AS person_role, NULL AS member_id
FROM family
UNION ALL
SELECT family_id, id_hash, member_name AS person_name, 'Member' AS person_role, member_id
FROM family_member;

-- TRIGGER
DELIMITER //
CREATE TRIGGER trg_block_duplicate
BEFORE INSERT ON distribution
FOR EACH ROW
BEGIN
    DECLARE recent_count INT;

    -- JOIN
    SELECT COUNT(*) INTO recent_count
    FROM distribution d
    JOIN relief_item r1 ON d.item_id = r1.item_id
    JOIN relief_item r2 ON r2.item_id = NEW.item_id
    WHERE d.family_id = NEW.family_id
      AND r1.category = r2.category
      AND d.dist_date >= NOW() - INTERVAL 7 DAY;

    IF recent_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'DUPLICATE BLOCKED: This family already received this category within 7 days';
    END IF;
END //

-- TRIGGER
CREATE TRIGGER trg_family_no_cross_dup
BEFORE INSERT ON family
FOR EACH ROW
BEGIN
    DECLARE v_existing_family INT;
    SELECT family_id INTO v_existing_family FROM family_member WHERE id_hash = NEW.nid_hash LIMIT 1;
    IF v_existing_family IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'DUPLICATE BLOCKED: This NID is already registered as a family member in another family';
    END IF;
END //

-- TRIGGER
CREATE TRIGGER trg_family_member_no_cross_dup
BEFORE INSERT ON family_member
FOR EACH ROW
BEGIN
    DECLARE v_existing_family INT;
    SELECT family_id INTO v_existing_family FROM family WHERE nid_hash = NEW.id_hash LIMIT 1;
    IF v_existing_family IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'DUPLICATE BLOCKED: This NID/Birth Certificate is already registered as a family head elsewhere';
    END IF;
END //
DELIMITER ;

-- STORED PROCEDURE
DELIMITER //
CREATE PROCEDURE sp_distribute_relief(
    IN p_nid_hash CHAR(64),
    IN p_ngo_id INT,
    IN p_item_id INT,
    IN p_point_id INT,
    IN p_quantity DECIMAL(8,2),
    OUT p_status VARCHAR(200)
)
proc_body: BEGIN
    DECLARE v_family_id INT;
    DECLARE v_recent INT;
    DECLARE v_stock DECIMAL(10,2);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    -- TRANSACTION
    START TRANSACTION;

    SELECT family_id INTO v_family_id
    FROM v_person_registry WHERE id_hash = p_nid_hash LIMIT 1;

    IF v_family_id IS NULL THEN
        SET p_status = 'ERROR: Family not registered';
        COMMIT;
        LEAVE proc_body;
    END IF;

    SELECT family_id INTO v_family_id FROM family WHERE family_id = v_family_id FOR UPDATE;

    -- JOIN
    SELECT COUNT(*) INTO v_recent
    FROM distribution d
    JOIN relief_item r1 ON d.item_id = r1.item_id
    JOIN relief_item r2 ON r2.item_id = p_item_id
    WHERE d.family_id = v_family_id
      AND r1.category = r2.category
      AND d.dist_date >= NOW() - INTERVAL 7 DAY;

    IF v_recent > 0 THEN
        INSERT INTO duplicate_log (nid_hash, attempted_ngo_id, reason)
        VALUES (p_nid_hash, p_ngo_id, 'Same category within 7 days');
        SET p_status = 'BLOCKED: Duplicate attempt logged';
        COMMIT;
        LEAVE proc_body;
    END IF;

    SELECT quantity_available INTO v_stock
    FROM ngo_stock WHERE ngo_id = p_ngo_id AND item_id = p_item_id FOR UPDATE;

    IF v_stock IS NULL OR v_stock < p_quantity THEN
        SET p_status = 'ERROR: Insufficient stock for this NGO/item';
        ROLLBACK;
        LEAVE proc_body;
    END IF;

    UPDATE ngo_stock SET quantity_available = quantity_available - p_quantity
    WHERE ngo_id = p_ngo_id AND item_id = p_item_id;

    INSERT INTO distribution (family_id, ngo_id, item_id, point_id, quantity)
    VALUES (v_family_id, p_ngo_id, p_item_id, p_point_id, p_quantity);
    SET p_status = 'SUCCESS: Relief distributed';
    COMMIT;
END proc_body //
DELIMITER ;

-- VIEW + JOIN
CREATE VIEW v_area_fairness AS
SELECT
    a.area_id,
    a.area_name,
    a.population,
    COUNT(d.dist_id) AS total_received,
    ROUND(a.population * (SELECT COUNT(*) FROM distribution)
          / (SELECT SUM(population) FROM area), 0) AS expected_share,
    ROUND(COUNT(d.dist_id) /
          NULLIF(a.population * (SELECT COUNT(*) FROM distribution)
          / (SELECT SUM(population) FROM area), 0), 2) AS fairness_ratio
FROM area a
LEFT JOIN distribution_point p ON a.area_id = p.area_id
LEFT JOIN distribution d ON p.point_id = d.point_id
GROUP BY a.area_id, a.area_name, a.population
ORDER BY fairness_ratio ASC;

INSERT INTO division (division_id, division_name) VALUES
(1,'Dhaka'),(2,'Chattogram'),(3,'Rajshahi'),(4,'Khulna'),
(5,'Barishal'),(6,'Sylhet'),(7,'Rangpur'),(8,'Mymensingh');

INSERT INTO district (district_id, district_name, division_id) VALUES
(1,'Dhaka',1),(2,'Faridpur',1),(3,'Gazipur',1),(4,'Gopalganj',1),(5,'Kishoreganj',1),
(6,'Madaripur',1),(7,'Manikganj',1),(8,'Munshiganj',1),(9,'Narayanganj',1),(10,'Narsingdi',1),
(11,'Rajbari',1),(12,'Shariatpur',1),(13,'Tangail',1),
(14,'Bandarban',2),(15,'Brahmanbaria',2),(16,'Chandpur',2),(17,'Chattogram',2),(18,'Cumilla',2),
(19,'Cox''s Bazar',2),(20,'Feni',2),(21,'Khagrachhari',2),(22,'Lakshmipur',2),(23,'Noakhali',2),(24,'Rangamati',2),
(25,'Bogura',3),(26,'Joypurhat',3),(27,'Naogaon',3),(28,'Natore',3),(29,'Chapainawabganj',3),
(30,'Pabna',3),(31,'Rajshahi',3),(32,'Sirajganj',3),
(33,'Bagerhat',4),(34,'Chuadanga',4),(35,'Jashore',4),(36,'Jhenaidah',4),(37,'Khulna',4),
(38,'Kushtia',4),(39,'Magura',4),(40,'Meherpur',4),(41,'Narail',4),(42,'Satkhira',4),
(43,'Barguna',5),(44,'Barishal',5),(45,'Bhola',5),(46,'Jhalokati',5),(47,'Patuakhali',5),(48,'Pirojpur',5),
(49,'Habiganj',6),(50,'Moulvibazar',6),(51,'Sunamganj',6),(52,'Sylhet',6),
(53,'Dinajpur',7),(54,'Gaibandha',7),(55,'Kurigram',7),(56,'Lalmonirhat',7),(57,'Nilphamari',7),
(58,'Panchagarh',7),(59,'Rangpur',7),(60,'Thakurgaon',7),
(61,'Jamalpur',8),(62,'Mymensingh',8),(63,'Netrokona',8),(64,'Sherpur',8);

INSERT INTO upazila (upazila_name, district_id) VALUES
('Dhamrai',1),('Dohar',1),('Keraniganj',1),('Nawabganj',1),('Savar',1),
('Faridpur Sadar',2),('Alfadanga',2),('Bhanga',2),('Boalmari',2),('Charbhadrasan',2),('Madhukhali',2),('Nagarkanda',2),('Sadarpur',2),('Saltha',2),
('Gazipur Sadar',3),('Kaliakair',3),('Kaliganj',3),('Kapasia',3),('Sreepur',3),
('Gopalganj Sadar',4),('Kashiani',4),('Kotalipara',4),('Muksudpur',4),('Tungipara',4),
('Kishoreganj Sadar',5),('Austagram',5),('Bajitpur',5),('Bhairab',5),('Hossainpur',5),('Itna',5),('Karimganj',5),('Katiadi',5),('Kuliarchar',5),('Mithamain',5),('Nikli',5),('Pakundia',5),('Tarail',5),
('Madaripur Sadar',6),('Kalkini',6),('Rajoir',6),('Shibchar',6),('Dasar',6),
('Manikganj Sadar',7),('Daulatpur',7),('Ghior',7),('Harirampur',7),('Saturia',7),('Shivalaya',7),('Singair',7),
('Munshiganj Sadar',8),('Gazaria',8),('Lohajang',8),('Sirajdikhan',8),('Sreenagar',8),('Tongibari',8),
('Narayanganj Sadar',9),('Araihazar',9),('Bandar',9),('Rupganj',9),('Sonargaon',9),
('Narsingdi Sadar',10),('Belabo',10),('Monohardi',10),('Palash',10),('Raipura',10),('Shibpur',10),
('Rajbari Sadar',11),('Baliakandi',11),('Goalandaghat',11),('Pangsha',11),('Kalukhali',11),
('Shariatpur Sadar',12),('Bhedarganj',12),('Damudya',12),('Gosairhat',12),('Naria',12),('Zajira',12),
('Tangail Sadar',13),('Basail',13),('Bhuapur',13),('Delduar',13),('Dhanbari',13),('Ghatail',13),('Gopalpur',13),('Kalihati',13),('Madhupur',13),('Mirzapur',13),('Nagarpur',13),('Sakhipur',13),
('Bandarban Sadar',14),('Ali Kadam',14),('Lama',14),('Naikhongchhari',14),('Rowangchhari',14),('Ruma',14),('Thanchi',14),
('Brahmanbaria Sadar',15),('Akhaura',15),('Ashuganj',15),('Bancharampur',15),('Kasba',15),('Nabinagar',15),('Nasirnagar',15),('Sarail',15),('Bijoynagar',15),
('Chandpur Sadar',16),('Faridganj',16),('Haimchar',16),('Hajiganj',16),('Kachua',16),('Matlab Uttar',16),('Matlab Dakshin',16),('Shahrasti',16),
('Anwara',17),('Banshkhali',17),('Boalkhali',17),('Chandanaish',17),('Fatikchhari',17),('Hathazari',17),('Lohagara',17),('Mirsharai',17),('Patiya',17),('Rangunia',17),('Raozan',17),('Sandwip',17),('Satkania',17),('Sitakunda',17),
('Cumilla Sadar',18),('Barura',18),('Brahmanpara',18),('Burichang',18),('Chandina',18),('Chauddagram',18),('Daudkandi',18),('Debidwar',18),('Homna',18),('Laksam',18),('Muradnagar',18),('Nangalkot',18),('Cumilla Sadar Dakshin',18),('Meghna',18),('Titas',18),
('Cox''s Bazar Sadar',19),('Chakaria',19),('Kutubdia',19),('Maheshkhali',19),('Pekua',19),('Ramu',19),('Teknaf',19),('Ukhia',19),
('Feni Sadar',20),('Chhagalnaiya',20),('Daganbhuiyan',20),('Parshuram',20),('Sonagazi',20),('Fulgazi',20),
('Khagrachhari Sadar',21),('Dighinala',21),('Lakshmichhari',21),('Mahalchhari',21),('Manikchhari',21),('Matiranga',21),('Panchhari',21),('Ramgarh',21),
('Lakshmipur Sadar',22),('Kamalnagar',22),('Raipur',22),('Ramganj',22),('Ramgati',22),
('Noakhali Sadar',23),('Begumganj',23),('Chatkhil',23),('Companiganj',23),('Hatiya',23),('Kabirhat',23),('Senbagh',23),('Sonaimuri',23),('Subarnachar',23),
('Rangamati Sadar',24),('Baghaichhari',24),('Barkal',24),('Belaichhari',24),('Juraichhari',24),('Kaptai',24),('Kawkhali',24),('Langadu',24),('Naniarchar',24),('Rajasthali',24),
('Bogura Sadar',25),('Adamdighi',25),('Dhunat',25),('Dhupchanchia',25),('Gabtali',25),('Kahaloo',25),('Nandigram',25),('Sariakandi',25),('Shajahanpur',25),('Sherpur',25),('Shibganj',25),('Sonatala',25),
('Joypurhat Sadar',26),('Akkelpur',26),('Kalai',26),('Khetlal',26),('Panchbibi',26),
('Naogaon Sadar',27),('Atrai',27),('Badalgachhi',27),('Dhamoirhat',27),('Manda',27),('Mahadevpur',27),('Niamatpur',27),('Patnitala',27),('Porsha',27),('Raninagar',27),('Sapahar',27),
('Natore Sadar',28),('Bagatipara',28),('Baraigram',28),('Gurudaspur',28),('Lalpur',28),('Singra',28),
('Chapainawabganj Sadar',29),('Bholahat',29),('Gomastapur',29),('Nachole',29),('Shibganj',29),
('Pabna Sadar',30),('Atgharia',30),('Bera',30),('Bhangura',30),('Chatmohar',30),('Faridpur',30),('Ishwardi',30),('Santhia',30),('Sujanagar',30),
('Bagha',31),('Bagmara',31),('Charghat',31),('Durgapur',31),('Godagari',31),('Mohanpur',31),('Paba',31),('Puthia',31),('Tanore',31),
('Sirajganj Sadar',32),('Belkuchi',32),('Chauhali',32),('Kamarkhanda',32),('Kazipur',32),('Raiganj',32),('Shahjadpur',32),('Tarash',32),('Ullapara',32),
('Bagerhat Sadar',33),('Chitalmari',33),('Fakirhat',33),('Kachua',33),('Mollahat',33),('Mongla',33),('Morrelganj',33),('Rampal',33),('Sarankhola',33),
('Chuadanga Sadar',34),('Alamdanga',34),('Damurhuda',34),('Jibannagar',34),
('Jashore Sadar',35),('Abhaynagar',35),('Bagherpara',35),('Chaugachha',35),('Jhikargachha',35),('Keshabpur',35),('Manirampur',35),('Sharsha',35),
('Jhenaidah Sadar',36),('Harinakunda',36),('Kaliganj',36),('Kotchandpur',36),('Maheshpur',36),('Shailkupa',36),
('Batiaghata',37),('Dacope',37),('Dumuria',37),('Dighalia',37),('Koyra',37),('Paikgachha',37),('Phultala',37),('Rupsa',37),('Terokhada',37),
('Kushtia Sadar',38),('Bheramara',38),('Daulatpur',38),('Khoksa',38),('Kumarkhali',38),('Mirpur',38),
('Magura Sadar',39),('Mohammadpur',39),('Shalikha',39),('Sreepur',39),
('Meherpur Sadar',40),('Gangni',40),('Mujibnagar',40),
('Narail Sadar',41),('Kalia',41),('Lohagara',41),
('Satkhira Sadar',42),('Assasuni',42),('Debhata',42),('Kalaroa',42),('Kaliganj',42),('Shyamnagar',42),('Tala',42),
('Barguna Sadar',43),('Amtali',43),('Bamna',43),('Betagi',43),('Patharghata',43),('Taltali',43),
('Barishal Sadar',44),('Agailjhara',44),('Babuganj',44),('Bakerganj',44),('Banaripara',44),('Gournadi',44),('Hizla',44),('Mehendiganj',44),('Muladi',44),('Wazirpur',44),
('Bhola Sadar',45),('Borhanuddin',45),('Char Fasson',45),('Daulatkhan',45),('Lalmohan',45),('Manpura',45),('Tazumuddin',45),
('Jhalokati Sadar',46),('Kathalia',46),('Nalchity',46),('Rajapur',46),
('Patuakhali Sadar',47),('Bauphal',47),('Dashmina',47),('Dumki',47),('Galachipa',47),('Kalapara',47),('Mirzaganj',47),('Rangabali',47),
('Pirojpur Sadar',48),('Bhandaria',48),('Kawkhali',48),('Mathbaria',48),('Nazirpur',48),('Nesarabad',48),('Zianagar',48),
('Habiganj Sadar',49),('Ajmiriganj',49),('Bahubal',49),('Baniyachong',49),('Chunarughat',49),('Lakhai',49),('Madhabpur',49),('Nabiganj',49),('Shayestaganj',49),
('Moulvibazar Sadar',50),('Barlekha',50),('Juri',50),('Kamalganj',50),('Kulaura',50),('Rajnagar',50),('Sreemangal',50),
('Sunamganj Sadar',51),('Bishwambharpur',51),('Chhatak',51),('Derai',51),('Dharampasha',51),('Dowarabazar',51),('Jagannathpur',51),('Jamalganj',51),('Sullah',51),('Tahirpur',51),('Shantiganj',51),
('Sylhet Sadar',52),('Balaganj',52),('Beanibazar',52),('Bishwanath',52),('Companiganj',52),('Fenchuganj',52),('Golapganj',52),('Gowainghat',52),('Jaintiapur',52),('Kanaighat',52),('Osmani Nagar',52),('Zakiganj',52),
('Dinajpur Sadar',53),('Birampur',53),('Birganj',53),('Biral',53),('Bochaganj',53),('Chirirbandar',53),('Fulbari',53),('Ghoraghat',53),('Hakimpur',53),('Kaharole',53),('Khansama',53),('Nawabganj',53),('Parbatipur',53),
('Gaibandha Sadar',54),('Fulchhari',54),('Gobindaganj',54),('Palashbari',54),('Sadullapur',54),('Saghata',54),('Sundarganj',54),
('Kurigram Sadar',55),('Bhurungamari',55),('Char Rajibpur',55),('Chilmari',55),('Phulbari',55),('Nageshwari',55),('Rajarhat',55),('Raomari',55),('Ulipur',55),
('Lalmonirhat Sadar',56),('Aditmari',56),('Hatibandha',56),('Kaliganj',56),('Patgram',56),
('Nilphamari Sadar',57),('Dimla',57),('Domar',57),('Jaldhaka',57),('Kishoreganj',57),('Saidpur',57),
('Panchagarh Sadar',58),('Atwari',58),('Boda',58),('Debiganj',58),('Tetulia',58),
('Rangpur Sadar',59),('Badarganj',59),('Gangachara',59),('Kaunia',59),('Mithapukur',59),('Pirgachha',59),('Pirganj',59),('Taraganj',59),
('Thakurgaon Sadar',60),('Baliadangi',60),('Haripur',60),('Pirganj',60),('Ranisankail',60),
('Jamalpur Sadar',61),('Bakshiganj',61),('Dewanganj',61),('Islampur',61),('Madarganj',61),('Melandaha',61),('Sarishabari',61),
('Mymensingh Sadar',62),('Bhaluka',62),('Dhobaura',62),('Fulbaria',62),('Gafargaon',62),('Gauripur',62),('Haluaghat',62),('Ishwarganj',62),('Muktagachha',62),('Nandail',62),('Phulpur',62),('Trishal',62),('Tarakanda',62),
('Netrokona Sadar',63),('Atpara',63),('Barhatta',63),('Durgapur',63),('Kalmakanda',63),('Kendua',63),('Khaliajuri',63),('Madan',63),('Mohanganj',63),('Purbadhala',63),
('Sherpur Sadar',64),('Jhenaigati',64),('Nakla',64),('Nalitabari',64),('Sreebardi',64);

INSERT INTO area (area_id, area_name, district, upazila_id, population, flood_severity) VALUES
(1,'সুনামগঞ্জ সদর','সুনামগঞ্জ',372,45000,'Critical'),
(2,'কুড়িগ্রাম চর','কুড়িগ্রাম',418,32000,'Critical'),
(3,'গাইবান্ধা উত্তর','গাইবান্ধা',414,28000,'High'),
(4,'সিলেট কোম্পানীগঞ্জ','সিলেট',387,51000,'High'),
(5,'নেত্রকোণা দুর্গাপুর','নেত্রকোণা',476,19500,'Medium'),
(6,'জামালপুর ইসলামপুর','জামালপুর',456,26500,'High'),
(7,'বগুড়া সারিয়াকান্দি','বগুড়া',196,22000,'Medium'),
(8,'রংপুর কাউনিয়া','রংপুর',443,24500,'High');

INSERT INTO ngo (ngo_id, ngo_name, contact_phone, reg_no) VALUES
(1,'আশা ফাউন্ডেশন','01911000001','NGO-AF-001'),
(2,'রিলিফ বাংলাদেশ','01911000002','NGO-RB-002'),
(3,'সেভ দ্য ফ্লাড ভিকটিমস','01911000003','NGO-SFV-003'),
(4,'মানবিক সহায়তা সংস্থা','01911000004','NGO-MSS-004'),
(5,'উত্তরণ ফাউন্ডেশন','01911000005','NGO-UF-005'),
(6,'প্রত্যাশা রিলিফ টিম','01911000006','NGO-PRT-006');

INSERT INTO relief_item (item_id, item_name, unit, category) VALUES
(1,'চাল','kg','Food'),
(2,'ডাল','kg','Food'),
(3,'শুকনো খাবার প্যাকেট','packet','Food'),
(4,'স্যালাইন ও ঔষধ','packet','Medical'),
(5,'কম্বল','piece','Shelter'),
(6,'তারপুলিন/ত্রিপল','piece','Shelter'),
(7,'বিশুদ্ধ পানির ট্যাবলেট','packet','Medical'),
(8,'নগদ অর্থ সহায়তা','BDT','Cash'),
(9,'চিড়া','kg','Food'),
(10,'মুড়ি','kg','Food'),
(11,'খাবার পানি (বোতলজাত)','liter','Food'),
(12,'শিশুখাদ্য (গুঁড়া দুধ)','packet','Food'),
(13,'স্যানিটারি ন্যাপকিন','packet','Medical'),
(14,'ফার্স্ট এইড বক্স','piece','Medical'),
(15,'মশারি','piece','Shelter'),
(16,'মোমবাতি ও দিয়াশলাই','packet','Shelter'),
(17,'টর্চ লাইট','piece','Shelter'),
(18,'দড়ি','piece','Shelter');

INSERT INTO ngo_stock (ngo_id, item_id, quantity_available)
SELECT n.ngo_id, i.item_id, IF(i.item_id <= 8, 500.00, 0.00)
FROM ngo n JOIN relief_item i;

INSERT INTO distribution_point (point_id, point_name, area_id) VALUES
(1,'সুনামগঞ্জ ইউনিয়ন পরিষদ',1),
(2,'সুনামগঞ্জ প্রাথমিক বিদ্যালয়',1),
(3,'কুড়িগ্রাম হাই স্কুল মাঠ',2),
(4,'গাইবান্ধা কলেজ ক্যাম্পাস',3),
(5,'সিলেট কমিউনিটি সেন্টার',4),
(6,'সিলেট রেলস্টেশন চত্বর',4),
(7,'নেত্রকোণা বাজার চত্বর',5),
(8,'জামালপুর উপজেলা মাঠ',6),
(9,'বগুড়া সারিয়াকান্দি ঘাট',7),
(10,'রংপুর কাউনিয়া বাজার',8);

INSERT INTO family (family_id, nid_hash, head_name, phone, family_size, area_id, registered_at) VALUES
(1, '84f85c8b447b5e6a0317b67e9d342d8c9172b8d8e901a4f8598e37f4b2a44ddc', 'রহিম উদ্দিন', '01711000001', 5, 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(2, '4c6123efa3f73ea0961ca4cdbaeae8723f9ce40b353ece5a301201b21540e739', 'করিম মিয়া', '01711000002', 4, 2, DATE_SUB(NOW(), INTERVAL 21 DAY)),
(3, '7fdf4c05e78f9b4e94161d1fbb4b15cd8e32c4e5704846c2b10a6a872b3a4e9f', 'ফাতেমা বেগম', '01711000003', 6, 3, DATE_SUB(NOW(), INTERVAL 24 DAY)),
(4, '4db3518b8ff7fd93c49b0b18033a3b5ed6df98a7c8db9e1eb8b2ece38c71284c', 'জোবেদা খাতুন', '01711000004', 3, 4, DATE_SUB(NOW(), INTERVAL 27 DAY)),
(5, '72c0e8be25db68ef13fadc765fd96e3497479ab4a3541e3c6f9da4ddd12d9a81', 'আব্দুল হালিম', '01711000005', 5, 5, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(6, '312d1b40764e6956338f6140bf7ce2843bb791de421ed010f48ecc926e3da59d', 'সালমা আক্তার', '01711000006', 4, 6, DATE_SUB(NOW(), INTERVAL 33 DAY)),
(7, 'df7fdb8a4ed16c50978c18bcc29445580381918f6b250b7188e8d6a054dc8c6e', 'নুরুল ইসলাম', '01711000007', 7, 7, DATE_SUB(NOW(), INTERVAL 36 DAY)),
(8, '1410ba6aef371f4101ec7900e952f76d601a164afdfcdba3e3512c42bc673341', 'রোকেয়া বেগম', '01711000008', 2, 8, DATE_SUB(NOW(), INTERVAL 39 DAY)),
(9, '91779478dc4389a49b25ef27785a939decb309ae32a7ac2b1043f0826fae03f5', 'মোজাম্মেল হক', '01711000009', 4, 1, DATE_SUB(NOW(), INTERVAL 42 DAY)),
(10, '5e3bab0531e570319474337385c53fd97936647627ee593e53220ac35e1e4e48', 'তাহেরা খাতুন', '01711000010', 3, 2, DATE_SUB(NOW(), INTERVAL 45 DAY)),
(11, 'af5b8cb6e01c52b004277159498364584acf47eddb9485c9295bec8d9e8db7de', 'আনোয়ার হোসেন', '01711000011', 5, 3, DATE_SUB(NOW(), INTERVAL 48 DAY)),
(12, '9bcf8d510e29fb57abab1ac98b12c34be53ce2027ddc56666dacd3473c9e28a8', 'মরিয়ম বিবি', '01711000012', 6, 4, DATE_SUB(NOW(), INTERVAL 51 DAY)),
(13, 'b7cab269c4bd95126c30dc9536377682f1b23a82e3b5dad92cb306176f4ffc06', 'শাহজাহান আলী', '01711000013', 4, 5, DATE_SUB(NOW(), INTERVAL 54 DAY)),
(14, '03cfb1e4527af727af4c2ab3f09952573bab28c2f73d395461736289366713bc', 'নাসরিন আক্তার', '01711000014', 3, 6, DATE_SUB(NOW(), INTERVAL 17 DAY)),
(15, '9b2f2cf85eea1d8d4fcf038a47c0b1ccb49ef4a32860d1883fb4abf55ed0c9ea', 'দেলোয়ার হোসেন', '01711000015', 5, 7, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(16, 'e4ccd53874084f0b87231295ee6aafadddf2f049b5e4710f32350e0fd3e40188', 'রাবেয়া খাতুন', '01711000016', 4, 8, DATE_SUB(NOW(), INTERVAL 23 DAY)),
(17, '1aee72ae99d1882cfe0fc2e00bdc804c095280d148950e1af040f65c4cd87d73', 'ইউসুফ আলী', '01711000017', 6, 1, DATE_SUB(NOW(), INTERVAL 26 DAY)),
(18, 'f7caadc90348ef89cfa90004b7fb5986fd9ccdff65633b82e34bf10ddb3eb989', 'সুরাইয়া বেগম', '01711000018', 3, 2, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(19, 'a14519dad86ae760d12aa6c1a114357431256a0e4d7f551c125bab53bf30ea0c', 'জাহাঙ্গীর আলম', '01711000019', 5, 3, DATE_SUB(NOW(), INTERVAL 32 DAY)),
(20, 'cb54af5beb6eb3f8f40a062e45eca3bed4a64536344141ebb2056002e943555e', 'হালিমা খাতুন', '01711000020', 4, 4, DATE_SUB(NOW(), INTERVAL 35 DAY));

INSERT INTO distribution (dist_id, family_id, ngo_id, item_id, point_id, quantity, dist_date) VALUES
(1, 1, 1, 1, 1, 7.00, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(2, 2, 2, 2, 3, 9.00, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(3, 3, 3, 3, 4, 11.00, DATE_SUB(NOW(), INTERVAL 21 DAY)),
(4, 4, 4, 4, 5, 5.00, DATE_SUB(NOW(), INTERVAL 24 DAY)),
(5, 5, 5, 5, 7, 7.00, DATE_SUB(NOW(), INTERVAL 27 DAY)),
(6, 6, 6, 6, 8, 9.00, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(7, 7, 1, 7, 9, 11.00, DATE_SUB(NOW(), INTERVAL 33 DAY)),
(8, 8, 2, 8, 10, 5.00, DATE_SUB(NOW(), INTERVAL 36 DAY)),
(9, 9, 3, 1, 1, 7.00, DATE_SUB(NOW(), INTERVAL 39 DAY)),
(10, 10, 4, 2, 3, 9.00, DATE_SUB(NOW(), INTERVAL 42 DAY)),
(11, 11, 5, 3, 4, 11.00, DATE_SUB(NOW(), INTERVAL 45 DAY)),
(12, 12, 6, 4, 5, 5.00, DATE_SUB(NOW(), INTERVAL 48 DAY)),
(13, 13, 1, 5, 7, 7.00, DATE_SUB(NOW(), INTERVAL 51 DAY)),
(14, 14, 2, 6, 8, 9.00, DATE_SUB(NOW(), INTERVAL 14 DAY)),
(15, 15, 3, 7, 9, 11.00, DATE_SUB(NOW(), INTERVAL 17 DAY)),
(16, 16, 4, 8, 10, 5.00, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(17, 17, 5, 1, 1, 7.00, DATE_SUB(NOW(), INTERVAL 23 DAY)),
(18, 18, 6, 2, 3, 9.00, DATE_SUB(NOW(), INTERVAL 26 DAY)),
(19, 19, 1, 3, 4, 11.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(20, 20, 2, 4, 5, 5.00, DATE_SUB(NOW(), INTERVAL 32 DAY)),
(21, 1, 2, 5, 1, 8.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(22, 3, 4, 8, 4, 6.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(23, 5, 6, 1, 7, 10.00, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(24, 7, 2, 6, 9, 8.00, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(25, 9, 4, 3, 1, 6.00, DATE_SUB(NOW(), INTERVAL 19 DAY)),
(26, 11, 6, 7, 4, 10.00, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(27, 13, 2, 2, 7, 8.00, DATE_SUB(NOW(), INTERVAL 31 DAY)),
(28, 15, 4, 4, 9, 6.00, DATE_SUB(NOW(), INTERVAL 2 DAY));

ALTER TABLE family AUTO_INCREMENT = 21;
ALTER TABLE distribution AUTO_INCREMENT = 29;
ALTER TABLE duplicate_log AUTO_INCREMENT = 1;
ALTER TABLE family_member AUTO_INCREMENT = 1;
ALTER TABLE query_log AUTO_INCREMENT = 1;
