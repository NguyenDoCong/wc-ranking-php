<?php
require_once 'db.php';
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$pdo = connect_db();
$message = null;

// Cập nhật trận
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = (int) $_POST['edit_id'];
    $timestamp = $_POST['timestamp'];
    $player1 = $_POST['player1'];
    $race1 = $_POST['race1'];
    $map = $_POST['map'];
    $result_text = strtolower(trim($_POST['result_text']));
    $player2 = $_POST['player2'];
    $race2 = $_POST['race2'];

    $result = null;
    if ($result_text === 'thắng') $result = 1;
    elseif ($result_text === 'thua') $result = 0;

    if ($result !== null) {
        $stmt = $pdo->prepare("UPDATE form_responses_raw SET player1=?, race1=?, map=?, result=?, player2=?, race2=?, timestamp=? WHERE id=?");
        $stmt->execute([$player1, $race1, $map, $result, $player2, $race2, $timestamp, $id]);
        require_once 'recalculate_elo.php';
        $message = "✅ Đã cập nhật và tính lại ELO.";
    } else {
        $message = "⛔ Kết quả không hợp lệ.";
    }
}

// Xoá trận
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM form_responses_raw WHERE id = ?");
    $stmt->execute([$id]);
    require_once 'recalculate_elo.php';
    $message = "✅ Đã cập nhật và tính lại ELO.";
}

// Lấy dữ liệu
$stmt = $pdo->query("SELECT * FROM form_responses_raw ORDER BY id DESC LIMIT 100");
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
$players = $pdo->query("SELECT name FROM players ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$races = ["HU", "ORC", "NE", "UD"];
$maps = ["Amazonia", "Autumn Leaves", "Concealed Hill", "Echo Isles", "Hammerfall", "Last Refuge", "Northern Isles", "Terenas Stand"];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Quản lý Trận đấu</title>
    <style>
        body { font-family: Arial; background: #f8f8f8; padding: 30px; }
        h1 { text-align: center; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background: #eee; }
        form { margin: 0; }
        .actions form { display: inline; }
        select, input[type=text] { width: 100px; }
    </style>
</head>
<body>
    <h1>Danh sách Trận đấu</h1>
    <?php if ($message) echo "<p style='color:green;'>$message</p>"; ?>
    <table>
        <tr>
            <th>ID</th><th>Thời gian</th><th>Người chơi 1</th><th>Race 1</th>
            <th>Bản đồ</th><th>Kết quả</th><th>Người chơi 2</th><th>Race 2</th><th>Hành động</th>
        </tr>
        <?php foreach ($matches as $m): ?>
        <tr>
            <form method="post">
                <td><?= $m['id'] ?><input type="hidden" name="edit_id" value="<?= $m['id'] ?>"></td>
                <td>
                    <input type="datetime-local" name="timestamp" value="<?= date('Y-m-d\TH:i', strtotime($m['timestamp'])) ?>">
                </td>
                <td>
                    <select name="player1">
                        <?php foreach ($players as $p): ?>
                        <option value="<?= $p ?>" <?= $p === $m['player1'] ? 'selected' : '' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="race1">
                        <?php foreach ($races as $r): ?>
                        <option value="<?= $r ?>" <?= $r === $m['race1'] ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="map">
                        <?php foreach ($maps as $map): ?>
                        <option value="<?= $map ?>" <?= $map === $m['map'] ? 'selected' : '' ?>><?= $map ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="text" name="result_text" value="<?= $m['result'] == 1 ? 'Thắng' : 'Thua' ?>"></td>
                <td>
                    <select name="player2">
                        <?php foreach ($players as $p): ?>
                        <option value="<?= $p ?>" <?= $p === $m['player2'] ? 'selected' : '' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="race2">
                        <?php foreach ($races as $r): ?>
                        <option value="<?= $r ?>" <?= $r === $m['race2'] ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="actions">
                    <button type="submit">Lưu</button>
            </form>
            <form method="post" onsubmit="return confirm('Bạn có chắc chắn muốn xoá trận này?');">
                <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
                <button type="submit" style="background:red;color:white;">Xoá</button>
            </form>
                </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
