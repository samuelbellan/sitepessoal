<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "imageset";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e){
    die("Erro na conexão: ".$e->getMessage());
}
?>
