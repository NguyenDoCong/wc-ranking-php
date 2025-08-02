<?php
require_once 'db.php';
require_once 'elo_calculator.php';
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$pdo = connect_db();
$message = null;

$player_names = $pdo->query("SELECT name FROM players ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$races = ["HU", "ORC", "NE", "UD"];
$maps = ["Amazonia", "Autumn Leaves", "Concealed Hill", "Echo Isles", "Hammerfall", "Last Refuge", "Northern Isles", "Terenas Stand"];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player1 = $_POST['player1'];
    $race1 = $_POST['race1'];
    $map = $_POST['map'];
    $result_text = strtolower(trim($_POST['result_text']));
    $player2 = $_POST['player2'];
    $race2 = $_POST['race2'];
    $timestamp = date("Y-m-d H:i:s");

    $result = ($result_text === "thắng") ? 1 : (($result_text === "thua") ? 0 : null);

    if (!is_null($result)) {
        $stmt = $pdo->prepare("INSERT INTO form_responses_raw (timestamp, player1, race1, map, result, player2, race2, processed)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$timestamp, $player1, $race1, $map, $result, $player2, $race2]);

        $match_id = $pdo->lastInsertId();
        process_elo($pdo, $timestamp, $player1, $player2, $result, $match_id);
        $message = "✅ Đã thêm và xử lý trận đấu.";
    } else {
        $message = "⛔ Kết quả chỉ nhận 'Thắng' hoặc 'Thua'.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Thêm Trận đấu</title>
    <style>
        body { font-family: Arial; background: #f9f9f9; padding: 30px; }
        h1 { text-align: center; }
        form { max-width: 600px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px #ccc; }
        label, select, input { display: block; width: 100%; margin-bottom: 10px; padding: 8px; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
        .msg { text-align: center; color: green; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Thêm Trận Đấu</h1>
    <?php if ($message) echo "<p class='msg'>$message</p>"; ?>
    <form method="post">
        <label>Người chơi 1:</label>
        <select name="player1"><?php foreach ($player_names as $n) echo "<option>$n</option>"; ?></select>

        <label>Race 1:</label>
        <select name="race1"><?php foreach ($races as $r) echo "<option>$r</option>"; ?></select>

        <label>Bản đồ:</label>
        <select name="map"><?php foreach ($maps as $m) echo "<option>$m</option>"; ?></select>

        <label>Kết quả (Thắng/Thua – của người chơi 1):</label>
        <input type="text" name="result_text">

        <label>Người chơi 2:</label>
        <select name="player2"><?php foreach ($player_names as $n) echo "<option>$n</option>"; ?></select>

        <label>Race 2:</label>
        <select name="race2"><?php foreach ($races as $r) echo "<option>$r</option>"; ?></select>

        <button type="submit">Thêm Trận</button>
    </form>
</body>
</html>
