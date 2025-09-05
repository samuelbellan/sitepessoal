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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container py-4">

    <!-- Topbar e ações -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold"><i class="fa fa-credit-card text-warning"></i> Gerenciar Cartões de Crédito</h2>
            <small class="text-muted">Controle todos os seus cartões e visualize faturas</small>
        </div>
        <div>
            <a href="adicionar_cartao.php" class="btn btn-success"><i class="fa fa-plus"></i> Novo Cartão</a>
            <a href="faturas_todos.php" class="btn btn-info ms-2"><i class="fa fa-file-invoice-dollar"></i> Faturas de Todos</a>
            <a href="index.php" class="btn btn-outline-primary ms-2"><i class="fa fa-arrow-left"></i> Voltar Dashboard</a>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-2 g-4 mb-3">
        <?php if ($cartoes): ?>
            <?php foreach ($cartoes as $c): ?>
                <div class="col">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title mb-1">
                                <i class="fa fa-credit-card text-warning"></i>
                                <?= htmlspecialchars($c['nome']) ?>
                            </h5>
                            <div class="mb-1 text-secondary small">Limite: <b>R$ <?= number_format($c['limite'], 2, ',', '.') ?></b></div>
                            <div class="mb-1 text-secondary small">Fechamento: <b><?= $c['dia_fechamento'] ?></b> &nbsp; &bull; &nbsp; Vencimento: <b><?= $c['dia_vencimento'] ?></b></div>
                        </div>
                        <div class="card-footer d-flex gap-2">
                            <a href="transacoes_cartao.php?cartao_id=<?= $c['id'] ?>" class="btn btn-sm btn-info"><i class="fa fa-list"></i> Transações</a>
                            <a href="editar_cartao.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-warning"><i class="fa fa-edit"></i> Editar</a>
                            <a href="remover_cartao.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Confirma remoção? Todas as transações serão excluídas.')"><i class="fa fa-trash"></i> Remover</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12"><div class="alert alert-info text-center mb-0">Nenhum cartão cadastrado.</div></div>
        <?php endif; ?>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header bg-warning-subtle text-dark fw-semibold">
            <i class="fa fa-exclamation-circle text-warning"></i> Faturas Pendentes do Mês (<?= date('m/Y') ?>)
        </div>
        <div class="card-body p-2">
            <?php if (count($faturasPendentes)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
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
                            <td>
                                <i class="fa fa-credit-card text-warning"></i>
                                <?= htmlspecialchars($fatura['cartao_nome']) ?>
                            </td>
                            <td>R$ <?= number_format($fatura['total_fatura'], 2, ',', '.') ?></td>
                            <td><?= date('d/m/Y', strtotime($fatura['vencimento'])) ?></td>
                            <td>
                                <form method="post" action="pagar_fatura.php" onsubmit="return confirm('Confirma o pagamento da fatura de <?= htmlspecialchars($fatura['cartao_nome']) ?>?');" style="display:inline">
                                    <input type="hidden" name="cartao_id" value="<?= $fatura['cartao_id'] ?>">
                                    <input type="hidden" name="mes" value="<?= date('Y-m') ?>">
                                    <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-check"></i> Pagar Fatura</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-success text-center mb-0">Não há faturas pendentes no mês atual.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'footer.php'; ?>
