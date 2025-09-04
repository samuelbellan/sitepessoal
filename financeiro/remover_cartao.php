<?php
require_once "../auth.php";
require_once "../conexao.php";

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM cartoes_credito WHERE id = ?");
$stmt->execute([$id]);

header("Location: cartoes.php");
exit;
?>