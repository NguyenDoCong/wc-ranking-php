<?php
require_once 'db.php';
$pdo = connect_db();

$stmt = $pdo->query("SELECT id, timestamp, player1, race1, map, result, player2, race2 FROM form_responses_raw ORDER BY id DESC");
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head><title>Danh sách trận đấu</title></head>
<body>
    <h1>Danh sách trận đấu</h1>
    <table border="1" cellpadding="5">
        <tr><th>ID</th><th>Thời gian</th><th>Người chơi 1</th><th>Race 1</th><th>Map</th><th>Kết quả</th><th>Người chơi 2</th><th>Race 2</th></tr>
        <?php foreach ($matches as $m): ?>
        <tr>
            <td><?= $m['id'] ?></td>
            <td><?= $m['timestamp'] ?></td>
            <td><?= htmlspecialchars($m['player1']) ?></td>
            <td><?= $m['race1'] ?></td>
            <td><?= $m['map'] ?></td>
            <td><?= $m['result'] == 1 ? "Thắng" : "Thua" ?></td>
            <td><?= htmlspecialchars($m['player2']) ?></td>
            <td><?= $m['race2'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>