<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Orçamento Mensal";
include 'header.php';

$mesSelecionado = $_GET['mes'] ?? date('Y-m');
$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subcategoria_mes'])) {
    $mes_ano = $_POST['mes_ano'] ?? $mesSelecionado;
    $categoria_pai = $_POST['categoria_pai'] ?? '';
    $nova_subcat = trim($_POST['nova_subcategoria'] ?? '');

    if ($categoria_pai === '' || $nova_subcat === '') {
        $_SESSION['erro'] = "Preencha todos os campos para adicionar a subcategoria.";
        header("Location: orcamento.php?mes=$mes_ano");
        exit();
    }

    // Verifica se já existe subcategoria deste nome para o mesmo mês/categoria mãe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM planejamento_mensal WHERE mes_ano = ? AND categoria = ?");
    $nomeMes = $categoria_pai . ' - ' . $nova_subcat;
    $stmt->execute([$mes_ano, $nomeMes]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['erro'] = "Já existe uma subcategoria com esse nome para este mês.";
        header("Location: orcamento.php?mes=$mes_ano");
        exit();
    }

    // Cria a subcategoria "virtual" apenas neste mês
    $insert = $pdo->prepare("INSERT INTO planejamento_mensal (mes_ano, categoria, valor_planejado) VALUES (?, ?, 0)");
    $insert->execute([$mes_ano, $nomeMes]);
    $_SESSION['mensagem'] = "Subcategoria adicionada para o mês!";
    header("Location: orcamento.php?mes=$mes_ano");
    exit();
}

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
    // Subcategorias temporárias daquele mês
    foreach ($planejadosSalvos as $cat => $val) {
        if (strpos($cat, $nomePai . ' - ') === 0) {
            $rows[] = [
                'categoria' => $cat,
                'planejado' => $val,
                'is_subcategoria' => true
            ];
        }
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <i class="fa fa-bullseye fa-2x text-primary"></i>
            <div>
                <h2 class="mb-0 fw-bold">Orçamento Mensal</h2>
                <small class="text-muted"><?= htmlspecialchars(date('F Y', strtotime($mesSelecionado . '-01'))) ?></small>
            </div>
        </div>
        <a href="index.php" class="btn btn-outline-primary"><i class="fa fa-arrow-left"></i> Voltar Dashboard</a>
    </div>

    <?php if ($mensagem): ?><div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <form method="get" class="mb-4">
        <label for="mes" class="form-label fw-semibold">Selecionar Mês</label>
        <div class="row g-2">
            <div class="col-auto">
                <input type="month" id="mes" name="mes" class="form-control" value="<?= htmlspecialchars($mesSelecionado) ?>" onchange="this.form.submit()" />
            </div>
        </div>
    </form>

    <div class="card shadow mb-4">
        <div class="card-header fw-semibold bg-primary-subtle">
            <i class="fa fa-plus-circle text-primary"></i> Adicionar Subcategoria do Mês
        </div>
        <div class="card-body">
            <form method="post" class="row g-3 align-items-end">
                <input type="hidden" name="mes_ano" value="<?= htmlspecialchars($mesSelecionado) ?>" />
                <input type="hidden" name="add_subcategoria_mes" value="1">
                <div class="col-md-5">
                    <label class="form-label">Categoria mãe:</label>
                    <select name="categoria_pai" class="form-select" required>
                        <?php foreach ($categoriasPai as $nomePai => $pai): ?>
                            <option value="<?= htmlspecialchars($nomePai) ?>"><?= htmlspecialchars($nomePai) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Nome da nova subcategoria:</label>
                    <input type="text" name="nova_subcategoria" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success w-100"><i class="fa fa-plus"></i> Adicionar</button>
                </div>
            </form>
        </div>
    </div>

    <form method="post" class="mb-3">
        <input type="hidden" name="mes_ano" value="<?= htmlspecialchars($mesSelecionado) ?>" />
        <input type="hidden" name="salvar_orcamento" value="1" />

        <div class="card shadow">
            <div class="card-header bg-primary-subtle fw-semibold"><i class="fa fa-table text-primary"></i> Planejamento</div>
            <div class="card-body px-3 py-2">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Categoria</th>
                                <th class="text-center">Planejado (R$)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr class="<?= $row['is_subcategoria'] ? 'table-info' : '' ?>">
                                    <td class="<?= $row['is_subcategoria'] ? '' : 'fw-bold text-primary' ?>">
                                        <?= $row['is_subcategoria'] ? '<i class="fa fa-arrow-right text-secondary me-1"></i>' : '<i class="fa fa-folder text-primary me-1"></i>' ?>
                                        <?= htmlspecialchars($row['categoria']) ?>
                                    </td>
                                    <td class="text-center">
                                        <input type="number" step="0.01" name="planejado[<?= htmlspecialchars($row['categoria']) ?>]" value="<?= number_format($row['planejado'], 2, '.', '') ?>" class="form-control text-end" />
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light d-flex justify-content-end">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Salvar Orçamento</button>
            </div>
        </div>
    </form>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'footer.php'; ?>
