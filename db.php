<?php
function connect_db() {
    return new PDO("mysql:host=localhost;dbname=elo;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
}
?>