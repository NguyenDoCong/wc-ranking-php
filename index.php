<?php
require_once 'db.php';
require_once 'update_rankings.php';
date_default_timezone_set("Asia/Ho_Chi_Minh");
include 'templates/header.php';

update_rankings();

$pdo = connect_db();
$today = date('Y-m-d');
$players = $pdo->query("SELECT * FROM players WHERE matches_played >=5 ORDER BY elo DESC")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT rank, player, elo FROM rankings WHERE rank_date = ? ORDER BY rank ASC");
$stmt->execute([$today]);
$rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT MAX(date) FROM elo_history_raw");
$last_update = $stmt->fetchColumn();
$last_update_fmt = $last_update ? date("d/m/Y H:i", strtotime($last_update)) : "Không rõ";

$race_stmt = $pdo->prepare("
    SELECT 
        name, 
        GROUP_CONCAT(
            CONCAT(r, ' (', race_count, ')') 
            ORDER BY race_count DESC 
            SEPARATOR ', '
        ) AS races 
    FROM (
        SELECT 
            name, 
            r, 
            COUNT(*) AS race_count 
        FROM (
            SELECT player1 AS name, race1 AS r FROM form_responses_raw 
            UNION ALL 
            SELECT player2 AS name, race2 AS r FROM form_responses_raw
        ) AS combined 
        GROUP BY name, r
    ) AS race_counts 
    GROUP BY name
");
$race_stmt->execute();
$player_races = $race_stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [name => "HU,NE,UD"]

// Phân nhóm rank
$rank_groups = [
    "A"  => ["label" => "Rank A", "range" => [0, 4]],
    "B+" => ["label" => "Rank B+", "range" => [4, 10]],
    "B-" => ["label" => "Rank B-", "range" => [10, 16]],
    "C+" => ["label" => "Rank C+", "range" => [16, 22]],
    "C-" => ["label" => "Rank C-", "range" => [22, 28]],
    "D"  => ["label" => "Rank D", "range" => [28, count($players)]],
];

function race_icon($race, $size = 30) {
    $map = [
        'hu' => 'human',
        'orc' => 'orc', 
        'ne' => 'nightelf',
        'ud' => 'undead'
    ];
    
    // Chuyển về chữ thường để mapping nhất quán
    $key = strtolower($race);
    
    if (!isset($map[$key])) {
        return htmlspecialchars($race); // fallback nếu race không khớp
    }
    
    $file = "assets/images/races/{$map[$key]}.png";
    return "<img src='$file' alt='$key' width='$size' height='$size' style='vertical-align: middle'> ";
}


?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Bảng Xếp Hạng ELO</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            /* background-color: #f6f8fa; */
            text-align: center;
            background-image: url("https://r4.wallpaperflare.com/wallpaper/284/884/891/warcraft-iii-reforged-blizzard-entertainment-warcraft-hd-wallpaper-fa8ecbf10aeacf6fba052397794c0aa5.jpg");
            --w3-bg-glass: rgba(13, 7, 24, .9);
            background-attachment: fixed!important;
            background-size: cover!important;
            align-items: center!important;
            justify-content: center!important;
            color: rgba(255,255,255,.8);
        }
        h1 {
            /* color: FloralWhite; */
            margin-top: 40px;
        }
        .updated {
            font-style: italic;
            margin-top: 10px;
            color: grey;
        }
        .rank-label { font-size: 20px; margin: 30px auto; text-align: left; font-weight: bold; width: 95%;}
        .background { 
            /* background-color: rgba(13, 7, 24, .9);  */
            background-color: rgba(20, 20, 40, 0.8);
            width: 85%; 
            margin: 60px auto; 
            padding: 20px 0;}

        table {
            margin: 30px auto;
            border-collapse: collapse;
            width: 95%;
            /* background-color: #fff; */
            /* box-shadow: 0 0 10px rgba(0,0,0,0.1); */
            margin: 0 auto;
            background-color: rgba(20, 20, 40, 0);
            /* border: 1px solid rgba(255,255,255,0.1); */
        }
        th, td {
            /* border: 1px solid #ccc; */
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        th {
            /* background-color: #eee; */
        }

        /* Định nghĩa độ rộng cụ thể cho từng cột trong bảng trận đấu */
        table th:nth-child(1), 
        table td:nth-child(1) { width: 5%; } /* Hạng	 */

        table th:nth-child(2), 
        table td:nth-child(2) { width: 15%; }  /* Tên	 */

        table th:nth-child(3), 
        table td:nth-child(3) { width: 5%; } /* ELO	 */

        table th:nth-child(4), 
        table td:nth-child(4) { width: 5%; }  /* Số trận	 */

        table th:nth-child(5), 
        table td:nth-child(5) { width: 5%; } /* Thắng	 */

        table th:nth-child(6), 
        table td:nth-child(6) { width: 5%; } /* Thua	 */

        table th:nth-child(7), 
        table td:nth-child(7) { width: 15%; } /* Tỉ lệ thắng */

        table th:nth-child(8), 
        table td:nth-child(8) { width: 35%; } /* Race */

        a {
            color: rgba(255,255,255,.8);
            text-decoration: none;
        }

    </style>
</head>
<body>
    <h1>Bảng Xếp Hạng ELO</h1>
    <div class="updated">Cập nhật lúc: <?= $last_update_fmt ?></div>
    <div class="background">
    <?php
    $rank_index = 0;
    foreach ($rank_groups as $rank => $info):
        echo "<div class='rank-label' style='background: {$info['color']}'>{$info['label']}</div>";
        echo "<table style='width:75%'>";
        echo "<tr>
                <th>Hạng</th><th>Tên</th><th>ELO</th>
                <th>Tổng</th><th>Thắng</th><th>Thua</th><th>Tỉ lệ thắng</th><th>Race</th>
            </tr>";
        for ($i = $info['range'][0]; $i < $info['range'][1] && $i < count($players); $i++):
            $p = $players[$i];
            $win_rate = $p['matches_played'] > 0 ? number_format($p['win_rate'] * 100, 2) . "%" : "0%";
            echo "<tr>";
            echo "<td>" . ($i + 1) . "</td>";
            echo "<td ><a href='player_detail.php?name=" . urlencode($p['name']) . "'>" . htmlspecialchars($p['name']) . "</a></td>";
            echo "<td>{$p['elo']}</td>";
            echo "<td>{$p['matches_played']}</td>";
            echo "<td style=\"color: green\">{$p['matches_won']}</td>";
            echo "<td style=\"color: red\">" . ($p['matches_played'] - $p['matches_won']) . "</td>";
            echo "<td>{$win_rate}</td>";

            ?> 
            <td>
                <?php 
                $races_string = $player_races[$p['name']] ?? '—';
                if ($races_string === '—' || empty($races_string)) {
                    echo '—';
                } else {
                    $races_array = explode(', ', $races_string);
                    $icons = [];
                    foreach ($races_array as $race_info) {
                        if (preg_match('/^([A-Za-z]+)\s*\((\d+)\)$/', trim($race_info), $matches)) {
                            $race = $matches[1];
                            $count = $matches[2];
                            $icons[] = race_icon($race, 40) . "$count";
                        }
                    }
                    echo implode(' ', $icons);
                }
                ?>
            </td>

            <?php
            echo "</tr>";
        endfor;
        echo "</table>";
    endforeach;
    ?>
    </div>
</body>
</html>