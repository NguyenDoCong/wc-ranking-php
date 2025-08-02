<?php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    if ($name) {
        $pdo = connect_db();
        try {
            $stmt = $pdo->prepare("INSERT INTO players (name, elo, matches_played, matches_won, win_rate) VALUES (?, 1500, 0, 0, 0.0)");
            $stmt->execute([$name]);
            $message = "✅ Đã thêm người chơi: $name";
        } catch (PDOException $e) {
            $message = "⚠️ Người chơi đã tồn tại hoặc lỗi khác.";
        }
    } else {
        $message = "⛔ Tên không được để trống.";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Thêm người chơi</title></head>
<body>
    <h1>Thêm người chơi</h1>
    <?php if ($message) echo "<p>$message</p>"; ?>
    <form method="post">
        <input type="text" name="name" placeholder="Tên người chơi">
        <button type="submit">Thêm</button>
    </form>
</body>
</html>