<?php
require_once 'db.php';
include 'templates/header.php';

$pdo = connect_db();

// Lấy dữ liệu
$stmt = $pdo->query("SELECT * FROM form_responses_raw ORDER BY id DESC LIMIT 100");
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$races = ["HU", "ORC", "NE", "UD"];
$maps = ["Amazonia", "Autumn Leaves", "Concealed Hill", "Echo Isles", "Hammerfall", "Last Refuge", "Northern Isles", "Terenas Stand"];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Danh sách Trận đấu</title>
    <style>
        body { 
            font-family: Arial; 
            /* background: #f8f8f8;  */
            padding: 30px; 
            background-color: #f6f8fa;
            text-align: center;
            background-image: url("https://r4.wallpaperflare.com/wallpaper/284/884/891/warcraft-iii-reforged-blizzard-entertainment-warcraft-hd-wallpaper-fa8ecbf10aeacf6fba052397794c0aa5.jpg");
            --w3-bg-glass: rgba(13, 7, 24, .9);
            background-attachment: fixed!important;
            background-size: cover!important;
            align-items: center!important;
            justify-content: center!important;
            color: rgba(255,255,255,.8);
            }
        h1 { text-align: center; }
        table { 
            /* color: black; */
            width: 100%; 
            border-collapse: collapse; 
            /* background: #fff;  */
            /* background-color: #f6f8fa;             */
            margin-top: 20px; 
            margin: 0 auto;
            background-color: rgba(20, 20, 40, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            }
        th, td { 
            /* border: 1px solid #ccc;  */
            padding: 8px; 
            text-align: center; }
        th { 
            /* background: #eee;  */
            }

        
    </style>
</head>
<body>
    <h1>Danh sách Trận đấu</h1>
    <table>
        <tr>
            <th>ID</th><th>Thời gian</th><th>Người chơi 1</th><th>Race 1</th>
            <th>Bản đồ</th><th>Kết quả</th><th>Người chơi 2</th><th>Race 2</th>
        </tr>
        <?php foreach ($matches as $m): ?>
        <tr>
            <td><?= $m['id'] ?></td>
            <td><?= $m['timestamp'] ?></td>
            <td><?= htmlspecialchars($m['player1']) ?></td>
            <td><?= htmlspecialchars($m['race1']) ?></td>
            <td><?= htmlspecialchars($m['map']) ?></td>
            <td><?= $m['result'] == 1 ? 'Thắng' : 'Thua' ?></td>
            <td><?= htmlspecialchars($m['player2']) ?></td>
            <td><?= htmlspecialchars($m['race2']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
