<?php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';
$pdo = connect_db();
$message = null;

// Xử lý POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['w3vn_7'], $_POST['name'])) {
    $name = $_POST['name'];
    $new_value = intval($_POST['w3vn_7']);

    // Lấy dữ liệu cũ
    $stmt = $pdo->prepare("SELECT w3vn_7, penalized, elo FROM players WHERE name = ?");
    $stmt->execute([$name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $message = "⛔ Người chơi không tồn tại.";
    } else {
        $old_value = $row['w3vn_7'];
        $penalized = $row['penalized'];
        $elo = $row['elo'];

        $pdo->prepare("UPDATE players SET w3vn_7 = ? WHERE name = ?")->execute([$new_value, $name]);

        if ($old_value == 0 && $new_value == 1 && $penalized == 1) {
            $pdo->prepare("UPDATE players SET elo = ?, penalized = 0 WHERE name = ?")
                ->execute([$elo + 200, $name]);
            $message = "✅ Đã cập nhật $name và hoàn lại 200 ELO do chuyển sang tham gia W3VN 7.";
        } elseif ($old_value == 1 && $new_value == 0 && $penalized == 0) {
            $pdo->prepare("UPDATE players SET elo = ?, penalized = 1 WHERE name = ?")
                ->execute([$elo - 200, $name]);
            $message = "✅ Đã cập nhật $name và trừ 200 ELO do không tham gia W3VN 7.";
        } else {
            $message = "✅ Đã cập nhật người chơi $name (không thay đổi ELO).";
        }
    }
}

// Truy vấn danh sách người chơi
$stmt = $pdo->query("SELECT name, w3vn_7 FROM players ORDER BY name");
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Chỉnh sửa W3VN 7</title>
  <style>
    table { width: 60%; margin: auto; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
    form { display: inline; }
    .message { text-align: center; color: green; margin-top: 10px; }
  </style>
</head>
<body>
  <h2 style="text-align:center;">✏️ Chỉnh sửa tham gia giải W3VN 7</h2>

  <?php if ($message): ?>
    <p class="message"><?= htmlspecialchars($message) ?></p>
  <?php endif; ?>

  <table>
    <tr><th>Tên</th><th>Tham gia</th><th>Chỉnh</th></tr>
    <?php foreach ($players as $p): ?>
    <tr>
      <td><?= htmlspecialchars($p['name']) ?></td>
      <td><?= $p['w3vn_7'] ?></td>
      <td>
        <form method="post">
          <input type="hidden" name="name" value="<?= htmlspecialchars($p['name']) ?>">
          <select name="w3vn_7">
            <option value="1" <?= $p['w3vn_7'] == 1 ? 'selected' : '' ?>>1 (Có)</option>
            <option value="0" <?= $p['w3vn_7'] == 0 ? 'selected' : '' ?>>0 (Không)</option>
          </select>
          <input type="submit" value="Lưu">
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
