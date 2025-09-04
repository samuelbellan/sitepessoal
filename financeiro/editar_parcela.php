<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Editar Parcela";
include "header.php";

$parcela_id = $_GET['id'] ?? null;
$cartao_id = $_GET['cartao_id'] ?? null;

if (!$parcela_id || !$cartao_id) {
    echo "<div class='alert alert-danger'>Dados insuficientes para editar a parcela.</div>";
    include "footer.php";
    exit;
}

// Buscar informações da parcela e transação
$stmt_parcela = $pdo->prepare("
    SELECT p.*, t.cartao_id, t.valor as valor_total
    FROM parcelas_cartao p
    JOIN transacoes_cartao t ON p.transacao_id = t.id
    WHERE p.id = ? AND t.cartao_id = ?
");
$stmt_parcela->execute([$parcela_id, $cartao_id]);
$parcela = $stmt_parcela->fetch(PDO::FETCH_ASSOC);

if (!$parcela) {
    echo "<div class='alert alert-danger'>Parcela não encontrada ou não pertence a este cartão.</div>";
    include "footer.php";
    exit;
}

// Buscar informações do cartão
$stmt_cartao = $pdo->prepare("SELECT nome, limite, dia_fechamento, dia_vencimento FROM cartoes_credito WHERE id = ?");
$stmt_cartao->execute([$cartao_id]);
$cartao = $stmt_cartao->fetch(PDO::FETCH_ASSOC);

$mensagem = '';
$erro = '';

// Processar formulário de edição
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // A descrição foi movida para a tabela transacoes_cartao
    $descricao = trim($_POST['descricao'] ?? $parcela['descricao']);
    $valor_parcela = str_replace(',', '.', $_POST['valor'] ?? $parcela['valor']); // Valor da parcela
    $data_vencimento = $_POST['vencimento'] ?? $parcela['vencimento'];

    if (empty($descricao) || empty($valor_parcela) || !is_numeric($valor_parcela) || $valor_parcela <= 0) {
        $erro = "Erro: Preencha todos os campos obrigatórios corretamente.";
    } else {
        try {
            $pdo->beginTransaction();

            // Atualiza o valor e vencimento da parcela
            $stmt_update = $pdo->prepare("UPDATE parcelas_cartao SET valor = ?, vencimento = ? WHERE id = ?");
            $stmt_update->execute([$valor_parcela, $data_vencimento, $parcela_id]);

            // Atualiza a descrição na transação principal
            $stmt_update_transacao = $pdo->prepare("UPDATE transacoes_cartao SET descricao = ? WHERE id = ?");
            $stmt_update_transacao->execute([$descricao, $parcela['transacao_id']]);

            $pdo->commit();

            $mensagem = "Parcela atualizada com sucesso!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Erro ao salvar a parcela: " . $e->getMessage();
        }
    }
}

// Recarrega os dados para preencher o formulário
$stmt_parcela = $pdo->prepare("
    SELECT p.*, t.descricao, t.valor as valor_total
    FROM parcelas_cartao p
    JOIN transacoes_cartao t ON p.transacao_id = t.id
    WHERE p.id = ? AND t.cartao_id = ?
");
$stmt_parcela->execute([$parcela_id, $cartao_id]);
$parcela = $stmt_parcela->fetch(PDO::FETCH_ASSOC);

// Dados para preencher o formulário
$dados_form = [
    'descricao' => $parcela['descricao'],
    'valor' => number_format($parcela['valor'], 2, ',', '.'),
    'vencimento' => $parcela['vencimento'],
    'status_paga' => $parcela['paga']
];
?>

<h1>Editar Parcela</h1>
<p>Cartão: <?= htmlspecialchars($cartao['nome']) ?></p>
<p>Valor Total da Transação: R$ <?= number_format($parcela['valor_total'], 2, ',', '.') ?></p>

<?php if ($mensagem): ?>
    <div class="alert alert-success"><?= $mensagem ?></div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="alert alert-danger"><?= $erro ?></div>
<?php endif; ?>

<form method="post" class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Descrição</label>
        <input type="text" name="descricao" class="form-control" value="<?= htmlspecialchars($dados_form['descricao']) ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Valor da Parcela</label>
        <input type="text" name="valor" class="form-control" value="<?= $dados_form['valor'] ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Vencimento</label>
        <input type="date" name="vencimento" class="form-control" value="<?= $dados_form['vencimento'] ?>" required>
    </div>
    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        <a href="transacoes_cartao.php?cartao_id=<?= $cartao_id ?>" class="btn btn-secondary">Voltar</a>
    </div>
</form>

<?php include "footer.php"; ?>