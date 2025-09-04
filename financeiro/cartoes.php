<?php
require_once "../auth.php";
require_once "../conexao.php";

$title = "Gerenciar Cartões de Crédito";

// Data do mês atual para filtro
$mesAtual = date('Y-m');

// Buscar cartões
$stmt = $pdo->query("SELECT * FROM cartoes_credito ORDER BY nome");
$cartoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar faturas pendentes agrupadas por cartão e mês
$stmtFaturas = $pdo->prepare("
    SELECT c.id AS cartao_id, c.nome AS cartao_nome,
           SUM(pc.valor) AS total_fatura,
           MIN(pc.vencimento) AS vencimento
    FROM parcelas_cartao pc
    JOIN transacoes_cartao tc ON pc.transacao_id = tc.id
    JOIN cartoes_credito c ON tc.cartao_id = c.id
    WHERE pc.paga = 0 AND DATE_FORMAT(pc.vencimento, '%Y-%m') = ?
    GROUP BY c.id, c.nome
    ORDER BY c.nome
");
$stmtFaturas->execute([$mesAtual]);
$faturasPendentes = $stmtFaturas->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Gerenciar Cartões de Crédito</h1>
    <div>
        <a href="adicionar_cartao.php" class="btn btn-success">+ Adicionar Cartão</a>
        <a href="faturas_todos.php" class="btn btn-info ms-2">Ver Faturas de Todos</a>
        <a href="index.php" class="btn btn-secondary ms-2">Voltar ao Controle Financeiro</a>
    </div>
</div>

<div class="table-responsive mb-5">
    <table class="table table-striped table-bordered align-middle">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Limite</th>
                <th>Dia Fechamento</th>
                <th>Dia Vencimento</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($cartoes): ?>
            <?php foreach ($cartoes as $c): ?>
                <tr>
                    <td><?= $c['id'] ?></td>
                    <td><?= htmlspecialchars($c['nome']) ?></td>
                    <td>R$ <?= number_format($c['limite'], 2, ',', '.') ?></td>
                    <td><?= $c['dia_fechamento'] ?></td>
                    <td><?= $c['dia_vencimento'] ?></td>
                    <td>
                        <a href="transacoes_cartao.php?cartao_id=<?= $c['id'] ?>" class="btn btn-sm btn-info">Transações</a>
                        <a href="editar_cartao.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                        <a href="remover_cartao.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Confirma remoção? Todas as transações serão excluídas.')">Remover</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6" class="text-center">Nenhum cartão cadastrado.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<h2>Faturas Pendentes do Mês (<?= date('m/Y') ?>)</h2>

<?php if (count($faturasPendentes)): ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
            <tr>
                <th>Cartão</th>
                <th>Valor Total</th>
                <th>Vencimento</th>
                <th>Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($faturasPendentes as $fatura): ?>
                <tr>
                    <td><?= htmlspecialchars($fatura['cartao_nome']) ?></td>
                    <td>R$ <?= number_format($fatura['total_fatura'], 2, ',', '.') ?></td>
                    <td><?= date('d/m/Y', strtotime($fatura['vencimento'])) ?></td>
                    <td>
                        <form method="post" action="pagar_fatura.php" onsubmit="return confirm('Confirma o pagamento da fatura de <?= htmlspecialchars($fatura['cartao_nome']) ?>?');" style="display:inline">
                            <input type="hidden" name="cartao_id" value="<?= $fatura['cartao_id'] ?>">
                            <input type="hidden" name="mes" value="<?= date('Y-m') ?>">
                            <button type="submit" class="btn btn-sm btn-success">Pagar Fatura</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p>Não há faturas pendentes no mês atual.</p>
<?php endif; ?>

<?php include 'footer.php'; ?>
