<?php
require_once "../auth.php";
require_once "../conexao.php";

$id = $_GET['id'];
$cartao_id = $_GET['cartao_id'];
$stmt = $pdo->prepare("DELETE FROM transacoes_cartao WHERE id = ? AND cartao_id = ?");
$stmt->execute([$id, $cartao_id]);

header("Location: transacoes_cartao.php?cartao_id=$cartao_id");
exit;
?>