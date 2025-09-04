<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Editar Lançamento";
include "header.php";

if (!isset($_GET['id'])) {
    die("ID não informado.");
}
$id = (int) $_GET['id'];

// Buscar dados
$stmt = $pdo->prepare("SELECT * FROM financeiro WHERE id = ?");
$stmt->execute([$id]);
$registro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registro) {
    die("Registro não encontrado.");
}

// Atualizar se enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descricao = $_POST['descricao'];
    $categoria = $_POST['categoria'];
    $valor = $_POST['valor'];
    $tipo = $_POST['tipo'];
    $data = $_POST['data'];

    $stmt = $pdo->prepare("UPDATE financeiro SET descricao=?, categoria=?, valor=?, tipo=?, data=? WHERE id=?");
    $stmt->execute([$descricao, $categoria, $valor, $tipo, $data, $id]);

    header("Location: index.php");
    exit;
}
?>

<h1>Editar Lançamento</h1>

<form method="post">
    <div class="mb-3">
        <label class="form-label">Descrição</label>
        <input type="text" name="descricao" class="form-control" value="<?= htmlspecialchars($registro['descricao']) ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Categoria</label>
        <input type="text" name="categoria" class="form-control" value="<?= htmlspecialchars($registro['categoria']) ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Valor</label>
        <input type="number" step="0.01" name="valor" class="form-control" value="<?= htmlspecialchars($registro['valor']) ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Tipo</label>
        <select name="tipo" class="form-select" required>
            <option value="entrada" <?= $registro['tipo']=='entrada'?'selected':'' ?>>Entrada</option>
            <option value="saida" <?= $registro['tipo']=='saida'?'selected':'' ?>>Saída</option>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Data</label>
        <input type="date" name="data" class="form-control" value="<?= htmlspecialchars($registro['data']) ?>" required>
    </div>

    <button type="submit" class="btn btn-success">Salvar</button>
    <a href="index.php" class="btn btn-secondary">Cancelar</a>
</form>

<?php include "footer.php"; ?>
