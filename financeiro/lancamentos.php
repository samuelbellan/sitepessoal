<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Lançamentos";
include 'header.php';

$mesSelecionado = $_GET['mes'] ?? date('Y-m');
$mesOrcamento = $_GET['mes_orcamento'] ?? $mesSelecionado;

$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

$catsSistema = $pdo->query("SELECT nome FROM categorias_orcamento")->fetchAll(PDO::FETCH_COLUMN);

// Processar novo lançamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_lancamento'])) {
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria = $_POST['categoria'] ?? '';
    $valor = str_replace(',', '.', $_POST['valor'] ?? 0);
    $tipo = $_POST['tipo'] ?? '';
    $data = $_POST['data'] ?? '';

    if (!in_array($categoria, $catsSistema)) {
        $_SESSION['erro'] = "Selecione uma categoria válida.";
        header("Location: lancamentos.php?mes=" . urlencode($mesSelecionado) . "&mes_orcamento=" . urlencode($mesOrcamento));
        exit;
    }
    if (empty($descricao) || !is_numeric($valor) || $valor <= 0 || !in_array($tipo, ['entrada', 'saida']) || empty($data)) {
        $_SESSION['erro'] = "Preencha os campos corretamente.";
        header("Location: lancamentos.php?mes=" . urlencode($mesSelecionado) . "&mes_orcamento=" . urlencode($mesOrcamento));
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO financeiro (descricao, categoria, valor, tipo, data) VALUES (?, ?, ?, ?, ?)");
    try {
        $stmt->execute([$descricao, $categoria, $valor, $tipo, $data]);
        $_SESSION['mensagem'] = "Lançamento adicionado com sucesso.";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao salvar.";
    }
    header("Location: lancamentos.php?mes=" . urlencode($mesSelecionado) . "&mes_orcamento=" . urlencode($mesOrcamento));
    exit;
}

$stmtPlan = $pdo->prepare("SELECT categoria, valor_planejado FROM planejamento_mensal WHERE mes_ano = ?");
$stmtPlan->execute([$mesOrcamento]);
$planejamento = $stmtPlan->fetchAll(PDO::FETCH_KEY_PAIR);

$stmtCats = $pdo->query("SELECT nome, tipo FROM categorias_orcamento ORDER BY nome");
$catsData = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
$catTipoMap = [];
foreach ($catsData as $c) {
    $catTipoMap[$c['nome']] = $c['tipo'];
}

$catsPlanejamento = array_keys($planejamento);

$catsPorTipo = ['receita' => [], 'despesa' => []];
foreach ($catsPlanejamento as $nomeCat) {
    if (isset($catTipoMap[$nomeCat])) {
        $tipo = $catTipoMap[$nomeCat];
    } else {
        if (strpos($nomeCat, ' - ') !== false) {
            $mae = explode(' - ', $nomeCat)[0];
            $tipo = $catTipoMap[$mae] ?? 'despesa';
        } else {
            $tipo = 'despesa';
        }
    }
    $catsPorTipo[$tipo][] = $nomeCat;
}
$catsPorTipo['receita'] = array_unique($catsPorTipo['receita']);
$catsPorTipo['despesa'] = array_unique($catsPorTipo['despesa']);

$stmtLanc = $pdo->prepare("SELECT categoria, tipo, SUM(valor) AS total FROM financeiro WHERE DATE_FORMAT(data,'%Y-%m')=? GROUP BY categoria, tipo");
$stmtLanc->execute([$mesSelecionado]);
$totaisLanc = $stmtLanc->fetchAll(PDO::FETCH_ASSOC);

$totaisFinanceiro = [];
foreach ($totaisLanc as $item) {
    $totaisFinanceiro[$item['tipo']][$item['categoria']] = $item['total'];
}

