<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Orçamento Mensal";
include "header.php";

// Mensagens
$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

$mesAtual = date('Y-m');
$mesEscolhido = $_GET['mes'] ?? $mesAtual;

// Processar formulário de orçamento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_orcamento'])) {
    $mes_ano = $_POST['mes_ano'] ?? $mesEscolhido;
    $planejadoForm = $_POST['planejado'] ?? [];
    try {
        $pdo->beginTransaction();
        foreach ($planejadoForm as $categoria => $valor) {
            $valor = (float)str_replace(',', '.', $valor);
            $stmt = $pdo->prepare("INSERT INTO planejamento_mensal (mes_ano, categoria, valor_planejado) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor_planejado = VALUES(valor_planejado)");
            $stmt->execute([$mes_ano, $categoria, $valor]);
        }
        $pdo->commit();
        $_SESSION['mensagem'] = "Orçamento atualizado com sucesso!";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['erro'] = "Erro ao salvar orçamento.";
    }
    header("Location: orcamento.php?mes=$mes_ano");
    exit;
}

// Buscar estrutura de categorias
$stmt_categorias = $pdo->query("SELECT nome, limite_ideal, categoria_pai FROM categorias_orcamento ORDER BY categoria_pai, nome");
$todas_cats = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

// Organizar hierarquia
$cats_principais = [];
$cats_filhas = [];
foreach ($todas_cats as $cat) {
    if ($cat['categoria_pai'] === null) {
        $cats_principais[$cat['nome']] = $cat['limite_ideal'];
    } else {
        $cats_filhas[$cat['categoria_pai']][] = $cat;
    }
}

// Buscar planejado e real
$stmtPlanejado = $pdo->prepare("SELECT categoria, valor_planejado FROM planejamento_mensal WHERE mes_ano = ?");
$stmtPlanejado->execute([$mesEscolhido]);
$planejado = $stmtPlanejado->fetchAll(PDO::FETCH_KEY_PAIR);

$stmtReal = $pdo->prepare("SELECT categoria, SUM(valor) as valor_real FROM financeiro WHERE tipo = 'saida' AND categoria != 'Pagamento de Fatura' AND DATE_FORMAT(data, '%Y-%m') = ? GROUP BY categoria");
$stmtReal->execute([$mesEscolhido]);
$real = $stmtReal->fetchAll(PDO::FETCH_KEY_PAIR);

// Calcular totais hierárquicos
function calcularTotaisHierarquicos($cats_principais, $cats_filhas, $planejado, $real) {
    $controle_hierarquico = [];
    
    foreach ($cats_principais as $cat_principal => $limite_ideal) {
        $valor_planejado_principal = $planejado[$cat_principal] ?? 0;
        $valor_real_principal = $real[$cat_principal] ?? 0;
        
        $limite_ideal_total = $limite_ideal;
        $valor_planejado_total = $valor_planejado_principal;
        $valor_real_total = $valor_real_principal;
        
        if (isset($cats_filhas[$cat_principal])) {
            foreach ($cats_filhas[$cat_principal] as $subcat) {
                $limite_ideal_total += $subcat['limite_ideal'];
                $valor_planejado_total += $planejado[$subcat['nome']] ?? 0;
                $valor_real_total += $real[$subcat['nome']] ?? 0;
            }
        }
        
        $diferenca = $valor_planejado_total - $valor_real_total;
        
        $controle_hierarquico[] = [
            'categoria'      => $cat_principal,
            'limite_ideal'   => $limite_ideal_total,
            'planejado'      => $valor_planejado_total,
            'real'           => $valor_real_total,
            'diferenca'      => $diferenca,
            'is_subcategoria' => false
        ];
        
        if (isset($cats_filhas[$cat_principal])) {
            foreach ($cats_filhas[$cat_principal] as $subcat) {
                $sub_planejado = $planejado[$subcat['nome']] ?? 0;
                $sub_real = $real[$subcat['nome']] ?? 0;
                $sub_diferenca = $sub_planejado - $sub_real;
                
                $controle_hierarquico[] = [
                    'categoria'      => $subcat['nome'],
                    'limite_ideal'   => $subcat['limite_ideal'],
                    'planejado'      => $sub_planejado,
                    'real'           => $sub_real,
                    'diferenca'      => $sub_diferenca,
                    'is_subcategoria' => true
                ];
            }
        }
    }
    
    return $controle_hierarquico;
}

$controle_mensal = calcularTotaisHierarquicos($cats_principais, $cats_filhas, $planejado, $real);

// Gerar opções de meses
$meses = [];
for ($i = -12; $i <= 12; $i++) {
    $data = (new DateTime())->modify("$i months");
    $meses[] = $data->format('Y-m');
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Orçamento Mensal</h1>
        <div>
            <a href="index.php" class="btn btn-outline-secondary">← Dashboard</a>
            <a href="categorias.php" class="btn btn-outline-primary">Categorias</a>
        </div>
    </div>

    <?php if ($mensagem): ?><div class="alert alert-success"><?= $mensagem ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

    <!-- Seletor de Mês -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Selecionar Mês</label>
                    <select name="mes" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($meses as $mes): ?>
                            <option value="<?= $mes ?>" <?= $mes == $mesEscolhido ? 'selected' : '' ?>>
                                <?= date('m/Y', strtotime($mes . '-01')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Orçamento -->
    <div class="card">
        <div class="card-header">
            <h5>Orçamento de <?= date('m/Y', strtotime($mesEscolhido . '-01')) ?></h5>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="salvar_orcamento" value="1">
                <input type="hidden" name="mes_ano" value="<?= $mesEscolhido ?>">
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Categoria</th>
                                <th>Limite Ideal</th>
                                <th>Planejado</th>
                                <th>Real</th>
                                <th>Diferença</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($controle_mensal as $item): ?>
                            <?php
                                $cor_diferenca = '';
                                if ($item['diferenca'] < 0) $cor_diferenca = 'text-danger fw-bold';
                                elseif ($item['diferenca'] > 0) $cor_diferenca = 'text-success';
                            ?>
                            <tr class="<?= $item['is_subcategoria'] ? 'table-light' : '' ?>">
                                <td>
                                    <?php if ($item['is_subcategoria']): ?>
                                        <span class="ms-3 text-muted">↳ <?= htmlspecialchars($item['categoria']) ?></span>
                                    <?php else: ?>
                                        <strong><?= htmlspecialchars($item['categoria']) ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>R$ <?= number_format($item['limite_ideal'], 2, ',', '.') ?></td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">R$</span>
                                        <input type="number" step="0.01" class="form-control"
                                            name="planejado[<?= htmlspecialchars($item['categoria']) ?>]"
                                            value="<?= number_format($item['planejado'], 2, '.', '') ?>"
                                            placeholder="0.00">
                                    </div>
                                </td>
                                <td>R$ <?= number_format($item['real'], 2, ',', '.') ?></td>
                                <td class="<?= $cor_diferenca ?>">R$ <?= number_format($item['diferenca'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Salvar Orçamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
