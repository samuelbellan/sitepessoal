<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Lançamentos Financeiros";
include "header.php";

// Mensagens
$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

// Categorias para filtro e cadastro
$cats_sistema = $pdo->query("SELECT nome FROM categorias_orcamento ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);

// Processar novo lançamento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['novo_lancamento'])) {
    $descricao = $_POST['descricao'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $valor = str_replace(',', '.', $_POST['valor'] ?? '0');
    $tipo = $_POST['tipo'] ?? '';
    $data = $_POST['data'] ?? '';

    // Validação da categoria
    if (!in_array($categoria, $cats_sistema)) {
        $_SESSION['erro'] = "Selecione uma categoria válida.";
        header('Location: lancamentos.php');
        exit;
    }

    if (empty($descricao) || empty($valor) || !is_numeric($valor) || $valor <= 0 || empty($tipo) || !in_array($tipo, ['entrada', 'saida']) || empty($data)) {
        $_SESSION['erro'] = "Preencha todos os campos obrigatórios corretamente.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO financeiro (descricao, categoria, valor, tipo, data) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$descricao, $categoria, $valor, $tipo, $data]);
            $_SESSION['mensagem'] = "Lançamento adicionado com sucesso!";
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao salvar o lançamento.";
        }
    }
    header('Location: lancamentos.php');
    exit;
}

// Filtros
$filtroCategoria = $_GET['categoria'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';
$filtroDataInicio = $_GET['data_inicio'] ?? '';
$filtroDataFim = $_GET['data_fim'] ?? '';

$where = [];
$params = [];
if ($filtroCategoria != '') {
    $where[] = "categoria = :categoria";
    $params[':categoria'] = $filtroCategoria;
}
if ($filtroTipo != '') {
    $where[] = "tipo = :tipo";
    $params[':tipo'] = $filtroTipo;
}
if ($filtroDataInicio != '') {
    $where[] = "data >= :data_inicio";
    $params[':data_inicio'] = $filtroDataInicio;
}
if ($filtroDataFim != '') {
    $where[] = "data <= :data_fim";
    $params[':data_fim'] = $filtroDataFim;
}
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// Buscar lançamentos
$stmt = $pdo->prepare("SELECT * FROM financeiro $whereSQL ORDER BY data DESC, id DESC LIMIT 100");
$stmt->execute($params);
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totais
$stmtEntradas = $pdo->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = 'entrada' " . $whereSQL);
$stmtEntradas->execute($params);
$totalEntradas = $stmtEntradas->fetchColumn() ?: 0;

$stmtSaidas = $pdo->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = 'saida' " . $whereSQL);
$stmtSaidas->execute($params);
$totalSaidas = $stmtSaidas->fetchColumn() ?: 0;

// Categorias para filtro e cadastro
$categorias = $pdo->query("SELECT DISTINCT categoria FROM financeiro WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria")->fetchAll(PDO::FETCH_COLUMN);
$cats_sistema = $pdo->query("SELECT nome FROM categorias_orcamento ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);
$todas_categorias = array_unique(array_merge($categorias, $cats_sistema));
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Lançamentos Financeiros</h1>
        <div>
            <a href="index.php" class="btn btn-outline-secondary">← Dashboard</a>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNovoLancamento">+ Novo Lançamento</button>
        </div>
    </div>

    <?php if ($mensagem): ?><div class="alert alert-success"><?= $mensagem ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

    <!-- Modal Novo Lançamento -->
    <div class="modal fade" id="modalNovoLancamento" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Novo Lançamento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="novo_lancamento" value="1">
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="descricao" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Categoria</label>
                            <select name="categoria" class="form-select" required>
                                <option value="">Selecione</option>
                                <?php foreach ($cats_sistema as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Valor</label>
                            <input type="text" name="valor" class="form-control" placeholder="Ex: 100.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" required>
                                <option value="">Selecione</option>
                                <option value="entrada">Entrada</option>
                                <option value="saida">Saída</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data</label>
                            <input type="date" name="data" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Resumo -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-bg-success">
                <div class="card-body">
                    <h5>Entradas</h5>
                    <p class="fs-4">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-bg-danger">
                <div class="card-body">
                    <h5>Saídas</h5>
                    <p class="fs-4">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-bg-primary">
                <div class="card-body">
                    <h5>Saldo</h5>
                    <p class="fs-4">R$ <?= number_format($totalEntradas - $totalSaidas, 2, ',', '.') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Categoria</label>
                    <select name="categoria" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($todas_categorias as $c): ?>
                            <option value="<?= $c ?>" <?= $filtroCategoria == $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select">
                        <option value="">Todos</option>
                        <option value="entrada" <?= $filtroTipo == 'entrada' ? 'selected' : '' ?>>Entrada</option>
                        <option value="saida" <?= $filtroTipo == 'saida' ? 'selected' : '' ?>>Saída</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Início</label>
                    <input type="date" name="data_inicio" value="<?= $filtroDataInicio ?>" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Fim</label>
                    <input type="date" name="data_fim" value="<?= $filtroDataFim ?>" class="form-control">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Lançamentos -->
    <div class="card">
        <div class="card-header">
            <h5>Lançamentos (<?= count($lancamentos) ?> registros)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Valor</th>
                            <th>Tipo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($lancamentos): ?>
                            <?php foreach ($lancamentos as $l): ?>
                                <tr>
                                    <td><?= date("d/m/Y", strtotime($l['data'])) ?></td>
                                    <td><?= htmlspecialchars($l['descricao']) ?></td>
                                    <td><?= htmlspecialchars($l['categoria']) ?></td>
                                    <td>R$ <?= number_format($l['valor'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge <?= $l['tipo'] == 'entrada' ? 'bg-success' : 'bg-danger' ?>">
                                            <?= ucfirst($l['tipo']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="editar.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                        <a href="remover.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Confirma remoção?')">Remover</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center">Nenhum lançamento encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
