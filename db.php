

<?php

// function connect_db() {
//     return new PDO("mysql:host=localhost;dbname=elo;charset=utf8mb4", "root", "", [
//         PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
//     ]);
// }

function connect_db() {
    return new PDO("mysql:host=sql213.infinityfree.com;dbname=if0_39472640_elo;charset=utf8mb4", "if0_39472640", "RCGbjVKkzMih", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
}
?>
