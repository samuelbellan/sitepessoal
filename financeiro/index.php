<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Dashboard Financeiro";
include "header.php";

$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

$mesAtual = date('Y-m');
$stmtEntradas = $pdo->query("SELECT SUM(valor) as total FROM financeiro WHERE tipo = 'entrada'");
$totalEntradas = $stmtEntradas->fetchColumn() ?: 0;
$stmtSaidas = $pdo->query("SELECT SUM(valor) as total FROM financeiro WHERE tipo = 'saida'");
$totalSaidas = $stmtSaidas->fetchColumn() ?: 0;
$saldo = $totalEntradas - $totalSaidas;

$stmtFaturas = $pdo->prepare("
    SELECT c.nome AS cartao_nome, SUM(p.valor) AS total_fatura, MIN(p.vencimento) AS vencimento
    FROM parcelas_cartao p
    JOIN transacoes_cartao t ON p.transacao_id = t.id
    JOIN cartoes_credito c ON t.cartao_id = c.id
    WHERE p.paga = 0 AND DATE_FORMAT(p.vencimento, '%Y-%m') = ?
    GROUP BY c.id, c.nome
    ORDER BY c.nome
");
$stmtFaturas->execute([$mesAtual]);
$faturasPendentes = $stmtFaturas->fetchAll(PDO::FETCH_ASSOC);

$stmtUltimos = $pdo->query("SELECT * FROM financeiro ORDER BY data DESC, id DESC LIMIT 5");
$ultimosLancamentos = $stmtUltimos->fetchAll(PDO::FETCH_ASSOC);

$stmtOrcamento = $pdo->prepare("
    SELECT 
        SUM(valor_planejado) as total_planejado,
        (SELECT SUM(valor) FROM financeiro WHERE tipo = 'saida' AND categoria != 'Pagamento de Fatura' AND DATE_FORMAT(data, '%Y-%m') = ?) as total_real
    FROM planejamento_mensal WHERE mes_ano = ?
");
$stmtOrcamento->execute([$mesAtual, $mesAtual]);
$resumoOrcamento = $stmtOrcamento->fetch(PDO::FETCH_ASSOC);

$catsSistema = $pdo->query("SELECT nome FROM categorias_orcamento ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container py-4">

    <!-- Top bar e Ações -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <img src="https://ui-avatars.com/api/?name=Samuel+Bellan&background=0D8ABC&color=fff&rounded=true" width="50" height="50" alt="avatar">
            <div>
                <h2 class="mb-0 fw-bold">Dashboard Financeiro</h2>
                <small class="text-muted">Visão geral do financeiro</small>
            </div>
        </div>
        <div>
            <button type="button" class="btn btn-success me-1" data-bs-toggle="modal" data-bs-target="#modalNovoLancamento">
                <i class="fas fa-plus"></i> Novo Lançamento
            </button>
            <a href="orcamento.php" class="btn btn-primary me-1"><i class="fas fa-chart-pie"></i> Orçamento</a>
            <a href="categorias.php" class="btn btn-outline-primary"><i class="fas fa-tags"></i> Categorias</a>
        </div>
    </div>

    <?php if ($mensagem): ?><div class="alert alert-success"><?= $mensagem ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

    <!-- Cards de Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card shadow h-100 bg-gradient bg-success-subtle border-0">
                <div class="card-body d-flex flex-column align-items-center">
                    <i class="fas fa-arrow-circle-down fa-2x text-success"></i>
                    <div class="fs-5 mt-2 fw-semibold">Entradas</div>
                    <div class="fs-4 fw-bold">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow h-100 bg-gradient bg-danger-subtle border-0">
                <div class="card-body d-flex flex-column align-items-center">
                    <i class="fas fa-arrow-circle-up fa-2x text-danger"></i>
                    <div class="fs-5 mt-2 fw-semibold">Saídas</div>
                    <div class="fs-4 fw-bold">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow h-100 bg-gradient bg-primary-subtle border-0">
                <div class="card-body d-flex flex-column align-items-center">
                    <i class="fas fa-wallet fa-2x text-primary"></i>
                    <div class="fs-5 mt-2 fw-semibold">Saldo</div>
                    <div class="fs-4 fw-bold">R$ <?= number_format($saldo, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow h-100 bg-gradient bg-warning-subtle border-0">
                <div class="card-body d-flex flex-column align-items-center">
                    <i class="fas fa-credit-card fa-2x text-warning"></i>
                    <div class="fs-5 mt-2 fw-semibold">Faturas Pendentes</div>
                    <div class="fs-4 fw-bold"><?= count($faturasPendentes) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Linha de atalhos com destaques -->
    <div class="row my-3 g-3">
        <div class="col-md-3 col-6">
            <a href="lancamentos.php" class="text-decoration-none">
                <div class="card text-center shadow-sm h-100 hover-shadow">
                    <div class="card-body">
                        <i class="fa fa-exchange-alt fa-2x text-success mb-2"></i>
                        <div class="fw-bold">Lançamentos</div>
                        <div class="text-secondary small">Gerencie receitas e gastos</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="orcamento.php" class="text-decoration-none">
                <div class="card text-center shadow-sm h-100 hover-shadow">
                    <div class="card-body">
                        <i class="fa fa-bullseye fa-2x text-primary mb-2"></i>
                        <div class="fw-bold">Orçamento</div>
                        <div class="text-secondary small">Planeje seus meses futuros</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="categorias.php" class="text-decoration-none">
                <div class="card text-center shadow-sm h-100 hover-shadow">
                    <div class="card-body">
                        <i class="fa fa-tags fa-2x text-info mb-2"></i>
                        <div class="fw-bold">Categorias</div>
                        <div class="text-secondary small">Organize seu orçamento</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="cartoes.php" class="text-decoration-none">
                <div class="card text-center shadow-sm h-100 hover-shadow">
                    <div class="card-body">
                        <i class="fa fa-credit-card fa-2x text-warning mb-2"></i>
                        <div class="fw-bold">Cartões</div>
                        <div class="text-secondary small">Gerencie seus cartões</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Faturas Pendentes -->
    <?php if ($faturasPendentes): ?>
    <div class="card mb-4 shadow">
        <div class="card-header bg-warning-subtle fw-bold">
            <i class="fa fa-credit-card text-warning"></i> Faturas Pendentes - <?= date('m/Y') ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Cartão</th><th>Valor</th><th>Vencimento</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faturasPendentes as $fatura): ?>
                        <tr>
                            <td><?= htmlspecialchars($fatura['cartao_nome']) ?></td>
                            <td>R$ <?= number_format($fatura['total_fatura'], 2, ',', '.') ?></td>
                            <td><?= date("d/m/Y", strtotime($fatura['vencimento'])) ?></td>
                            <td><a href="cartoes.php" class="btn btn-sm btn-outline-primary"><i class="fa fa-credit-card"></i> Gerenciar</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Últimos Lançamentos -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
            <div><i class="fa fa-history text-primary"></i> Últimos Lançamentos</div>
            <a href="lancamentos.php" class="btn btn-sm btn-outline-primary"><i class="fa fa-search"></i> Ver Todos</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead>
                        <tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Valor</th><th>Tipo</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimosLancamentos as $l): ?>
                        <tr>
                            <td><?= date("d/m/Y", strtotime($l['data'])) ?></td>
                            <td><?= htmlspecialchars($l['descricao']) ?></td>
                            <td><?= htmlspecialchars($l['categoria']) ?></td>
                            <td>R$ <?= number_format($l['valor'], 2, ',', '.') ?></td>
                            <td>
                                <span class="badge <?= $l['tipo'] == 'entrada' ? 'bg-success' : 'bg-danger' ?>">
                                    <i class="fa <?= $l['tipo'] == 'entrada' ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i>
                                    <?= ucfirst($l['tipo']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para novo lançamento -->
    <div class="modal fade" id="modalNovoLancamento" tabindex="-1" aria-labelledby="modalNovoLancamentoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" action="lancamentos.php" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNovoLancamentoLabel">Novo Lançamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="novo_lancamento" value="1" />
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <input type="text" id="descricao" name="descricao" class="form-control" required />
                    </div>
                    <div class="mb-3">
                        <label for="categoria" class="form-label">Categoria</label>
                        <select id="categoria" name="categoria" class="form-select" required>
                            <option value="" selected disabled>Selecione a categoria</option>
                            <?php foreach ($catsSistema as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="valor" class="form-label">Valor (R$)</label>
                        <input type="number" step="0.01" min="0" id="valor" name="valor" class="form-control" required />
                    </div>
                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select id="tipo" name="tipo" class="form-select" required>
                            <option value="entrada">Receita</option>
                            <option value="saida">Despesa</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="data" class="form-label">Data</label>
                        <input type="date" id="data" name="data" class="form-control" value="<?= date('Y-m-d') ?>" required />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Salvar Lançamento</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include "footer.php"; ?>
