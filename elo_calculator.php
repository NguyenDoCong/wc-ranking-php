<?php
require_once 'db.php';

function get_player_info($pdo, $name) {
    $stmt = $pdo->prepare("SELECT elo, matches_played, matches_won FROM players WHERE name = ?");
    $stmt->execute([$name]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function insert_if_not_exist($pdo, $name) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO players (name, elo, matches_played, matches_won, win_rate) VALUES (?, 1500, 0, 0, 0.0)");
        $stmt->execute([$name]);
    }
}

function process_elo($pdo, $match_time, $player1, $player2, $result, $match_id = null) {
    $k = 32;
    insert_if_not_exist($pdo, $player1);
    insert_if_not_exist($pdo, $player2);
    $p1 = get_player_info($pdo, $player1);
    $p2 = get_player_info($pdo, $player2);

    $expected1 = 1 / (1 + pow(10, ($p2['elo'] - $p1['elo']) / 400));
    $new_elo1 = round($p1['elo'] + $k * ($result - $expected1));
    $new_elo2 = round($p2['elo'] + $k * ((1 - $result) - (1 - $expected1)));

    $played1 = $p1['matches_played'] + 1;
    $played2 = $p2['matches_played'] + 1;
    $won1 = $p1['matches_won'] + ($result == 1 ? 1 : 0);
    $won2 = $p2['matches_won'] + ($result == 0 ? 1 : 0);
    $rate1 = $won1 / $played1;
    $rate2 = $won2 / $played2;

    $stmt = $pdo->prepare("UPDATE players SET elo=?, matches_played=?, matches_won=?, win_rate=? WHERE name=?");
    $stmt->execute([$new_elo1, $played1, $won1, $rate1, $player1]);
    $stmt->execute([$new_elo2, $played2, $won2, $rate2, $player2]);

    $stmt = $pdo->prepare("INSERT INTO elo_history_raw (date, player, elo) VALUES (?, ?, ?)");
    $stmt->execute([$match_time, $player1, $new_elo1]);
    $stmt->execute([$match_time, $player2, $new_elo2]);

    if ($match_id !== null) {
        $pdo->prepare("UPDATE form_responses_raw SET processed = 1 WHERE id = ?")->execute([$match_id]);
    }
}

function process_unprocessed_matches() {
    $pdo = connect_db();
    $stmt = $pdo->prepare("SELECT id, player1, player2, result, timestamp FROM form_responses_raw WHERE processed = 0 ORDER BY id ASC");
    $stmt->execute();
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($matches as $match) {
        $ts = $match["timestamp"] ?? date("Y-m-d H:i:s");
        process_elo($pdo, $ts, $match["player1"], $match["player2"], $match["result"], $match["id"]);
    }

    echo "✅ Đã xử lý " . count($matches) . " trận chưa xử lý.";
}

if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    process_unprocessed_matches();
}
?>