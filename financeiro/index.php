<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Dashboard Financeiro";
include "header.php";

// Mensagens
$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

// Dados básicos para dashboard
$mesAtual = date('Y-m');

// Totais gerais
$stmtEntradas = $pdo->query("SELECT SUM(valor) as total FROM financeiro WHERE tipo = 'entrada'");
$totalEntradas = $stmtEntradas->fetchColumn() ?: 0;

$stmtSaidas = $pdo->query("SELECT SUM(valor) as total FROM financeiro WHERE tipo = 'saida'");
$totalSaidas = $stmtSaidas->fetchColumn() ?: 0;

$saldo = $totalEntradas - $totalSaidas;

// Faturas pendentes este mês
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

// Últimos lançamentos
$stmtUltimos = $pdo->query("SELECT * FROM financeiro ORDER BY data DESC, id DESC LIMIT 5");
$ultimosLancamentos = $stmtUltimos->fetchAll(PDO::FETCH_ASSOC);

// Orçamento do mês (resumo)
$stmtOrcamento = $pdo->prepare("
    SELECT 
        SUM(valor_planejado) as total_planejado,
        (SELECT SUM(valor) FROM financeiro WHERE tipo = 'saida' AND categoria != 'Pagamento de Fatura' AND DATE_FORMAT(data, '%Y-%m') = ?) as total_real
    FROM planejamento_mensal WHERE mes_ano = ?
");
$stmtOrcamento->execute([$mesAtual, $mesAtual]);
$resumoOrcamento = $stmtOrcamento->fetch(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Dashboard Financeiro</h1>
        <div>
            <a href="lancamentos.php" class="btn btn-success">+ Novo Lançamento</a>
            <a href="orcamento.php" class="btn btn-primary ms-2">Orçamento</a>
            <a href="categorias.php" class="btn btn-outline-primary ms-2">Categorias</a>
        </div>
    </div>

    <?php if ($mensagem): ?><div class="alert alert-success"><?= $mensagem ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

    <!-- Cards de Resumo -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-bg-success">
                <div class="card-body">
                    <h5>Total Entradas</h5>
                    <p class="fs-4">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-danger">
                <div class="card-body">
                    <h5>Total Saídas</h5>
                    <p class="fs-4">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-primary">
                <div class="card-body">
                    <h5>Saldo</h5>
                    <p class="fs-4">R$ <?= number_format($saldo, 2, ',', '.') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-warning">
                <div class="card-body">
                    <h5>Faturas Pendentes</h5>
                    <p class="fs-4"><?= count($faturasPendentes) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Menu de Navegação -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fa fa-plus-circle fa-3x text-success mb-3"></i>
                    <h5>Lançamentos</h5>
                    <p>Adicionar receitas e despesas</p>
                    <a href="lancamentos.php" class="btn btn-success">Acessar</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fa fa-chart-pie fa-3x text-primary mb-3"></i>
                    <h5>Orçamento</h5>
                    <p>Planejamento mensal</p>
                    <a href="orcamento.php" class="btn btn-primary">Acessar</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fa fa-tags fa-3x text-info mb-3"></i>
                    <h5>Categorias</h5>
                    <p>Gerenciar categorias e subcategorias</p>
                    <a href="categorias.php" class="btn btn-info">Acessar</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fa fa-credit-card fa-3x text-warning mb-3"></i>
                    <h5>Cartões</h5>
                    <p>Gerenciar cartões de crédito</p>
                    <a href="cartoes.php" class="btn btn-warning">Acessar</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Faturas Pendentes -->
    <?php if ($faturasPendentes): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5>Faturas Pendentes - <?= date('m/Y') ?></h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr><th>Cartão</th><th>Valor</th><th>Vencimento</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faturasPendentes as $fatura): ?>
                        <tr>
                            <td><?= htmlspecialchars($fatura['cartao_nome']) ?></td>
                            <td>R$ <?= number_format($fatura['total_fatura'], 2, ',', '.') ?></td>
                            <td><?= date("d/m/Y", strtotime($fatura['vencimento'])) ?></td>
                            <td><a href="cartoes.php" class="btn btn-sm btn-outline-primary">Gerenciar</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Últimos Lançamentos -->
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h5>Últimos Lançamentos</h5>
            <a href="lancamentos.php" class="btn btn-sm btn-outline-primary">Ver Todos</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
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
                            <td><span class="badge <?= $l['tipo'] == 'entrada' ? 'bg-success' : 'bg-danger' ?>"><?= ucfirst($l['tipo']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
