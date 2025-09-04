<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Editar Orçamento Mensal";
include "header.php";

session_start();
$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem']);
unset($_SESSION['erro']);

$mes_ano = date('Y-m');

// Processar formulário de atualização de orçamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planos = $_POST['planos'] ?? [];

    try {
        $pdo->beginTransaction();
        
        $stmt_update = $pdo->prepare("
            INSERT INTO planejamento_mensal (mes_ano, categoria, valor_planejado)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE valor_planejado = VALUES(valor_planejado)
        ");

        $toFloat = function ($str) {
            $str = str_replace(['R$', 'r$', ' ', '.', ','], '', $str);
            return (float)substr_replace($str, '.', -2, 0);
        };

        foreach ($planos as $categoria => $valor) {
            $valor_float = $toFloat($valor);
            if ($valor_float > 0) {
                $stmt_update->execute([$mes_ano, $categoria, $valor_float]);
            }
        }

        $pdo->commit();
        $_SESSION['mensagem'] = "Orçamento do mês atualizado com sucesso!";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['erro'] = "Erro ao salvar o orçamento: " . $e->getMessage();
    }

    header('Location: index.php');
    exit;
}

// Buscar todas as categorias existentes no sistema
$stmt_categorias = $pdo->query("SELECT DISTINCT categoria FROM financeiro WHERE categoria IS NOT NULL AND categoria != ''");
$categorias_ativas = $stmt_categorias->fetchAll(PDO::FETCH_COLUMN);

// Buscar valores de orçamento existentes para o mês
$stmt_orcamento_existente = $pdo->prepare("
    SELECT categoria, valor_planejado
    FROM planejamento_mensal
    WHERE mes_ano = ?
");
$stmt_orcamento_existente->execute([$mes_ano]);
$orcamento_existente = $stmt_orcamento_existente->fetchAll(PDO::FETCH_KEY_PAIR);

// Preparar dados para o formulário
$dados_orcamento = [];
foreach ($categorias_ativas as $cat) {
    $dados_orcamento[$cat] = $orcamento_existente[$cat] ?? '0,00';
}
?>

<h1>Editar Orçamento Mensal (<?= date('m/Y') ?>)</h1>

<?php if ($mensagem): ?>
    <div class="alert alert-success"><?= $mensagem ?></div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="alert alert-danger"><?= $erro ?></div>
<?php endif; ?>

<form method="post">
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Categoria</th>
                    <th>Valor Planejado (R$)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($dados_orcamento): ?>
                    <?php foreach ($dados_orcamento as $categoria => $valor): ?>
                        <tr>
                            <td><?= htmlspecialchars($categoria) ?></td>
                            <td>
                                <input type="text" name="planos[<?= htmlspecialchars($categoria) ?>]" 
                                       class="form-control" 
                                       value="<?= number_format($valor, 2, ',', '.') ?>" 
                                       placeholder="0,00">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" class="text-center">Nenhuma categoria encontrada para orçamento.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <button type="submit" class="btn btn-primary">Salvar Orçamento</button>
    <a href="index.php" class="btn btn-secondary">Cancelar</a>
</form>

<?php include "footer.php"; ?>