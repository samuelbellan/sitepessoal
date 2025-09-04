<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Orçamento Mensal";
include 'header.php';

$mesSelecionado = $_GET['mes'] ?? date('Y-m');

$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_orcamento'])) {
    $mes_ano = $_POST['mes_ano'] ?? $mesSelecionado;
    $planejados = $_POST['planejado'] ?? [];

    if (!$mes_ano || !preg_match('/^\d{4}-\d{2}$/', $mes_ano)) {
        $_SESSION['erro'] = "Mês inválido.";
        header("Location: orcamento.php?mes=$mes_ano");
        exit();
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO planejamento_mensal (mes_ano, categoria, valor_planejado) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor_planejado = VALUES(valor_planejado)");
        foreach ($planejados as $categoria => $valor) {
            $valorFloat = floatval(str_replace(',', '.', $valor));
            $stmt->execute([$mes_ano, $categoria, $valorFloat]);
        }
        $pdo->commit();
        $_SESSION['mensagem'] = "Orçamento salvo com sucesso!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['erro'] = "Erro ao salvar orçamento: " . $e->getMessage();
    }
    header("Location: orcamento.php?mes=$mes_ano");
    exit();
}

// Buscar categorias incluindo o campo limite_ideal e hierarquia
$stmtCats = $pdo->query("SELECT id, nome, limite_ideal, categoria_pai FROM categorias_orcamento ORDER BY categoria_pai ASC, nome ASC");
$categorias = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

$categoriasPai = [];
$categoriasFilho = [];

foreach ($categorias as $cat) {
    if (empty($cat['categoria_pai'])) {
        $categoriasPai[$cat['nome']] = $cat;
    } else {
        $categoriasFilho[$cat['categoria_pai']][] = $cat;
    }
}

// Buscar valores planejados para o mês
$stmtPlano = $pdo->prepare("SELECT categoria, valor_planejado FROM planejamento_mensal WHERE mes_ano = ?");
$stmtPlano->execute([$mesSelecionado]);
$planejadosSalvos = $stmtPlano->fetchAll(PDO::FETCH_KEY_PAIR);

// Preparar linhas, usando valores salvos ou padrão do limite_ideal
$rows = [];
foreach ($categoriasPai as $nomePai => $pai) {
    $valorPlanejadoPai = $planejadosSalvos[$nomePai] ?? $pai['limite_ideal'];

    $rows[] = [
        'categoria' => $nomePai,
        'planejado' => $valorPlanejadoPai,
        'is_subcategoria' => false,
    ];

    if (isset($categoriasFilho[$nomePai])) {
        foreach ($categoriasFilho[$nomePai] as $filho) {
            $valorPlanejadoFilho = $planejadosSalvos[$filho['nome']] ?? $filho['limite_ideal'];
            $rows[] = [
                'categoria' => $filho['nome'],
                'planejado' => $valorPlanejadoFilho,
                'is_subcategoria' => true,
            ];
        }
    }
}
?>

<div class="container mt-4">
    <h1>Orçamento Mensal - <?= htmlspecialchars(date('F Y', strtotime($mesSelecionado . '-01'))) ?></h1>

    <?php if ($mensagem): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="get" class="mb-4">
        <label for="mes" class="form-label">Selecionar Mês</label>
        <input type="month" id="mes" name="mes" class="form-control" value="<?= htmlspecialchars($mesSelecionado) ?>" onchange="this.form.submit()" />
    </form>

    <form method="post">
        <input type="hidden" name="mes_ano" value="<?= htmlspecialchars($mesSelecionado) ?>" />
        <input type="hidden" name="salvar_orcamento" value="1" />

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th>Planejado (R$)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="<?= $row['is_subcategoria'] ? 'table-light' : '' ?>">
                        <td><?= $row['is_subcategoria'] ? '↳ ' : '' ?><?= htmlspecialchars($row['categoria']) ?></td>
                        <td>
                            <input type="number" step="0.01" name="planejado[<?= htmlspecialchars($row['categoria']) ?>]" value="<?= number_format($row['planejado'], 2, '.', '') ?>" class="form-control" />
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <button type="submit" class="btn btn-primary">Salvar Orçamento</button>
    </form>
</div>

<?php include 'footer.php'; ?>
