<?php
require_once 'db.php';

function update_rankings() {
    $pdo = connect_db();
    $today = date('Y-m-d');
    $pdo->prepare("DELETE FROM rankings WHERE rank_date = ?")->execute([$today]);

    $stmt = $pdo->prepare("SELECT name, elo FROM players WHERE matches_played >= 5 ORDER BY elo DESC");
    $stmt->execute();
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt_insert = $pdo->prepare("INSERT INTO rankings (player, elo, rank, rank_date) VALUES (?, ?, ?, ?)");
    foreach ($players as $index => $player) {
        $stmt_insert->execute([$player['name'], $player['elo'], $index + 1, $today]);
    }

}

if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    update_rankings();
}
?>