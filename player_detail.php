<?php
include 'templates/header.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
date_default_timezone_set("Asia/Ho_Chi_Minh");

$pdo = connect_db();
$name = $_GET['name'] ?? '';

if (!$name) {
    echo "⚠️ Không tìm thấy người chơi.";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM players WHERE name = ?");
$stmt->execute([$name]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    echo "⚠️ Người chơi không tồn tại.";
    exit;
}

// Lấy lịch sử ELO
$elo_stmt = $pdo->prepare("SELECT id, elo, date FROM elo_history_raw WHERE player = ? ORDER BY id ASC");
$elo_stmt->execute([$name]);
$elo_history = $elo_stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy các trận đã đấu
$match_stmt = $pdo->prepare("SELECT * FROM form_responses_raw WHERE player1 = ? OR player2 = ? ORDER BY id DESC");
$match_stmt->execute([$name, $name]);
$matches = $match_stmt->fetchAll(PDO::FETCH_ASSOC);

// Thống kê theo race & map
$races = ["HU" => "Human", "Orc" => "Orc", "NE" => "Night Elf", "UD" => "Undead"];
$by_race = [];

foreach ($matches as $m) {
    $is_p1 = $m['player1'] === $name;
    $my_race = $is_p1 ? $m['race1'] : $m['race2'];
    $op_race = $is_p1 ? $m['race2'] : $m['race1'];

    // Kiểm tra null và chuẩn hóa race
    $my_race = $my_race ? strtoupper($my_race) : '';
    $op_race = $op_race ? strtoupper($op_race) : '';
    // Bỏ qua nếu không có thông tin race
    if (!$my_race || !$op_race) continue;

    $map = $m['map'];
    $won = ($is_p1 && $m['result'] == 1) || (!$is_p1 && $m['result'] == 0);

    // Chuẩn hóa tên race
    if ($my_race == 'ORC') $my_race = 'Orc';
    if ($op_race == 'ORC') $op_race = 'Orc';

    if (!isset($by_race[$my_race])) $by_race[$my_race] = [];
    $by_race[$my_race][] = ['map' => $map, 'op' => $op_race, 'won' => $won];
}

function calculate_stats($games) {
    $stats = [];
    foreach ($games as $g) {
        $map = $g['map'];
        $op = $g['op'];
        $won = $g['won'];

        // Kiểm tra null và chuẩn hóa race opponent
        $op = $op ? strtoupper($op) : '';
        if ($op == 'ORC') $op = 'Orc';

        if (!in_array($op, ['HU','Orc','NE','UD'])) continue;

        if (!isset($stats[$map])) {
            $stats[$map] = ['total' => ['p' => 0, 'w' => 0], 'HU' => [0, 0], 'Orc' => [0, 0], 'NE' => [0, 0], 'UD' => [0, 0]];
        }

        $stats[$map]['total']['p']++;
        if ($won) $stats[$map]['total']['w']++;

        $stats[$map][$op][0]++;
        if ($won) $stats[$map][$op][1]++;
    }
    return $stats;
}

// Lấy các trận đã đấu với ELO trước và sau trận đấu
$match_stmt = $pdo->prepare("
    SELECT 
        fr.*,
        (
            SELECT eh1.elo 
            FROM elo_history_raw eh1 
            WHERE eh1.player = fr.player1 
            AND eh1.date < fr.timestamp 
            ORDER BY eh1.date DESC, eh1.id DESC 
            LIMIT 1
        ) as p1_elo_before,
        (
            SELECT eh2.elo 
            FROM elo_history_raw eh2 
            WHERE eh2.player = fr.player2 
            AND eh2.date < fr.timestamp 
            ORDER BY eh2.date DESC, eh2.id DESC 
            LIMIT 1
        ) as p2_elo_before,
        (
            SELECT eh1.elo 
            FROM elo_history_raw eh1 
            WHERE eh1.player = fr.player1 
            AND eh1.date >= fr.timestamp 
            ORDER BY eh1.date ASC, eh1.id ASC 
            LIMIT 1
        ) as p1_elo_after,
        (
            SELECT eh2.elo 
            FROM elo_history_raw eh2 
            WHERE eh2.player = fr.player2 
            AND eh2.date >= fr.timestamp 
            ORDER BY eh2.date ASC, eh2.id ASC 
            LIMIT 1
        ) as p2_elo_after
    FROM form_responses_raw fr
    WHERE fr.player1 = ? OR fr.player2 = ?
    ORDER BY fr.timestamp DESC, fr.id DESC
");
$match_stmt->execute([$name, $name]);
$matches = $match_stmt->fetchAll(PDO::FETCH_ASSOC);

// Thống kê theo race cho summary
$race_summary = [];
foreach ($matches as $m) {
    $is_p1 = $m['player1'] === $name;
    $my_race = $is_p1 ? $m['race1'] : $m['race2'];
    $won = ($is_p1 && $m['result'] == 1) || (!$is_p1 && $m['result'] == 0);
    
    // Kiểm tra null và chuẩn hóa race
    $my_race = $my_race ? strtoupper($my_race) : '';
    if ($my_race == 'ORC') $my_race = 'Orc';
    
    if (!$my_race) continue;
    
    if (!isset($race_summary[$my_race])) {
        $race_summary[$my_race] = ['played' => 0, 'won' => 0];
    }
    
    $race_summary[$my_race]['played']++;
    if ($won) $race_summary[$my_race]['won']++;
}

// Tính ranking của người chơi dựa trên ELO trong database
$rank_stmt = $pdo->prepare("SELECT COUNT(*) + 1 as rank FROM players WHERE elo > ? AND matches_played > 0");
$rank_stmt->execute([$player['elo']]);
$player_rank = $rank_stmt->fetchColumn();

// Tính tổng số người chơi
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE matches_played > 0");
$total_stmt->execute();
$total_players = $total_stmt->fetchColumn();

// Tính phần trăm top
$top_percentage = $total_players > 0 ? ($player_rank / $total_players) * 100 : 0;

// Xác định rank dựa trên vị trí thứ hạng (không phải phần trăm)
$current_rank = "D"; // Mặc định
$current_rank_label = "Rank D";

if ($player_rank >= 1 && $player_rank <= 4) {
    $current_rank = "A";
    $current_rank_label = "Rank A";
} elseif ($player_rank >= 5 && $player_rank <= 10) {
    $current_rank = "B+";
    $current_rank_label = "Rank B+";
} elseif ($player_rank >= 11 && $player_rank <= 16) {
    $current_rank = "B-";
    $current_rank_label = "Rank B-";
} elseif ($player_rank >= 17 && $player_rank <= 22) {
    $current_rank = "C+";
    $current_rank_label = "Rank C+";
} elseif ($player_rank >= 23 && $player_rank <= 28) {
    $current_rank = "C-";
    $current_rank_label = "Rank C-";
} else {
    $current_rank = "D";
    $current_rank_label = "Rank D";
}

// Điều chỉnh để người chơi luôn là player1
foreach ($matches as &$m) {
    if ($m['player1'] !== $name) {
        // Hoán đổi thông tin người chơi
        [$m['player1'], $m['player2']] = [$m['player2'], $m['player1']];
        [$m['race1'], $m['race2']] = [$m['race2'], $m['race1']];
        [$m['p1_elo_before'], $m['p2_elo_before']] = [$m['p2_elo_before'], $m['p1_elo_before']];
        [$m['p1_elo_after'], $m['p2_elo_after']] = [$m['p2_elo_after'], $m['p1_elo_after']];
        $m['result'] = $m['result'] == 1 ? 0 : 1; // Đảo kết quả
    }
}
unset($m); // để tránh lỗi tham chiếu sau vòng lặp

function race_icon($race, $size = 40) {
    $map = [
        'hu' => 'human',
        'orc' => 'orc',
        'ne' => 'nightelf',
        'ud' => 'undead'
    ];
    $key = strtolower($race);
    if (!$race) return "N/A";
    if (!isset($map[$key])) return htmlspecialchars($race); // fallback nếu race không khớp
    $file = "assets/images/races/{$map[$key]}.png";
    return "<img src='$file' alt='$key' width='$size' height='$size' style='vertical-align: middle'> ";
}

    // Xử lý chọn đối thủ
    $opponent = $_GET['opponent'] ?? '';
    $opponent_stats = null;
    $opponent_matches = [];

    if ($opponent && $opponent !== $name) {
        // Lấy tất cả trận đấu giữa 2 người chơi
        $h2h_stmt = $pdo->prepare("
            SELECT * FROM form_responses_raw 
            WHERE (player1 = ? AND player2 = ?) OR (player1 = ? AND player2 = ?)
            ORDER BY timestamp DESC, id DESC
        ");
        $h2h_stmt->execute([$name, $opponent, $opponent, $name]);
        $opponent_matches_raw = $h2h_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Tạo bản copy và chuẩn hóa dữ liệu để $name luôn là player1
        foreach ($opponent_matches_raw as $m) {
            $match = $m; // Tạo copy thay vì dùng tham chiếu
            if ($match['player1'] !== $name) {
                [$match['player1'], $match['player2']] = [$match['player2'], $match['player1']];
                [$match['race1'], $match['race2']] = [$match['race2'], $match['race1']];
                $match['result'] = $match['result'] == 1 ? 0 : 1;
            }
            $opponent_matches[] = $match;
        }
        
        // Phần tính toán thống kê giữ nguyên
        $total_matches = count($opponent_matches);
        $total_wins = array_sum(array_column($opponent_matches, 'result'));
        $win_rate = $total_matches > 0 ? ($total_wins / $total_matches) * 100 : 0;
        
        // Thống kê theo map
        $map_stats = [];
        foreach ($opponent_matches as $match) {
            $map = $match['map'];
            $won = $match['result'] == 1;
            
            if (!isset($map_stats[$map])) {
                $map_stats[$map] = ['played' => 0, 'won' => 0];
            }
            
            $map_stats[$map]['played']++;
            if ($won) $map_stats[$map]['won']++;
        }
        
        $opponent_stats = [
            'total_matches' => $total_matches,
            'total_wins' => $total_wins,
            'win_rate' => $win_rate,
            'map_stats' => $map_stats
        ];
    }
    // Lấy danh sách tất cả đối thủ
    $opponents_stmt = $pdo->prepare("
        SELECT DISTINCT 
            CASE 
                WHEN player1 = ? THEN player2 
                ELSE player1 
            END as opponent
        FROM form_responses_raw 
        WHERE player1 = ? OR player2 = ?
        ORDER BY opponent
    ");
    $opponents_stmt->execute([$name, $name, $name]);
    $all_opponents = $opponents_stmt->fetchAll(PDO::FETCH_COLUMN);

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Chi tiết người chơi <?= htmlspecialchars($name) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial;
            background-image: url("https://r4.wallpaperflare.com/wallpaper/284/884/891/warcraft-iii-reforged-blizzard-entertainment-warcraft-hd-wallpaper-fa8ecbf10aeacf6fba052397794c0aa5.jpg");
            background-attachment: fixed !important;
            background-size: cover !important;
            padding: 30px;
            color: rgba(255,255,255,.8);

        }
        .card {
            max-width: 1400px;
            margin: auto;
            /* background: rgba(13, 7, 24, .9); */
            background-color: rgba(20, 20, 40, 0.8);
            
            padding: 20px;
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            /* background: rgba(13, 7, 24, .9); */
            table-layout: fixed;
            background-color: rgba(20, 20, 40, 0);
            
        }

        th, td {
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 8px;
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Định nghĩa độ rộng cụ thể cho từng cột trong bảng trận đấu */
        .matches-table th:nth-child(1), 
        .matches-table td:nth-child(1) { width: 5%; } /* Người chơi 1 */

        .matches-table th:nth-child(2), 
        .matches-table td:nth-child(2) { width: 2.5%; }  /* ELO trước */

        .matches-table th:nth-child(3), 
        .matches-table td:nth-child(3) { width: 2.5%; }  /* Thay đổi */

        .matches-table th:nth-child(4), 
        .matches-table td:nth-child(4) { width: 2.5%; }  /* Race 1 */

        .matches-table th:nth-child(5), 
        .matches-table td:nth-child(5) { width: 2.5%; }  /* Race 2 */

        .matches-table th:nth-child(6), 
        .matches-table td:nth-child(6) { width: 5%; } /* Người chơi 2 */      

        .matches-table th:nth-child(7), 
        .matches-table td:nth-child(7) { width: 2.5%; }  /* ELO trước */          

        .matches-table th:nth-child(8), 
        .matches-table td:nth-child(8) { width: 2.5%; }  /* Thay đổi */

        .matches-table th:nth-child(9), 
        .matches-table td:nth-child(9) { width: 10%; } /* Bản đồ */

        .matches-table th:nth-child(10), 
        .matches-table td:nth-child(10) { width: 15%; } /* Thời gian */
        
        .tabs { text-align: center; margin-bottom: 20px; }
        .tab-button {
            padding: 10px 20px;
            margin: 0 5px;
            background-color: transparent;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: white;
        }
        /* .tab-button.active { background: #007bff; color: white; } */
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        canvas { max-width: 100%; height: 300px; }

        .elo-change {
            font-weight: bold;
            font-size: 0.9em;
        }
        .elo-positive { color: #4CAF50; }
        .elo-negative { color: #f44336; }
        .elo-neutral { color: rgba(255,255,255,.8); }

        a {
            text-decoration: none;
        }

        .result-win { color: #4CAF50; }
        .result-loss { color: #f44336; }

        .summary {
            width: 40%;
            margin: 0 auto;
        }

        img[alt="HU"], img[alt="Orc"], img[alt="NE"], img[alt="UD"] {
            margin-right: 4px;
        }

    </style>
</head>
<body>
<div class="card">
    <div style="text-align:center">
    <h1>
        <?= htmlspecialchars($name) ?>
    </h1>
        <h2 style="font-weight: lighter;">
            ELO <?= $player['elo'] ?> 
            <span>
                (<?= $current_rank_label ?> / Top <?= number_format($top_percentage, 1) ?>%)
            </span>
        </h2>
    </div>

    <table class="summary">
        <tr>
            <th>Race</th><th>Số trận</th><th>Thắng</th><th>Thua</th><th>Tỉ lệ thắng</th>
        </tr>
        <tr>
            <td>All</td>            
            <td><?= $player['matches_played'] ?></td>
            <td style='color:#4CAF50'><?= $player['matches_won'] ?></td>
            <td style='color:red'><?= $player['matches_played'] - $player['matches_won'] ?></td>
            <td><?= isset($player['win_rate']) ? number_format($player['win_rate'] * 100, 2) . '%' : 'N/A' ?></td>
        </tr>
        
        <!-- Thống kê theo race -->
        <?php foreach ($race_summary as $race => $stats): 
            $winrate = $stats['played'] > 0 ? ($stats['won'] / $stats['played']) * 100 : 0;
            $lost = $stats['played'] - $stats['won'];
        ?>
        <tr>
            <td><?= race_icon($race) ?></td>
            <td><?= $stats['played'] ?></td>
            <td style='color:#4CAF50'><?= $stats['won'] ?></td>
            <td style='color:red'><?= $lost ?></td>
            <td><?= number_format($winrate, 2) ?>%</td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div id="opponent-stats" style="text-align:center">

        <div style="margin-bottom: 20px;">
            <h1>Thống kê</h1>
            <select id="opponent-select" onchange="selectOpponent()" style="padding: 5px; margin-right: 10px;">
                <option value="">-- Chọn đối thủ --</option>
                <?php foreach ($all_opponents as $opp): ?>
                    <option value="<?= htmlspecialchars($opp) ?>" <?= $opponent === $opp ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opp) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <?php if ($opponent): ?>
                <button onclick="clearOpponent()" style="padding: 5px 10px;">Thống kê chung</button>
            <?php endif; ?>
        </div>
        
        <script>
        function filterMatches() {
            const playerRace = document.getElementById("playerRaceFilter").value.toUpperCase();
            const opponentRace = document.getElementById("opponentRaceFilter").value.toUpperCase();
            const rows = document.querySelectorAll(".matches-table tbody tr");

            rows.forEach(row => {
                const pRace = row.dataset.playerRace?.toUpperCase() || '';
                const oRace = row.dataset.opponentRace?.toUpperCase() || '';

                const matchPlayer = !playerRace || pRace === playerRace;
                const matchOpponent = !opponentRace || oRace === opponentRace;

                row.style.display = (matchPlayer && matchOpponent) ? "" : "none";
            });
        }
        </script>

        <?php if ($opponent_stats): ?>
        <h4>Thống kê vs <?= htmlspecialchars($opponent) ?></h4>
        
        <!-- Thống kê tổng và theo map -->
        <table style="width: 40%; margin: 20px auto;">
            <tr>
                <th style="text-align: center;">Map</th>
                <th style="color: #4CAF50; text-align: left;"><?= htmlspecialchars($name) ?></th>
                <th style="text-align: center;">H2H</th>
                <th style="color: #f44336; text-align: right;"><?= htmlspecialchars($opponent) ?></th>
            </tr>
            
            <!-- Thống kê tổng -->
            <tr style="background: rgba(255,255,255,0.05);">
                <td style="text-align: center; ">Tổng</td>
                <td style="color: #4CAF50; text-align: left;">
                    <?= number_format($opponent_stats['win_rate'], 0) ?>%
                </td>
                <td style="text-align: center; ">
                    <span style="color: #4CAF50;"><?= $opponent_stats['total_wins'] ?></span>
                    :
                    <span style="color: #f44336;"><?= $opponent_stats['total_matches'] - $opponent_stats['total_wins'] ?></span>
                </td>
                <td style="color: #f44336; text-align: right;">
                    <?= number_format(100 - $opponent_stats['win_rate'], 0) ?>%
                </td>
            </tr>
            
            <!-- Thống kê theo map -->
            <?php if (!empty($opponent_stats['map_stats'])): ?>
                <?php foreach ($opponent_stats['map_stats'] as $map => $stats): 
                    $map_winrate = $stats['played'] > 0 ? ($stats['won'] / $stats['played']) * 100 : 0;
                    $lost = $stats['played'] - $stats['won'];
                    $opponent_map_winrate = 100 - $map_winrate;
                ?>
                <tr>
                    <td style="text-align: center;"><?= htmlspecialchars($map) ? htmlspecialchars($map) : "N/A" ?></td>
                    <td style="color: #4CAF50; text-align: left;">
                        <?= number_format($map_winrate, 0) ?>%
                    </td>
                    <td style="text-align: center; ">
                        <span style="color: #4CAF50;"><?= $stats['won'] ?></span>
                        :
                        <span style="color: #f44336;"><?= $lost ?></span>
                    </td>
                    <td style="color: #f44336; text-align: right;">
                        <?= number_format($opponent_map_winrate, 0) ?>%
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>

        <!-- Lịch sử đối đầu -->
        <h4>Lịch sử đối đầu (<?= count($opponent_matches) ?> trận gần nhất)</h4>
        <table class="matches-table" >
            <tr>
                <th><?= htmlspecialchars($name) ?></th>
                <th>Race</th>
                <th>vs</th>
                <th>Race</th>
                <th><?= htmlspecialchars($opponent) ?></th>
                <th>Map</th>
                <th>Thời gian</th>
            </tr>
            <?php foreach ($opponent_matches as $match): 
                $won = $match['result'] == 1;
                $map = $match['map'] ? htmlspecialchars($match['map']) : "N/A";
                $race1 = $match['race1'] ? htmlspecialchars($match['race1']) : '';
                $race2 = $match['race2'] ? htmlspecialchars($match['race2']) : '';
                
            ?>
            <tr>
                <td class="<?= $won ? 'result-win' : 'result-loss' ?>">
                    <?= htmlspecialchars($match['player1']) ?>
                </td>
                <td><?= race_icon($race1 ?? '', 30)  ?></td>
                <td>vs</td>
                <td><?= race_icon($race2 ?? '', 30)  ?></td>
                <td class="<?= $won ? 'result-loss' : 'result-win' ?>">
                    <?= htmlspecialchars($match['player2']) ?>
                </td>
                <td><?= $map ?></td>
                <td><?= htmlspecialchars($match['timestamp']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

    </div>
    <?php endif; ?>

    <div id="race-stats-section">

    <h2 style="text-align:center">Thống kê chung</h2>

        <div class="tabs">
            <?php foreach ($races as $code => $label): ?>
                <button class="tab-button" onclick="showTab('<?= $code ?>')"><?= race_icon($code) ?></button>
            <?php endforeach; ?>
            <button class="tab-button active" onclick="showTab('ALL')">Tất cả</button>
        </div>

        <?php
        function calculate_overall_row($stats) {
            $overall = ['total' => ['p' => 0, 'w' => 0], 'HU' => [0, 0], 'Orc' => [0, 0], 'NE' => [0, 0], 'UD' => [0, 0]];
            foreach ($stats as $s) {
                foreach (['HU','Orc','NE','UD'] as $r) {
                    $overall[$r][0] += $s[$r][0];
                    $overall[$r][1] += $s[$r][1];
                }
                $overall['total']['p'] += $s['total']['p'];
                $overall['total']['w'] += $s['total']['w'];
            }
            return ['Overall' => $overall] + $stats;
        }

        foreach ($races as $code => $label):
            $data = $by_race[$code] ?? [];
            echo "<div id='tab_$code' class='tab-content'>";
            if (!$data) {
                echo "<p style='text-align:center; color:gray'>Chưa có trận nào dùng race $label.</p>";
            } else {
               $stats = calculate_overall_row(calculate_stats($data));
                echo "<table style='width: 60%; margin: 0 auto'><tr><th>Map</th><th>Total</th><th>vs Human</th><th>vs Orc</th><th>vs NE</th><th>vs UD</th></tr>";
                foreach ($stats as $map => $s) {
                    $row = "<tr><td>$map</td>";
                    $ratio = $s['total']['p'] ? number_format($s['total']['w'] / $s['total']['p'] * 100, 1) . "%" : "—";
                    $color_sum = $ratio !== '—' ? (floatval($ratio) >= 50 ? '#4CAF50' : 'red') : 'rgba(255,255,255,.8)';
                    $row .= "<td style='color: $color_sum;'>$ratio</td>";
                    foreach (["HU","Orc","NE","UD"] as $r) {
                        [$p, $w] = $s[$r];
                        $rate = $p ? number_format($w / $p * 100, 1) . "%" : "—";
                        $color = $rate !== '—' ? (floatval($rate) >= 50 ? '#4CAF50' : 'red') : 'rgba(255,255,255,.8)';
                        $row .= "<td style='color:$color'>$rate</td>";
                    }
                    $row .= "</tr>";
                    echo $row;
                }
                echo "</table>";
            }
            echo "</div>";
        endforeach;

        $all_games = array_merge(...array_values($by_race));
        $stats = calculate_overall_row(calculate_stats($all_games));
        ?>
        <div id="tab_ALL" class="tab-content active">
            <table style='width: 60%; margin: 0 auto'>
                <tr><th>Map</th><th>Total</th><th>vs Human</th><th>vs Orc</th><th>vs NE</th><th>vs UD</th></tr>
                <?php foreach ($stats as $map => $s): ?>
                <tr>
                    <td><?= $map ?></td>
                    <?php
                    $rate = $s['total']['p'] ? number_format($s['total']['w'] / $s['total']['p'] * 100, 1) . "%" : "—";
                    $color = $rate !== '—' ? (floatval($rate) >= 50 ? '#4CAF50' : 'red') : 'rgba(255,255,255,.8)';
                    ?>
                    <td style="color: <?= $color ?>;"><?= $rate ?></td>
                    <?php foreach (["HU","Orc","NE","UD"] as $r):
                        [$p, $w] = $s[$r];
                        $rate = $p ? number_format($w / $p * 100, 1) . "%" : "—";
                        $color = $rate !== '—' ? (floatval($rate) >= 50 ? '#4CAF50' : 'red') : 'rgba(255,255,255,.8)';
                    ?>
                    <td style="color: <?= $color ?>"><?= $rate ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>


    <script>
    function showTab(id) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-button').forEach(el => el.classList.remove('active'));
        document.getElementById('tab_' + id).classList.add('active');
        event.target.classList.add('active');
    }
    </script>

    <div id="matches-section">

        <h2 style="text-align:center">Trận đã đấu</h2>

        <div style="text-align:center; margin-bottom: 20px;">
            <select id="playerRaceFilter" onchange="filterMatches()" style="padding: 6px 12px; margin: 0 10px;">
                <option value="">Race người chơi</option>
                <option value="HU">Human</option>
                <option value="ORC">Orc</option>
                <option value="NE">Night Elf</option>
                <option value="UD">Undead</option>
            </select>

            <select id="opponentRaceFilter" onchange="filterMatches()" style="padding: 6px 12px; margin: 0 10px;">
                <option value="">Race đối thủ</option>
                <option value="HU">Human</option>
                <option value="ORC">Orc</option>
                <option value="NE">Night Elf</option>
                <option value="UD">Undead</option>
            </select>
        </div>

        
        <table class="matches-table">
        <thead>
            <tr>
                <th>Người chơi 1</th>
                <th>ELO</th>
                <th>+/-</th>
                <th>Race</th>
                <th>Race</th>
                <th>Người chơi 2</th>
                <th>ELO</th>
                <th>+/-</th>
                <th>Bản đồ</th>
                <th>Thời gian</th>
            </tr>
        </thead>
        <tbody>
            <?php 
                foreach ($matches as $m):
                    $p1_won = $m['result'] == 1;
                    
                    // Tính thay đổi ELO
                    $p1_elo_change = 0;
                    $p2_elo_change = 0;
                    
                    if ($m['p1_elo_before'] !== null && $m['p1_elo_after'] !== null) {
                        $p1_elo_change = $m['p1_elo_after'] - $m['p1_elo_before'];
                    }
                    
                    if ($m['p2_elo_before'] !== null && $m['p2_elo_after'] !== null) {
                        $p2_elo_change = $m['p2_elo_after'] - $m['p2_elo_before'];
                    }
                    
                    // Định dạng hiển thị thay đổi ELO
                    $p1_change_display = '';
                    $p1_change_class = 'elo-neutral';
                    if ($p1_elo_change > 0) {
                        $p1_change_display = '+' . $p1_elo_change;
                        $p1_change_class = 'elo-positive';
                    } elseif ($p1_elo_change < 0) {
                        $p1_change_display = $p1_elo_change;
                        $p1_change_class = 'elo-negative';
                    } else {
                        $p1_change_display = '0';
                    }
                    
                    $p2_change_display = '';
                    $p2_change_class = 'elo-neutral';
                    if ($p2_elo_change > 0) {
                        $p2_change_display = '+' . $p2_elo_change;
                        $p2_change_class = 'elo-positive';
                    } elseif ($p2_elo_change < 0) {
                        $p2_change_display = $p2_elo_change;
                        $p2_change_class = 'elo-negative';
                    } else {
                        $p2_change_display = '0';
                    }

            ?>
            <tr 
                data-player-race="<?= strtoupper($m['race1']) ?>" 
                data-opponent-race="<?= strtoupper($m['race2']) ?>"
            >


                <td class="<?= $p1_won ? 'result-win' : 'result-loss' ?>"><?= htmlspecialchars($m['player1']) ?></td>
                <td class="<?= $p1_won ? 'result-win' : 'result-loss' ?>"><?= $m['p1_elo_before'] ?? 'N/A' ?></td>
                <td class="elo-change <?= $p1_change_class ?>"><?= $p1_change_display ?: 'N/A' ?></td>
                <td><?= race_icon($m['race1']?: 'N/A', 30) ?></td>
                <td><?= race_icon($m['race2']?: 'N/A', 30) ?></td>
                <td>
                    <a class="<?= $p1_won ? 'result-loss' : 'result-win' ?>" 
                        href="player_detail.php?name=<?= urlencode($m['player2']) ?>">
                        <?= htmlspecialchars($m['player2']) ?>
                    </a>
                </td>            
                <td class="<?= $p1_won ? 'result-loss' : 'result-win' ?>"><?= $m['p2_elo_before'] ?? 'N/A' ?></td>
                <td class="elo-change <?= $p2_change_class ?>"><?= $p2_change_display ?: 'N/A' ?></td>
                <td><?= htmlspecialchars($m['map'] ?? '') ?></td>
                <td><?= htmlspecialchars($m['timestamp']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    function toggleSections() {
        const opponent = document.getElementById('opponent-select').value;
        const raceStatsSection = document.getElementById('race-stats-section');
        const matchesSection = document.getElementById('matches-section');
        
        if (opponent) {
            // Có chọn đối thủ - ẩn 2 phần
            raceStatsSection.style.display = 'none';
            matchesSection.style.display = 'none';
        } else {
            // Không chọn đối thủ - hiện 2 phần  
            raceStatsSection.style.display = 'block';
            matchesSection.style.display = 'block';
        }
    }
    
    function selectOpponent() {
        const select = document.getElementById('opponent-select');
        const opponent = select.value;
        if (opponent) {
            const url = new URL(window.location);
            url.searchParams.set('opponent', opponent);
            window.location.href = url.toString();
        }
    }

    function clearOpponent() {
        const url = new URL(window.location);
        url.searchParams.delete('opponent');
        window.location.href = url.toString();
    }
    </script>
    


    <h1 style="text-align:center">Lịch sử ELO</h1>
    <canvas id="eloChart"></canvas>

</div>

<script>
    const labels = <?= json_encode(array_column($elo_history, "id")) ?>;
    const data = <?= json_encode(array_column($elo_history, "elo")) ?>;

    const ctx = document.getElementById('eloChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'ELO theo thời gian',
                data: data,
                borderColor: 'blue',
                backgroundColor: 'lightblue',
                fill: false,
                tension: 0.2
            }]
        },
        options: {
            scales: {
                x: { title: { display: true, text: 'Match ID' }},
                y: { title: { display: true, text: 'ELO' }, beginAtZero: false }
            }
        }
    });
    document.addEventListener('DOMContentLoaded', function() {
        toggleSections();
    });
</script>
</body>
</html>