<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Lançamentos";
include 'header.php';

$mesSelecionado = $_GET['mes'] ?? date('Y-m');
$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

// Categorias do sistema para validação e filtro
$catsSistema = $pdo->query("SELECT nome FROM categorias_orcamento ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);

// Processar novo lançamento (mantido conforme seu código)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_lancamento'])) {
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria = $_POST['categoria'] ?? '';
    $valor = str_replace(',', '.', $_POST['valor'] ?? '0');
    $tipo = $_POST['tipo'] ?? '';
    $data = $_POST['data'] ?? '';

    if (!in_array($categoria, $catsSistema)) {
        $_SESSION['erro'] = "Selecione uma categoria válida.";
        header('Location: lancamentos.php?mes=' . urlencode($mesSelecionado));
        exit;
    }

    if (empty($descricao) || empty($valor) || !is_numeric($valor) || $valor <= 0 || empty($tipo) || !in_array($tipo, ['entrada', 'saida']) || empty($data)) {
        $_SESSION['erro'] = "Preencha todos os campos obrigatórios corretamente.";
        header('Location: lancamentos.php?mes=' . urlencode($mesSelecionado));
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO financeiro (descricao, categoria, valor, tipo, data) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$descricao, $categoria, $valor, $tipo, $data]);
        $_SESSION['mensagem'] = "Lançamento adicionado com sucesso!";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao salvar o lançamento.";
    }
    header('Location: lancamentos.php?mes=' . urlencode($mesSelecionado));
    exit;
}

// Buscar planejamento mensal para o mês selecionado
$stmtPlan = $pdo->prepare("SELECT categoria, valor_planejado FROM planejamento_mensal WHERE mes_ano = ?");
$stmtPlan->execute([$mesSelecionado]);
$planejamento = $stmtPlan->fetchAll(PDO::FETCH_KEY_PAIR);

// Buscar categorias completas com tipo
$stmtCats = $pdo->query("SELECT nome, tipo FROM categorias_orcamento ORDER BY nome");
$categoriasDados = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

// Agrupar categorias por tipo
$categoriasPorTipo = ['receita' => [], 'despesa' => []];
foreach ($categoriasDados as $cat) {
    $categoriasPorTipo[$cat['tipo']][] = $cat['nome'];
}

// Buscar soma lançamentos financeiro agrupados por categoria e tipo
$stmtLanc = $pdo->prepare("
    SELECT categoria, tipo, SUM(valor) AS total
    FROM financeiro
    WHERE DATE_FORMAT(data, '%Y-%m') = ?
    GROUP BY categoria, tipo
");
$stmtLanc->execute([$mesSelecionado]);
$totaisFinanceiroRaw = $stmtLanc->fetchAll(PDO::FETCH_ASSOC);

$totaisFinanceiro = [];
foreach ($totaisFinanceiroRaw as $row) {
    $totaisFinanceiro[$row['tipo']][$row['categoria']] = $row['total'];
}

// Buscar soma das parcelas por cartão no mês e status de pagamento
$stmtParcelasCartao = $pdo->prepare("
    SELECT c.nome AS cartao_nome, SUM(pc.valor) AS total_fatura
    FROM parcelas_cartao pc
    JOIN transacoes_cartao tc ON pc.transacao_id = tc.id
    JOIN cartoes_credito c ON tc.cartao_id = c.id
    WHERE pc.paga = 0 AND DATE_FORMAT(pc.vencimento, '%Y-%m') = ?
    GROUP BY c.nome
");
$stmtParcelasCartao->execute([$mesSelecionado]);
$faturasCartao = $stmtParcelasCartao->fetchAll(PDO::FETCH_ASSOC);

// Construir linhas para despesas a partir das categorias normais
function montarLinhas(array $categorias, array $planejamento, array $lancamentos) {
    $linhas = [];
    foreach ($categorias as $categoria) {
        $planejado = $planejamento[$categoria] ?? 0.0;
        $real = $lancamentos[$categoria] ?? 0.0;
        $linhas[] = [
            'categoria' => $categoria,
            'planejado' => $planejado,
            'real' => $real,
            'diferenca' => $planejado - $real,
        ];
    }
    return $linhas;
}

$linhasReceitas = montarLinhas($categoriasPorTipo['receita'], $planejamento, $totaisFinanceiro['entrada'] ?? []);
$linhasDespesas = montarLinhas($categoriasPorTipo['despesa'], $planejamento, $totaisFinanceiro['saida'] ?? []);

?>

<div class="container mt-4">
    <h1>Lançamentos para <?= htmlspecialchars(date('F Y', strtotime($mesSelecionado . '-01'))) ?></h1>

    <?php if ($mensagem): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="get" class="mb-3">
        <label for="mes" class="form-label">Filtrar por mês</label>
        <input type="month" id="mes" name="mes" class="form-control" value="<?= htmlspecialchars($mesSelecionado) ?>" onchange="this.form.submit()" />
    </form>

    <h2>Despesas</h2>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Categoria</th>
                <th>Planejado (R$)</th>
                <th>Real (R$)</th>
                <th>Diferença (R$)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($linhasDespesas as $linha): ?>
                <tr>
                    <td><?= htmlspecialchars($linha['categoria']) ?></td>
                    <td><?= number_format($linha['planejado'], 2, ',', '.') ?></td>
                    <td><?= number_format($linha['real'], 2, ',', '.') ?></td>
                    <td><?= number_format($linha['diferenca'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ($faturasCartao as $fatura): ?>
                <tr>
                    <td><?= htmlspecialchars($fatura['cartao_nome'] . ' (Fatura Cartão)') ?></td>
                    <td> - </td>
                    <td><?= number_format($fatura['total_fatura'], 2, ',', '.') ?></td>
                    <td> - </td>
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
                <th>Diferença (R$)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($linhasReceitas as $linha): ?>
                <tr>
                    <td><?= htmlspecialchars($linha['categoria']) ?></td>
                    <td><?= number_format($linha['planejado'], 2, ',', '.') ?></td>
                    <td><?= number_format($linha['real'], 2, ',', '.') ?></td>
                    <td><?= number_format($linha['diferenca'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>
