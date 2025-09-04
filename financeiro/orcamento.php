<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Orçamento Mensal";
include 'header.php';

// Obter o mês selecionado no formato YYYY-MM
$mesSelecionado = $_GET['mes'] ?? date('Y-m');

// Buscar categorias e estrutura de pai/filho
$stmt = $pdo->query("SELECT id, nome, limite_ideal AS planejado, categoria_pai FROM categorias_orcamento ORDER BY categoria_pai ASC, nome ASC");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar categorias em pais e filhos
$parents = [];
$children = [];
foreach ($categorias as $categoria) {
    if (empty($categoria['categoria_pai'])) {
        $parents[$categoria['nome']] = $categoria;
    } else {
        $children[$categoria['categoria_pai']][] = $categoria;
    }
}

// Buscar soma real das despesas por categoria para o mês
$stmtReal = $pdo->prepare("SELECT categoria, SUM(valor) AS total_real FROM financeiro WHERE tipo = 'saida' AND categoria IS NOT NULL AND categoria != '' AND DATE_FORMAT(data, '%Y-%m') = ? GROUP BY categoria");
$stmtReal->execute([$mesSelecionado]);
$real = $stmtReal->fetchAll(PDO::FETCH_KEY_PAIR);

// Montar dados para exibição
$rows = [];
foreach ($parents as $nomePai => $catPai) {
    $planejadoPai = $catPai['planejado'] ?? 0;
    $realPai = $real[$nomePai] ?? 0;

    // Soma de planejado e real para pai + filhos
    $planejadoTotal = $planejadoPai;
    $realTotal = $realPai;

    if (isset($children[$nomePai])) {
        foreach ($children[$nomePai] as $filho) {
            $nomeFilho = $filho['nome'];
            $planejadoTotal += $filho['planejado'] ?? 0;
            $realTotal += $real[$nomeFilho] ?? 0;
        }
    }

    $rows[] = [
        'categoria' => $nomePai,
        'planejado' => $planejadoTotal,
        'real' => $realTotal,
        'diferenca' => $planejadoTotal - $realTotal,
        'is_subcategoria' => false,
    ];

    if (isset($children[$nomePai])) {
        foreach ($children[$nomePai] as $filho) {
            $nomeFilho = $filho['nome'];
            $rows[] = [
                'categoria' => $nomeFilho,
                'planejado' => $filho['planejado'] ?? 0,
                'real' => $real[$nomeFilho] ?? 0,
                'diferenca' => ($filho['planejado'] ?? 0) - ($real[$nomeFilho] ?? 0),
                'is_subcategoria' => true,
            ];
        }
    }
}
?>

<div class="container mt-4">
    <h1>Orçamento para <?= htmlspecialchars(date('F Y', strtotime($mesSelecionado . '-01'))) ?></h1>

    <form method="get" class="mb-3">
        <label for="mesSelect" class="form-label">Selecionar mês</label>
        <input type="month" id="mesSelect" name="mes" value="<?= htmlspecialchars($mesSelecionado) ?>" onchange="this.form.submit()" />
    </form>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Categoria</th>
                <th>Planejado (R$)</th>
                <th>Real (R$)</th>
                <th>Diferença (R$)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr class="<?= $row['is_subcategoria'] ? 'table-light' : '' ?>">
                <td><?= $row['is_subcategoria'] ? '↳ ' : '' ?><?= htmlspecialchars($row['categoria']) ?></td>
                <td><?= number_format($row['planejado'], 2, ',', '.') ?></td>
                <td><?= number_format($row['real'], 2, ',', '.') ?></td>
                <td><?= number_format($row['diferenca'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>
