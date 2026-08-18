<?php
session_start();
require __DIR__ . '/includes/lang.php';
require __DIR__ . '/includes/env.php';
require __DIR__ . '/includes/icons.php';

loadEnv(__DIR__ . '/.env');

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'relief_db'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// Hashing salt (must match the value used when database.sql seed hashes were generated)
define('NID_SALT', env('NID_SALT', 'relief2026_secret_salt'));

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("ডাটাবেস সংযোগ ব্যর্থ হয়েছে। XAMPP-এ MySQL চালু আছে কিনা এবং database.sql import করা হয়েছে কিনা যাচাই করুন।<br>Error: " . htmlspecialchars($e->getMessage()));
}

function hashNID(string $nid): string {
    return hash('sha256', trim($nid) . NID_SALT);
}

function mb_levenshtein(string $a, string $b): int {
    $a = mb_str_split($a);
    $b = mb_str_split($b);
    $alen = count($a);
    $blen = count($b);
    $dp = [];
    for ($i = 0; $i <= $alen; $i++) $dp[$i][0] = $i;
    for ($j = 0; $j <= $blen; $j++) $dp[0][$j] = $j;
    for ($i = 1; $i <= $alen; $i++) {
        for ($j = 1; $j <= $blen; $j++) {
            $cost = ($a[$i - 1] === $b[$j - 1]) ? 0 : 1;
            $dp[$i][$j] = min(
                $dp[$i - 1][$j] + 1,
                $dp[$i][$j - 1] + 1,
                $dp[$i - 1][$j - 1] + $cost
            );
        }
    }
    return $dp[$alen][$blen];
}
