<?php
require_once 'db.php';
$pdo = connect_db();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $new_value = isset($_POST['w3vn_7']) ? (int)$_POST['w3vn_7'] : null;

    $stmt = $pdo->prepare("SELECT w3vn_7, penalized, elo FROM players WHERE name = ?");
    $stmt->execute([$name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $old_value = $row['w3vn_7'];
        $penalized = $row['penalized'];
        $elo = $row['elo'];

        $pdo->prepare("UPDATE players SET w3vn_7 = ? WHERE name = ?")->execute([$new_value, $name]);

        if ($old_value == 0 && $new_value == 1 && $penalized == 1) {
            $pdo->prepare("UPDATE players SET elo = ?, penalized = 0 WHERE name = ?")->execute([$elo + 200, $name]);
            $message = "✅ Đã hoàn lại 200 ELO.";
        } else if ($old_value == 1 && $new_value == 0 && $penalized == 0) {
            $pdo->prepare("UPDATE players SET elo = ?, penalized = 1 WHERE name = ?")->execute([$elo - 200, $name]);
            $message = "✅ Đã trừ 200 ELO.";
        } else {
            $message = "✅ Đã cập nhật (không thay đổi ELO).";
        }
    } else {
        $message = "⛔ Không tìm thấy người chơi.";
    }
}

$stmt = $pdo->query("SELECT name, w3vn_7 FROM players ORDER BY name");
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head><title>Chỉnh W3VN 7</title></head>
<body>
    <h1>Cập nhật trạng thái W3VN 7</h1>
    <?php if ($message) echo "<p>$message</p>"; ?>
    <form method="post">
        <label>Tên:</label>
        <select name="name"><?= implode('', array_map(fn($p) => "<option>{$p['name']}</option>", $players)) ?></select>
        <label>Tham gia W3VN 7?</label>
        <select name="w3vn_7">
            <option value="1">✔ Có</option>
            <option value="0">✘ Không</option>
        </select>
        <button type="submit">Cập nhật</button>
    </form>
</body>
</html>