<?php
require_once 'db.php';
$pdo = connect_db();

$stmt = $pdo->query("SELECT name, elo, matches_played, matches_won, win_rate, w3vn_7 FROM players ORDER BY elo DESC");
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head><title>Danh sách người chơi</title></head>
<body>
    <h1>Danh sách người chơi</h1>
    <table border="1" cellpadding="5">
        <tr><th>Tên</th><th>ELO</th><th>Trận</th><th>Thắng</th><th>Tỷ lệ</th><th>W3VN7</th></tr>
        <?php foreach ($players as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['name']) ?></td>
            <td><?= $p['elo'] ?></td>
            <td><?= $p['matches_played'] ?></td>
            <td><?= $p['matches_won'] ?></td>
            <td><?= round($p['win_rate'] * 100, 1) ?>%</td>
            <td><?= $p['w3vn_7'] ? "✔" : "✘" ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>