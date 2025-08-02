<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
$pdo = connect_db();
include 'templates/header.php';

$races = ['HU', 'ORC', 'NE', 'UD'];
$race_names = [
    'HU' => 'Human',
    'ORC' => 'Orc', 
    'NE' => 'Night Elf',
    'UD' => 'Undead'
];

$selected_map = $_GET['map'] ?? '';
$params = [];
$query = "SELECT race1, race2, result FROM form_responses_raw WHERE race1 IS NOT NULL AND race2 IS NOT NULL";
if ($selected_map) {
    $query .= " AND map = ?";
    $params[] = $selected_map;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Khởi tạo ma trận
$matrix = [];
foreach ($races as $r1) {
    foreach ($races as $r2) {
        $matrix[$r1][$r2] = ['played' => 0, 'won' => 0];
    }
}

// Khởi tạo thống kê tổng thể
$overall_stats = [];
foreach ($races as $race) {
    $overall_stats[$race] = ['played' => 0, 'won' => 0];
}

// Xử lý dữ liệu matches
foreach ($matches as $m) {
    $race1 = strtoupper(trim($m['race1']));
    $race2 = strtoupper(trim($m['race2'])); 
    $result = (int)$m['result'];
    
    if (!in_array($race1, $races) || !in_array($race2, $races)) continue;
    
    // Cập nhật số trận đấu cho cả hai hướng
    $matrix[$race1][$race2]['played']++;
    $matrix[$race2][$race1]['played']++;
    
    // Cập nhật thống kê tổng thể
    $overall_stats[$race1]['played']++;
    $overall_stats[$race2]['played']++;
    
    // Cập nhật kết quả thắng
    if ($result == 1) {
        // race1 thắng
        $matrix[$race1][$race2]['won']++;
        $overall_stats[$race1]['won']++;
    } elseif ($result == 0) {
        // race2 thắng  
        $matrix[$race2][$race1]['won']++;
        $overall_stats[$race2]['won']++;
    }
}


$stmt = $pdo->query("
    SELECT player, MAX(date) as latest_date
    FROM elo_history_raw
    GROUP BY player
");

$latest_dates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Lấy MMR mới nhất cho từng player
$placeholders = rtrim(str_repeat('?,', count($latest_dates)), ',');
$stmt = $pdo->prepare("
    SELECT player, elo
    FROM elo_history_raw
    WHERE (player, date) IN (" .
    implode(',', array_fill(0, count($latest_dates), '(?, ?)')) .
")");

$params = [];
foreach ($latest_dates as $player => $date) {
    $params[] = $player;
    $params[] = $date;
}

$stmt->execute($params);
$latest_elos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lưu elo vào JS
$elos = array_map(fn($row) => (int)$row['elo'], $latest_elos);

// Lấy race gần nhất của mỗi người chơi
$race_stmt = $pdo->query("
    SELECT player, race FROM (
        SELECT player1 AS player, race1 AS race, timestamp FROM form_responses_raw
        UNION ALL
        SELECT player2 AS player, race2 AS race, timestamp FROM form_responses_raw
    ) AS combined
    ORDER BY timestamp DESC
");
$player_race = [];
while ($row = $race_stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!isset($player_race[$row['player']])) {
        $player_race[$row['player']] = $row['race'];
    }
}

// Lấy MMR mới nhất của từng người
$elo_stmt = $pdo->query("
    SELECT player, elo FROM (
        SELECT player, elo, ROW_NUMBER() OVER (PARTITION BY player ORDER BY date DESC, id DESC) AS rn
        FROM elo_history_raw
    ) sub WHERE rn = 1
");

$mmr_by_race = ['HU'=>[], 'Orc'=>[], 'NE'=>[], 'UD'=>[]];
while ($row = $elo_stmt->fetch(PDO::FETCH_ASSOC)) {
    $player = $row['player'];
    if (!isset($player_race[$player])) continue;

    $race = $player_race[$player];
    if (!in_array($race, ['HU','Orc','NE','UD'])) continue;

    $mmr_by_race[$race][] = (int)$row['elo'];
}

function makeHistogram($data, $min = 1200, $max = 2000, $step = 50) {
    $bins = array_fill_keys(range($min, $max, $step), 0);
    foreach ($data as $value) {
        foreach (array_keys($bins) as $bin) {
            if ($value >= $bin && $value < $bin + $step) {
                $bins[$bin]++;
                break;
            }
        }
    }
    return $bins;
}

$bin_labels = range(1200, 1950, 50); // X-axis
$hist_hu   = makeHistogram($mmr_by_race['HU']);
$hist_orc  = makeHistogram($mmr_by_race['Orc']);
$hist_ne   = makeHistogram($mmr_by_race['NE']);
$hist_ud   = makeHistogram($mmr_by_race['UD']);

$map_counts_stmt = $pdo->query("
    SELECT map, COUNT(*) as count
    FROM form_responses_raw
    WHERE TRIM(map) != ''
    GROUP BY map
    ORDER BY count DESC
");
$map_counts = $map_counts_stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [map => count]

$rows = $pdo->query("SELECT 
        fr.timestamp,
        fr.race1, fr.race2,
        (
            SELECT eh1.elo 
            FROM elo_history_raw eh1 
            WHERE eh1.player = fr.player1 
            AND eh1.date < fr.timestamp 
            ORDER BY eh1.date DESC, eh1.id DESC 
            LIMIT 1
        ) AS p1_elo,
        (
            SELECT eh2.elo 
            FROM elo_history_raw eh2 
            WHERE eh2.player = fr.player2 
            AND eh2.date < fr.timestamp 
            ORDER BY eh2.date DESC, eh2.id DESC 
            LIMIT 1
        ) AS p2_elo
    FROM form_responses_raw fr
    WHERE fr.race1 IS NOT NULL AND fr.race2 IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

// Gom elo theo race
$elo_by_race = ['HU' => [], 'ORC' => [], 'NE' => [], 'UD' => []];

foreach ($rows as $row) {
    if (isset($row['p1_elo'], $row['race1']) && $row['race1'] !== '') {
        $race = strtoupper(trim($row['race1'])); // chuyển về chữ hoa
        $elo = (int)$row['p1_elo'];
        if (isset($elo_by_race[$race])) {
            $elo_by_race[$race][] = $elo;
        }
    }
    if (isset($row['p2_elo'], $row['race2']) && $row['race2'] !== '') {
        $race = strtoupper(trim($row['race2'])); // chuyển về chữ hoa
        $elo = (int)$row['p2_elo'];
        if (isset($elo_by_race[$race])) {
            $elo_by_race[$race][] = $elo;
        }
    }
}


?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Race vs Race Winrates</title>
        <style>
            body {
                background-color: #0e0e1a;
                font-family: Arial, sans-serif;
                color: white;
                padding: 20px;
                margin: 0;
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
                text-align: center;
                margin-bottom: 30px;
            }
            
            form {
                text-align: center;
                margin-bottom: 30px;
            }
            
            select {
                padding: 8px 15px;
                font-size: 16px;
                border-radius: 5px;
                border: 1px solid #444;
                background-color: #222;
                color: white;
            }
            
            table {
                border-collapse: collapse;
                margin: 0 auto;
                background-color: rgba(20, 20, 40, 0.8);
                border: 1px solid rgba(255,255,255,0.1);
            }
            
            th, td {
                padding: 15px 20px;
                border: 1px solid rgba(255,255,255,0.1);
                text-align: center;
                min-width: 100px;
            }
            
            th {
                background-color: rgba(40, 40, 60, 0.8);
                font-weight: bold;
            }
            
            .race-header {
                background-color: rgba(60, 60, 80, 0.8);
                font-weight: bold;
            }
            
            .green { 
                color: #4CAF50; 
                font-weight: bold;
            }
            
            .red { 
                color: #f44336; 
                font-weight: bold;
            }
            
            .neutral {
                color: #ccc;
            }
            
            .diagonal {
                background-color: rgba(80, 80, 100, 0.3);
                color: #666;
            }

            canvas {
                background-color: rgba(20, 20, 40, 0.8);
                
            }
        </style>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    </head>
    
    <body>
        <h1>Thống kê tỉ lệ thắng<?= $selected_map ? " trên bản đồ \"$selected_map\"" : "" ?></h1>
        
        <form method="get">
            <select name="map" onchange="this.form.submit()">
                <option value="">Tất cả bản đồ</option>
                <?php
                $maps = $pdo->query("SELECT DISTINCT map FROM form_responses_raw WHERE map IS NOT NULL ORDER BY map")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($maps as $map_name):
                ?>
                <option value="<?= htmlspecialchars($map_name) ?>" <?= $map_name === $selected_map ? 'selected' : '' ?>>
                    <?= htmlspecialchars($map_name) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>

        <table>
            <tr>
                <th></th>
                <?php foreach ($races as $col): ?>
                <th>vs <?= $race_names[$col] ?></th>
                <?php endforeach; ?>
                <th>Overall Winrate</th>
                <th>Số trận</th>

            </tr>
            
            <?php foreach ($races as $row): ?>
            <tr>
                <th class="race-header"><?= $race_names[$row] ?></th>
                <?php foreach ($races as $col): ?>
                <td <?= $row === $col ? 'class="diagonal"' : '' ?>>
                    <?php
                    if ($row === $col) {
                        echo "–";
                    } else {
                        $data = $matrix[$row][$col];
                        if ($data['played'] === 0) {
                            echo "–";
                        } else {
                            $rate = ($data['won'] / $data['played']) * 100;
                            $color = $rate > 50 ? 'green' : ($rate < 50 ? 'red' : 'neutral');
                            echo "<span class='$color'>" . number_format($rate, 1) . "%</span>";
                        }
                    }
                    ?>
                </td>
                <?php endforeach; ?>
                <td>
                    <?php
                    if ($overall_stats[$row]['played'] === 0) {
                        echo "–";
                    } else {
                        $overall_rate = ($overall_stats[$row]['won'] / $overall_stats[$row]['played']) * 100;
                        $color = $overall_rate > 50 ? 'green' : ($overall_rate < 50 ? 'red' : 'neutral');
                        echo "<span class='$color'>" . number_format($overall_rate, 1) . "%</span>";
                    }
                    ?>
                </td>
                <td>
                    <?= $overall_stats[$row]['played'] ?>
                </td>

            </tr>
            <?php endforeach; ?>
            
        </table>

        <h1>Histogram Elo theo Race</h1>

        <canvas id="eloHistogram"></canvas>

        <script>
        const eloByRace = <?= json_encode($elo_by_race) ?>;
        const colors = {
            HU: 'gold',
            ORC: 'crimson',
            NE: 'Chartreuse',
            UD: 'DarkMagenta',
            Unknown: 'rgba(201, 203, 207, 0.5)'
        };

        // Chuyển elo thành bin
        function histogramData(data, binSize = 50, maxElo = 3000, minElo = 1000) {
            const bins = [];
            for (let i = minElo; i <= maxElo; i += binSize) bins.push(0);
            data.forEach(elo => {
                let idx = Math.floor((elo - minElo) / binSize);
                if (idx >= 0 && idx < bins.length) bins[idx]++;
            });
            return bins;
        }

        const labels = [];
        for (let i = 1000; i <= 2100; i += 50) labels.push(i + '-' + (i+49));

        const datasets = Object.keys(eloByRace).map(race => ({
            label: race,
            data: histogramData(eloByRace[race]),
            backgroundColor: colors[race]
        }));

        new Chart(document.getElementById('eloHistogram'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                // plugins: {
                //     title: {
                //         display: true,
                //         text: 'Histogram Elo theo Race'
                //     }
                // },
                responsive: true,
                scales: {
                    x: {
                        // stacked: true,
                        title: {
                            display: true,
                            text: 'Elo bins'
                        }
                    },
                    y: {
                        // stacked: true,
                        title: {
                            display: true,
                            text: 'Số lượng người chơi'
                        }
                    }
                }
            }
        });
        </script>

        <h1>MMR Distribution</h1>
        <canvas id="mmrChart" height="100"></canvas>

        <h1 style="text-align:center;">Số trận theo từng bản đồ</h1>
        <div style="max-width: 800px; margin: auto; background-color: rgba(20, 20, 40, 0.8);">
            <canvas id="mapChart" height="500"></canvas>
        </div>

        
        <script>
            const mmrs = <?= json_encode($elos) ?>;

            // Tạo bins
            const binSize = 50;
            const minMMR = 1000;
            const maxMMR = 2100;

            const bins = [], counts = [], cumulative = [];
            for (let i = minMMR; i <= maxMMR; i += binSize) {
                bins.push(`>${i}`);
                counts.push(0);
            }

            mmrs.forEach(mmr => {
                const index = Math.floor((mmr - minMMR) / binSize);
                if (index >= 0 && index < counts.length) counts[index]++;
            });

            let total = 0;
            for (let i = 0; i < counts.length; i++) {
                total += counts[i];
                cumulative.push(total);
            }

            const ctx = document.getElementById('mmrChart').getContext('2d');
            const mmrChart = new Chart(ctx, {
                data: {
                    labels: bins,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'MMR',
                            data: counts,
                            backgroundColor: 'rgba(0, 160, 255, 0.5)',
                            borderColor: 'rgba(0, 160, 255, 1)',
                            borderWidth: 1,
                            yAxisID: 'y1'
                        },
                        {
                            type: 'line',
                            label: 'Cumulative',
                            data: cumulative,
                            borderColor: 'lime',
                            backgroundColor: 'lime',
                            yAxisID: 'y2',
                            tension: 0.3,
                            fill: false,
                            pointRadius: 2
                        }
                    ]
                },
                options: {
                    scales: {
                        y1: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            title: { display: true, text: 'Players in Bin' }
                        },
                        y2: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            title: { display: true, text: 'Cumulative Players' }
                        }
                    },
                    plugins: {
                        legend: { labels: { color: 'white' } }
                    }
                }
            });
        </script>
        
        <script>
            const labels = <?= json_encode(array_map(fn($v) => "$v-" . ($v + 49), $bin_labels)); ?>;

            const data = {
                labels: labels,
                datasets: [
                    {
                        label: 'HU',
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        data: <?= json_encode(array_values($hist_hu)); ?>
                    },
                    {
                        label: 'Orc',
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        data: <?= json_encode(array_values($hist_orc)); ?>
                    },
                    {
                        label: 'NE',
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        data: <?= json_encode(array_values($hist_ne)); ?>
                    },
                    {
                        label: 'UD',
                        backgroundColor: 'rgba(153, 102, 255, 0.6)',
                        data: <?= json_encode(array_values($hist_ud)); ?>
                    }
                ]
            };

            new Chart(document.getElementById('mmrHistogram'), {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Histogram MMR mới nhất theo race'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        x: {
                            stacked: true
                        },
                        y: {
                            stacked: true,
                            title: {
                                display: true,
                                text: 'Số người chơi'
                            }
                        }
                    }
                }
            });
            

        </script>

        <script>
        
            const mapLabels = <?= json_encode(array_keys($map_counts)) ?>;
            const mapCounts = <?= json_encode(array_values($map_counts)) ?>;

            const map_ctx = document.getElementById('mapChart').getContext('2d');
            new Chart(map_ctx, {
                type: 'bar',
                data: {
                    labels: mapLabels,
                    datasets: [{
                        label: 'Số trận',
                        data: mapCounts,
                        backgroundColor: 'rgba(0, 123, 255, 0.4)',
                        borderColor: 'rgba(0, 123, 255, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 10,
                            bottom: 10
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: 'white',
                                maxRotation: 45,
                                minRotation: 45,
                                autoSkip: false
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: 'white'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: 'white'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ` ${context.parsed.y} trận`;
                                }
                            }
                        }
                    }
                }
            });

        </script>

    </body>
</html>