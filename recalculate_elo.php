<?php
require_once 'db.php';
require_once 'elo_calculator.php';

$pdo = connect_db();

// 1. Reset ELO & thống kê người chơi
$pdo->exec("UPDATE players SET elo = 1500, matches_played = 0, matches_won = 0, win_rate = 0.0, penalized = 0");

// 2. Xoá lịch sử ELO
$pdo->exec("DELETE FROM elo_history_raw");

// 3. Đặt lại cờ processed
$pdo->exec("UPDATE form_responses_raw SET processed = 0");

// 4. Gọi lại tính ELO từ đầu
process_unprocessed_matches();

// 5. Áp dụng trừ 200 ELO nếu chưa tham gia W3VN 7
$stmt = $pdo->prepare("SELECT name, elo FROM players WHERE w3vn_7 = 0 AND penalized = 0");
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $new_elo = $row['elo'] - 200;
    $pdo->prepare("UPDATE players SET elo = ?, penalized = 1 WHERE name = ?")
        ->execute([$new_elo, $row['name']]);
}

echo "✅ Đã tính lại toàn bộ ELO và áp dụng trừ 200 với người không tham gia W3VN 7.";
?>
