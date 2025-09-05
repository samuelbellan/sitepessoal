<?php
require_once "../auth.php";
require_once "../conexao.php";

$id = $_GET['id'] ?? null;
$cartao_id = $_GET['cartao_id'] ?? null;
$mes = $_GET['mes'] ?? date('Y-m');

if (!$id) {
    $_SESSION['erro'] = "Parcela não informada.";
    header("Location: transacoes_cartao.php?cartao_id=$cartao_id&mes=$mes");
    exit;
}

$stmt = $pdo->prepare("DELETE FROM parcelas_cartao WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['mensagem'] = "Parcela removida com sucesso!";
header("Location: transacoes_cartao.php?cartao_id=$cartao_id&mes=$mes");
exit;
?>
