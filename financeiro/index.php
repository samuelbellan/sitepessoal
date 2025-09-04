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

// Obtém as categorias para o select
$catsSistema = $pdo->query("SELECT nome FROM categorias_orcamento ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Dashboard Financeiro</h1>
    <div>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNovoLancamento">
            + Novo Lançamento
        </button>
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
