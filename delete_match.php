<?php
require_once 'db.php';
$pdo = connect_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM form_responses_raw WHERE id = ?");
    $stmt->execute([$id]);
    echo "✅ Đã xoá trận $id";
} else {
    echo "⛔ Thiếu ID trận đấu.";
}
?>