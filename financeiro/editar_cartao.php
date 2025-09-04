<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Editar Cartão de Crédito";
include "header.php";

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM cartoes_credito WHERE id = ?");
$stmt->execute([$id]);
$cartao = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];
    $limite = str_replace(',', '.', $_POST['limite']);
    $dia_fechamento = $_POST['dia_fechamento'];
    $dia_vencimento = $_POST['dia_vencimento'];

    $stmt = $pdo->prepare("UPDATE cartoes_credito SET nome = ?, limite = ?, dia_fechamento = ?, dia_vencimento = ? WHERE id = ?");
    $stmt->execute([$nome, $limite, $dia_fechamento, $dia_vencimento, $id]);

    header("Location: cartoes.php");
    exit;
}
?>

<h1>Editar Cartão de Crédito</h1>

<form method="post" class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Nome do Cartão</label>
        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($cartao['nome']) ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Limite</label>
        <input type="text" name="limite" class="form-control" value="<?= number_format($cartao['limite'], 2, '.', '') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Dia de Fechamento da Fatura</label>
        <input type="number" name="dia_fechamento" class="form-control" value="<?= $cartao['dia_fechamento'] ?>" min="1" max="31" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Dia de Vencimento da Fatura</label>
        <input type="number" name="dia_vencimento" class="form-control" value="<?= $cartao['dia_vencimento'] ?>" min="1" max="31" required>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="cartoes.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php include "footer.php"; ?>