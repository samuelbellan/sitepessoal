<?php
require_once "../auth.php";
require_once "../conexao.php";

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cartao_id = $_POST['cartao_id'] ?? null;
    $vencimento_mes = $_POST['vencimento_mes'] ?? null;

    if (!$cartao_id || !$vencimento_mes) {
        $_SESSION['erro'] = "Dados de pagamento incompletos.";
        header('Location: index.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Verifica se ainda existem parcelas em aberto para este cartão e mês
        $stmt_total = $pdo->prepare("
            SELECT SUM(p.valor) AS total
            FROM parcelas_cartao p
            JOIN transacoes_cartao t ON p.transacao_id = t.id
            WHERE t.cartao_id = ? AND p.paga = 0 AND DATE_FORMAT(p.vencimento, '%Y-%m') = ?
        ");
        $stmt_total->execute([$cartao_id, $vencimento_mes]);
        $valor_pago = $stmt_total->fetchColumn();

        if ($valor_pago > 0) {
            // Marca como pagas
            $stmt_update_parcelas = $pdo->prepare("
                UPDATE parcelas_cartao p
                JOIN transacoes_cartao t ON p.transacao_id = t.id
                SET p.paga = 1
                WHERE t.cartao_id = ? AND p.paga = 0 AND DATE_FORMAT(p.vencimento, '%Y-%m') = ?
            ");
            $stmt_update_parcelas->execute([$cartao_id, $vencimento_mes]);

            // Lança no financeiro
            $stmt_lancamento = $pdo->prepare("
                INSERT INTO financeiro (descricao, categoria, valor, tipo, data)
                VALUES (?, ?, ?, 'saida', ?)
            ");
            $descricao = "Pagamento da fatura do cartão (id: {$cartao_id}) - " . date('m/Y', strtotime($vencimento_mes));
            $categoria = "Pagamento de Fatura";
            $data_pagamento = date('Y-m-d');
            $stmt_lancamento->execute([$descricao, $categoria, $valor_pago, $data_pagamento]);

            $_SESSION['mensagem'] = "Fatura do cartão paga com sucesso!";
        } else {
            $_SESSION['mensagem'] = "Fatura já está quitada ou não há parcelas pendentes para este mês.";
        }

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['erro'] = "Erro ao processar o pagamento: " . $e->getMessage();
    }

    header('Location: index.php');
    exit;
}

header('Location: index.php');
exit;
?>
