<?php include 'templates/header.php'; ?>
<!-- nội dung -->

<?php
require_once 'db.php';
$pdo = connect_db();
$message = null;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Lấy danh sách người chơi, race, map
$players = $pdo->query("SELECT name FROM players ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$races = ["HU", "ORC", "NE", "UD"];
$maps = ["Amazonia", "Autumn Leaves", "Concealed Hill", "Echo Isles", "Hammerfall", "Last Refuge", "Northern Isles", "Terenas Stand"];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player1 = trim($_POST['player1']);
    $race1 = $_POST['race1'];
    $map = $_POST['map'];
    $result_text = strtolower(trim($_POST['result_text']));
    $player2 = trim($_POST['player2']);
    $race2 = $_POST['race2'];

    $result = null;
    if ($result_text == "thắng") $result = 1;
    else if ($result_text == "thua") $result = 0;

    if (!is_null($result)) {
        $stmt = $pdo->prepare("UPDATE form_responses_raw SET player1=?, race1=?, map=?, result=?, player2=?, race2=? WHERE id=?");
        $stmt->execute([$player1, $race1, $map, $result, $player2, $race2, $id]);
        $message = "✅ Đã cập nhật trận.";
    } else {
        $message = "⛔ Kết quả không hợp lệ.";
    }
}

// Lấy dữ liệu cũ
$stmt = $pdo->prepare("SELECT player1, race1, map, result, player2, race2 FROM form_responses_raw WHERE id = ?");
$stmt->execute([$id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$match) die("⛔ Không tìm thấy trận có ID $id");

$result_text = $match['result'] == 1 ? "Thắng" : "Thua";
?>
<!DOCTYPE html>
<html>
<head><title>Chỉnh sửa trận đấu</title></head>
<body>
<h1>Sửa trận đấu #<?= $id ?></h1>
<?php if ($message) echo "<p>$message</p>"; ?>
<form method="post">
    <label>Người chơi 1:</label>
    <select name="player1">
        <?php foreach ($players as $p): ?>
            <option value="<?= $p ?>" <?= $p == $match['player1'] ? 'selected' : '' ?>><?= $p ?></option>
        <?php endforeach; ?>
    </select>
    <label>Race 1:</label>
    <select name="race1">
        <?php foreach ($races as $r): ?>
            <option value="<?= $r ?>" <?= $r == $match['race1'] ? 'selected' : '' ?>><?= $r ?></option>
        <?php endforeach; ?>
    </select><br>

    <label>Bản đồ:</label>
    <select name="map">
        <?php foreach ($maps as $m): ?>
            <option value="<?= $m ?>" <?= $m == $match['map'] ? 'selected' : '' ?>><?= $m ?></option>
        <?php endforeach; ?>
    </select><br>

    <label>Kết quả (Thắng/Thua - của người chơi 1):</label>
    <input name="result_text" value="<?= $result_text ?>"><br>

    <label>Người chơi 2:</label>
    <select name="player2">
        <?php foreach ($players as $p): ?>
            <option value="<?= $p ?>" <?= $p == $match['player2'] ? 'selected' : '' ?>><?= $p ?></option>
        <?php endforeach; ?>
    </select>
    <label>Race 2:</label>
    <select name="race2">
        <?php foreach ($races as $r): ?>
            <option value="<?= $r ?>" <?= $r == $match['race2'] ? 'selected' : '' ?>><?= $r ?></option>
        <?php endforeach; ?>
    </select><br>

    <button type="submit">Lưu</button>
</form>
</body>
</html>

<?php include 'templates/footer.php'; ?>
