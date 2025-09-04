<?php
require_once "../auth.php";
require_once "../conexao.php";

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cartao_id = $_POST['cartao_id'] ?? null;
    $mes = $_POST['mes'] ?? null;

    if (!$cartao_id || !$mes) {
        $_SESSION['erro'] = "Dados insuficientes para o pagamento.";
        header('Location: cartoes.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Verifica se há parcelas abertas
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM parcelas_cartao pc
                                    JOIN transacoes_cartao tc ON pc.transacao_id = tc.id
                                    WHERE tc.cartao_id = ? AND pc.paga = 0 AND DATE_FORMAT(pc.vencimento, '%Y-%m') = ?");
        $stmtCheck->execute([$cartao_id, $mes]);
        $count = $stmtCheck->fetchColumn();

        if ($count == 0) {
            $_SESSION['mensagem'] = "Nenhuma parcela pendente para pagamento.";
            $pdo->commit();
            header('Location: cartoes.php');
            exit;
        }

        // Marca parcelas como pagas
        $stmtUpdate = $pdo->prepare("UPDATE parcelas_cartao pc
                                     JOIN transacoes_cartao tc ON pc.transacao_id = tc.id
                                     SET pc.paga = 1
                                     WHERE tc.cartao_id = ? AND pc.paga = 0 AND DATE_FORMAT(pc.vencimento, '%Y-%m') = ?");
        $stmtUpdate->execute([$cartao_id, $mes]);

        // Soma valor pago para lançamento financeiro
        $stmtGetValue = $pdo->prepare("SELECT SUM(pc.valor) FROM parcelas_cartao pc
                                      JOIN transacoes_cartao tc ON pc.transacao_id = tc.id
                                      WHERE tc.cartao_id = ? AND DATE_FORMAT(pc.vencimento, '%Y-%m') = ?");
        $stmtGetValue->execute([$cartao_id, $mes]);
        $totalPago = $stmtGetValue->fetchColumn();

        // Registrar no financeiro
        $stmtFin = $pdo->prepare("INSERT INTO financeiro (descricao, categoria, valor, tipo, data) VALUES (?, ?, ?, 'saida', ?)");
        $descricao = "Pagamento fatura cartão ID {$cartao_id} - " . date('m/Y', strtotime($mes . '-01'));
        $dataPagamento = date('Y-m-d');
        $stmtFin->execute([$descricao, 'Pagamento de Fatura', $totalPago, $dataPagamento]);

        $pdo->commit();

        $_SESSION['mensagem'] = "Fatura do cartão paga com sucesso.";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['erro'] = "Erro: " . $e->getMessage();
    }

    header('Location: cartoes.php');
    exit;
}

header('Location: cartoes.php');
exit;
