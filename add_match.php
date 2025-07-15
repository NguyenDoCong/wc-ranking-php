<?php
require_once 'db.php';
require_once 'elo_calculator.php';

$pdo = connect_db();
$message = null;

// Lấy danh sách người chơi
$player_stmt = $pdo->query("SELECT name FROM players ORDER BY name");
$player_names = $player_stmt->fetchAll(PDO::FETCH_COLUMN);

$races = ["HU", "ORC", "NE", "UD"];
$maps = ["Amazonia", "Autumn Leaves", "Concealed Hill", "Echo Isles", "Hammerfall", "Last Refuge", "Northern Isles", "Terenas Stand"];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player1 = trim($_POST['player1']);
    $race1 = $_POST['race1'];
    $map = $_POST['map'];
    $result_text = strtolower(trim($_POST['result_text']));
    $player2 = trim($_POST['player2']);
    $race2 = $_POST['race2'];
    $timestamp = date("Y-m-d H:i:s");

    $result = null;
    if ($result_text == "thắng") $result = 1;
    else if ($result_text == "thua") $result = 0;

    if (!is_null($result)) {
        $stmt = $pdo->prepare("INSERT INTO form_responses_raw (timestamp, player1, race1, map, result, player2, race2, processed)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$timestamp, $player1, $race1, $map, $result, $player2, $race2]);

        // Lấy ID mới
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
<head><title>Thêm trận đấu</title></head>
<body>
    <h1>Thêm trận đấu</h1>
    <?php if ($message) echo "<p>$message</p>"; ?>
    <form method="post">
        <label>Người chơi 1:</label>
        <select name="player1"><?= implode('', array_map(fn($n) => "<option>$n</option>", $player_names)) ?></select>
        <label>Race 1:</label>
        <select name="race1"><?= implode('', array_map(fn($r) => "<option>$r</option>", $races)) ?></select><br>

        <label>Bản đồ:</label>
        <select name="map"><?= implode('', array_map(fn($m) => "<option>$m</option>", $maps)) ?></select><br>

        <label>Kết quả (Thắng/Thua - của người chơi 1):</label>
        <input name="result_text"><br>

        <label>Người chơi 2:</label>
        <select name="player2"><?= implode('', array_map(fn($n) => "<option>$n</option>", $player_names)) ?></select>
        <label>Race 2:</label>
        <select name="race2"><?= implode('', array_map(fn($r) => "<option>$r</option>", $races)) ?></select><br>

        <button type="submit">Thêm trận</button>
    </form>
</body>
</html>