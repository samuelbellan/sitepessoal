<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Adicionar Cartão de Crédito";
include "header.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];
    $limite = str_replace(',', '.', $_POST['limite']);  // Converter para decimal
    $dia_fechamento = $_POST['dia_fechamento'];
    $dia_vencimento = $_POST['dia_vencimento'];

    $stmt = $pdo->prepare("INSERT INTO cartoes_credito (nome, limite, dia_fechamento, dia_vencimento) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nome, $limite, $dia_fechamento, $dia_vencimento]);

    header("Location: cartoes.php");
    exit;
}
?>

<h1>Adicionar Cartão de Crédito</h1>

<form method="post" class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Nome do Cartão</label>
        <input type="text" name="nome" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Limite</label>
        <input type="text" name="limite" class="form-control" required placeholder="Ex: 5000.00">
    </div>
    <div class="col-md-6">
        <label class="form-label">Dia de Fechamento da Fatura</label>
        <input type="number" name="dia_fechamento" class="form-control" min="1" max="31" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Dia de Vencimento da Fatura</label>
        <input type="number" name="dia_vencimento" class="form-control" min="1" max="31" required>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="cartoes.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php include "footer.php"; ?>