$stmtParcelas = $pdo->prepare("
    SELECT c.nome AS cartao_nome, SUM(pc.valor) AS total_parcelas
    FROM parcelas_cartao pc
    JOIN transacoes_cartao tc ON pc.transacao_id=tc.id
    JOIN cartoes_credito c ON tc.cartao_id=c.id
    WHERE DATE_FORMAT(pc.vencimento, '%Y-%m')=?
    GROUP BY c.nome
");
$stmtParcelas->execute([$mesSelecionado]);
$parcelasCartao = $stmtParcelas->fetchAll(PDO::FETCH_ASSOC);

function montarLinhas(array $categorias, array $planejado, array $real){
    $linhas = [];
    foreach($categorias as $cat){
        $p = $planejado[$cat] ?? 0;
        $r = $real[$cat] ?? 0;
        $linhas[] = [
            'categoria' => $cat,
            'planejado' => $p,
            'real' => $r,
            'diferenca' => $p - $r
        ];
    }
    return $linhas;
}

$linhasReceitas = montarLinhas($catsPorTipo['receita'], $planejamento, $totaisFinanceiro['entrada'] ?? []);
$linhasDespesas = montarLinhas($catsPorTipo['despesa'], $planejamento, $totaisFinanceiro['saida'] ?? []);
usort($linhasDespesas, fn($a,$b) => strcmp($a['categoria'],$b['categoria']));

$totalPlanejadoReceitas = array_sum(array_map('floatval', array_intersect_key($planejamento, array_flip($catsPorTipo['receita']))));
$totalPlanejadoDespesas = array_sum(array_map('floatval', array_intersect_key($planejamento, array_flip($catsPorTipo['despesa']))));
$totalPlanejadoDespesas += array_sum(array_map(fn($pc) => (float) $pc['total_parcelas'], $parcelasCartao));
$totalRealReceitas = array_sum($totaisFinanceiro['entrada'] ?? []);
$totalRealDespesas = array_sum(array_column($linhasDespesas, 'real'));

$saldoPlanejado = $totalPlanejadoReceitas - $totalPlanejadoDespesas;
$saldoReal = $totalRealReceitas - $totalRealDespesas;
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container py-4">

    <!-- Top e filtros -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold"><i class="fa fa-exchange-alt text-success"></i> Lançamentos</h2>
            <small class="text-muted">
                <?= htmlspecialchars(date('F Y', strtotime($mesSelecionado . '-01'))) ?>
            </small>
        </div>
        <a href="index.php" class="btn btn-outline-primary"><i class="fa fa-arrow-left"></i> Voltar Dashboard</a>
    </div>

    <form method="get" class="mb-3">
        <input type="hidden" name="mes" value="<?= htmlspecialchars($mesSelecionado) ?>" />
        <label for="mes_orcamento" class="form-label fw-semibold">Visualizar orçamento de:</label>
        <input type="month" id="mes_orcamento" name="mes_orcamento" class="form-control d-inline w-auto"
            value="<?= htmlspecialchars($mesOrcamento) ?>"
            onchange="this.form.submit()" style="max-width:170px;">
    </form>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card bg-gradient bg-success-subtle shadow border-0 h-100">
                <div class="card-body text-center">
                    <i class="fa fa-bullseye fa-2x text-success mb-2"></i>
                    <div class="fw-semibold">Saldo Planejado</div>
                    <div class="fs-4 fw-bold">R$ <?= number_format($saldoPlanejado, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card bg-gradient bg-primary-subtle shadow border-0 h-100">
                <div class="card-body text-center">
                    <i class="fa fa-wallet fa-2x text-primary mb-2"></i>
                    <div class="fw-semibold">Saldo Real</div>
                    <div class="fs-4 fw-bold">R$ <?= number_format($saldoReal, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 d-flex align-items-stretch">
            <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#modalNovoLancamento">
                <i class="fa fa-plus"></i> Novo Lançamento
            </button>
        </div>
        <div class="col-sm-6 col-lg-3 d-flex align-items-stretch">
            <a href="orcamento.php?mes=<?= $mesOrcamento ?>" class="btn btn-primary w-100">
                <i class="fa fa-bullseye"></i> Orçamento Mensal
            </a>
        </div>
    </div>

    <?php if ($mensagem): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header bg-danger-subtle fw-semibold">
            <i class="fa fa-arrow-up text-danger"></i> Despesas
        </div>
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Categoria</th>
                            <th>Planejado (R$)</th>
                            <th>Real (R$)</th>
                            <th>Diferença</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linhasDespesas as $linha): ?>
                        <tr>
                            <td><?= htmlspecialchars($linha['categoria']) ?></td>
                            <td><?= number_format($linha['planejado'], 2, ',', '.') ?></td>
                            <td><?= number_format($linha['real'], 2, ',', '.') ?></td>
                            <td class="<?= $linha['diferenca'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($linha['diferenca'], 2, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header bg-success-subtle fw-semibold">
            <i class="fa fa-arrow-down text-success"></i> Receitas
        </div>
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Categoria</th>
                            <th>Planejado (R$)</th>
                            <th>Real (R$)</th>
                            <th>Diferença</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linhasReceitas as $linha): ?>
                        <tr>
                            <td><?= htmlspecialchars($linha['categoria']) ?></td>
                            <td><?= number_format($linha['planejado'], 2, ',', '.') ?></td>
                            <td><?= number_format($linha['real'], 2, ',', '.') ?></td>
                            <td class="<?= $linha['diferenca'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($linha['diferenca'], 2, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header bg-warning-subtle fw-semibold">
            <i class="fa fa-credit-card text-warning"></i> Faturas dos Cartões
        </div>
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Cartão</th>
                            <th>Valor Total (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parcelasCartao as $fatura): ?>
                        <tr>
                            <td><?= htmlspecialchars($fatura['cartao_nome']) ?></td>
                            <td><?= number_format($fatura['total_parcelas'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($parcelasCartao)): ?>
                        <tr>
                            <td colspan="2" class="text-center">Nenhuma fatura encontrada para o mês selecionado.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Novo Lançamento -->
    <div class="modal fade" id="modalNovoLancamento" tabindex="-1" aria-labelledby="modalNovoLancamentoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" action="lancamentos.php?mes=<?= urlencode($mesSelecionado) ?>&mes_orcamento=<?= urlencode($mesOrcamento) ?>" class="modal-content">
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
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Salvar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include "footer.php"; ?>
