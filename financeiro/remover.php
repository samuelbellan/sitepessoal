<?php
require_once "../auth.php";
require_once "../conexao.php";

if (!isset($_GET['id'])) {
    die("ID não informado.");
}
$id = (int) $_GET['id'];

$stmt = $pdo->prepare("DELETE FROM financeiro WHERE id = ?");
$stmt->execute([$id]);

header("Location: index.php");
exit;
