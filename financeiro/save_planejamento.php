<?php
require_once "../auth.php";
require_once "../conexao.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_planejamento'])) {
    $mes_ano = $_POST['mes_ano'] ?? null;
    $planejamentos = $_POST['planejado'] ?? [];

    if (!$mes_ano || !preg_match('/^\d{4}-\d{2}$/', $mes_ano)) {
        $_SESSION['erro'] = "Mês/Ano inválido para o planejamento.";
        header("Location: orcamento.php");
        exit();
    }

    if (!is_array($planejamentos) || empty($planejamentos)) {
        $_SESSION['erro'] = "Nenhum valor planejado enviado.";
        header("Location: orcamento.php?mes=$mes_ano");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Percorre cada categoria para salvar ou atualizar o valor planejado
        $stmt = $pdo->prepare("
            INSERT INTO planejamento_mensal (mes_ano, categoria, valor_planejado)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE valor_planejado = VALUES(valor_planejado)
        ");

        foreach ($planejamentos as $categoria => $valor) {
            // Conversão do valor para float padrão
            $valor_float = floatval(str_replace(',', '.', $valor));
            $stmt->execute([$mes_ano, $categoria, $valor_float]);
        }

        $pdo->commit();
        $_SESSION['mensagem'] = "Planejamento mensal salvo com sucesso para $mes_ano.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['erro'] = "Falha ao salvar planejamento: " . $e->getMessage();
    }

    header("Location: orcamento.php?mes=$mes_ano");
    exit();
} else {
    header("Location: orcamento.php");
    exit();
}
