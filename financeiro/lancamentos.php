<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Lançamentos";
include 'header.php';

$mesSelecionado = $_GET['mes'] ?? date('Y-m');
$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

// Categorias disponíveis
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
        header("Location: lancamentos.php?mes=" . urlencode($mesSelecionado));
        exit;
    }

    if (empty($descricao) || !is_numeric($valor) || $valor <= 0 || !in_array($tipo, ['entrada', 'saida']) || empty($data)) {
        $_SESSION['erro'] = "Preencha os campos corretamente.";
        header("Location: lancamentos.php?mes=" . urlencode($mesSelecionado));
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO financeiro (descricao, categoria, valor, tipo, data) VALUES (?, ?, ?, ?, ?)");
    try {
        $stmt->execute([$descricao, $categoria, $valor, $tipo, $data]);
        $_SESSION['mensagem'] = "Lançamento adicionado com sucesso.";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao salvar.";
    }
    header("Location: lancamentos.php?mes=" . urlencode($mesSelecionado));
    exit;
}

// Buscar planejamento mensal
$stmtPlan = $pdo->prepare("SELECT categoria, valor_planejado FROM planejamento_mensal WHERE mes_ano = ?");
$stmtPlan->execute([$mesSelecionado]);
$planejamento = $stmtPlan->fetchAll(PDO::FETCH_KEY_PAIR);

// Buscar categorias por tipo
$stmtCats = $pdo->query("SELECT nome, tipo FROM categorias_orcamento ORDER BY nome");
$catsData = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
$catsPorTipo = ['receita' => [], 'despesa' => []];
foreach ($catsData as $c) {
    $catsPorTipo[$c['tipo']][] = $c['nome'];
}

// Buscar somatório lançamentos no mês
$stmtLanc = $pdo->prepare("SELECT categoria, tipo, SUM(valor) AS total FROM financeiro WHERE DATE_FORMAT(data,'%Y-%m')=? GROUP BY categoria, tipo");
$stmtLanc->execute([$mesSelecionado]);
$totaisLanc = $stmtLanc->fetchAll(PDO::FETCH_ASSOC);

$totaisFinanceiro = [];
foreach ($totaisLanc as $item) {
    $totaisFinanceiro[$item['tipo']][$item['categoria']] = $item['total'];
}

// Buscar integral das parcelas dos cartões (pagas e não pagas)
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

// Acrescentar as parcelas ao total de despesas realizadas e ao planejamento (se desejar 0 para planejamento)
// foreach ($parcelasCartao as $pc) {
//     $nomeCartao = 'Parcelas Cartão: '.$pc['cartao_nome'];
//     $valorParcelas = (float)$pc['total_parcelas'];

//     $totaisFinanceiro['saida'][$nomeCartao] = $valorParcelas;
//     if(!isset($planejamento[$nomeCartao])){
//         $planejamento[$nomeCartao] = 0;
//     }
// }

// Função para montar linhas das tabelas
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

// Ordena para melhor visualização
usort($linhasDespesas, fn($a,$b) => strcmp($a['categoria'],$b['categoria']));

// Cálculo dos saldos planejado e real
$totalPlanejadoReceitas = array_sum(array_map('floatval', array_intersect_key($planejamento, array_flip($catsPorTipo['receita']))));
$totalPlanejadoDespesas = array_sum(array_map('floatval', array_intersect_key($planejamento, array_flip($catsPorTipo['despesa']))));
$totalPlanejadoDespesas += array_sum(array_map(fn($pc) => (float) $pc['total_parcelas'], $parcelasCartao));

$totalRealReceitas = array_sum($totaisFinanceiro['entrada'] ?? []);
$totalRealDespesas = array_sum(array_column($linhasDespesas, 'real'));

$saldoPlanejado = $totalPlanejadoReceitas - $totalPlanejadoDespesas;
$saldoReal = $totalRealReceitas - $totalRealDespesas;

?>

<div class="container mt-4">

<h1>Lançamentos para <?= htmlspecialchars(date('F Y', strtotime($mesSelecionado . '-01'))) ?></h1>

<?php if ($mensagem): ?>
<div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div>
<?php endif; ?>
<?php if ($erro): ?>
<div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>


<div class="row mb-4">
    <div class="col-md-6">
        <div class="card text-bg-success">
            <div class="card-body">
                <h5>Saldo Planejado</h5>
                <p class="fs-3"><?= "R$ " . number_format($saldoPlanejado, 2, ',', '.') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card text-bg-primary">
            <div class="card-body">
                <h5>Saldo Real</h5>
                <p class="fs-3"><?= "R$ " . number_format($saldoReal, 2, ',', '.') ?></p>
            </div>
        </div>
    </div>
</div>

<h2>Despesas</h2>
<table class="table table-bordered table-striped">
    <thead>
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
            <td class="<?= $linha['diferenca'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($linha['diferenca'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h2>Receitas</h2>
<table class="table table-bordered table-striped">
    <thead>
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
            <td class="<?= $linha['diferenca'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($linha['diferenca'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h2>Faturas dos Cartões</h2>
<table class="table table-bordered table-striped">
    <thead>
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
        <tr><td colspan="2" class="text-center">Nenhuma fatura encontrada para o mês selecionado.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

</div>

<?php include 'footer.php'; ?>