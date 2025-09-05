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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold"><i class="fa fa-edit text-primary"></i> Editar Cartão de Crédito</h2>
            <small class="text-muted">Atualize informações do cartão abaixo.</small>
        </div>
        <a href="cartoes.php" class="btn btn-outline-primary"><i class="fa fa-arrow-left"></i> Voltar Cartões</a>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow-lg">
                <div class="card-body">
                    <form method="post" class="row g-4">
                        <div class="col-12 mb-3">
                            <label class="form-label fw-semibold"><i class="fa fa-id-card"></i> Nome do Cartão</label>
                            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($cartao['nome']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold"><i class="fa fa-wallet"></i> Limite</label>
                            <input type="text" name="limite" class="form-control" value="<?= number_format($cartao['limite'], 2, '.', '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold"><i class="fa fa-calendar-alt"></i> Dia Fechamento</label>
                            <input type="number" name="dia_fechamento" class="form-control" value="<?= $cartao['dia_fechamento'] ?>" min="1" max="31" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold"><i class="fa fa-calendar-check"></i> Dia Vencimento</label>
                            <input type="number" name="dia_vencimento" class="form-control" value="<?= $cartao['dia_vencimento'] ?>" min="1" max="31" required>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Salvar</button>
                            <a href="cartoes.php" class="btn btn-secondary"><i class="fa fa-times"></i> Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include "footer.php"; ?>
