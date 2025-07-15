<?php
require_once 'db.php';
require_once 'update_rankings.php';

update_rankings();

$pdo = connect_db();
$today = date('Y-m-d');

$stmt = $pdo->prepare("SELECT rank, player, elo FROM rankings WHERE rank_date = ? ORDER BY rank ASC");
$stmt->execute([$today]);
$rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT MAX(date) FROM elo_history_raw");
$last_update = $stmt->fetchColumn();
$last_update_fmt = $last_update ? date("d/m/Y H:i", strtotime($last_update)) : "Không rõ";

?>
<!DOCTYPE html>
<html>
<head>
    <title>Bảng Xếp Hạng ELO</title>
</head>
<body>
    <h1>Bảng Xếp Hạng</h1>
    <p>Cập nhật lúc: <?= $last_update_fmt ?></p>
    <table border="1" cellpadding="6">
        <tr><th>Hạng</th><th>Tên</th><th>ELO</th></tr>
        <?php foreach ($rankings as $r): ?>
            <tr>
                <td><?= $r['rank'] ?></td>
                <td><?= htmlspecialchars($r['player']) ?></td>
                <td><?= $r['elo'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>