<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Gestão de Categorias";
include 'header.php';

// Sessão mensagens
$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['erro']);

// Exclusão de categoria
if (isset($_GET['delete_id']) && ctype_digit($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    // Busca o nome da categoria a excluir
    $stmt = $pdo->prepare("SELECT nome FROM categorias WHERE id = ?");
    $stmt->execute([$delete_id]);
    $catNome = $stmt->fetchColumn();

    if ($catNome) {
        // Verifica se possui subcategorias vinculadas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE categoria_pai = ?");
        $stmt->execute([$catNome]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $_SESSION['erro'] = "Não é possível excluir a categoria '{$catNome}' pois possui subcategorias vinculadas.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                $_SESSION['mensagem'] = "Categoria '{$catNome}' removida com sucesso.";
            } else {
                $_SESSION['erro'] = "Falha ao remover a categoria '{$catNome}'.";
            }
        }
    } else {
        $_SESSION['erro'] = "Categoria não encontrada.";
    }
    header("Location: categories.php");
    exit();
}

// Inserção ou edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cadastrar'])) {
        $nome = trim($_POST['nome'] ?? '');
        $limite = str_replace(',', '.', $_POST['limite_ideal'] ?? '');
        $pai = $_POST['pai'] ?: null;

        if ($nome === '' || $limite === '' || !is_numeric($limite)) {
            $_SESSION['erro'] = "Preencha todos os campos corretamente.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO categorias (nome, limite_ideal, categoria_pai) VALUES (?, ?, ?)");
            if ($stmt->execute([$nome, $limite, $pai])) {
                $_SESSION['mensagem'] = "Categoria '{$nome}' cadastrada com sucesso.";
            } else {
                $_SESSION['erro'] = "Erro ao cadastrar a categoria '{$nome}'.";
            }
        }
        header("Location: categories.php");
        exit();
    }
    if (isset($_POST['editar'])) {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $limite = str_replace(',', '.', $_POST['limite_ideal'] ?? '');
        $pai = $_POST['pai'] ?: null;

        if ($id <= 0 || $nome === '' || $limite === '' || !is_numeric($limite)) {
            $_SESSION['erro'] = "Dados inválidos para edição.";
        } else {
            $stmt = $pdo->prepare("UPDATE categorias SET nome = ?, limite_ideal = ?, categoria_pai = ? WHERE id = ?");
            if ($stmt->execute([$nome, $limite, $pai, $id])) {
                $_SESSION['mensagem'] = "Categoria '{$nome}' atualizada com sucesso.";
            } else {
                $_SESSION['erro'] = "Erro ao atualizar a categoria '{$nome}'.";
            }
        }
        header("Location: categories.php");
        exit();
    }
}

// Buscar categorias e organizar hierarquia
$stmt = $pdo->query("SELECT id, nome, limite_ideal, categoria_pai FROM categorias_orcamento ORDER BY categoria_pai, nome");
$cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$parents = [];
$children = [];
foreach ($cats as $cat) {
    if (empty($cat['categoria_pai'])) {
        $parents[] = $cat;
    } else {
        $children[$cat['categoria_pai']][] = $cat;
    }
}
?>

<div class="container mt-4">
    <h1>Gestão de Categorias</h1>

    <?php if ($mensagem): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#modalAdd">+ Nova Categoria</button>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Categoria <button class="btn btn-sm btn-outline-secondary" onclick="toggleAllSubs()">Expand/Collapse Subcategorias</button></th>
                <th>Valor (R$)</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($parents as $parent): ?>
                <?php
                $total = $parent['limite_ideal'];
                if (isset($children[$parent['nome']])) {
                    foreach ($children[$parent['nome']] as $child) {
                        $total += $child['limite_ideal'];
                    }
                }
                ?>
                <tr data-parent-id="<?= $parent['id'] ?>">
                    <td>
                        <?php if (isset($children[$parent['nome']])): ?>
                            <button class="btn btn-sm btn-primary" onclick="toggleSubs(<?= $parent['id'] ?>)">+</button>
                        <?php else: ?>
                            &nbsp;&nbsp;&nbsp;
                        <?php endif; ?>
                        <strong><?= htmlspecialchars($parent['nome']) ?></strong>
                    </td>
                    <td><?= number_format($total,2,',','.') ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="editCat(<?= $parent['id'] ?>, '<?= addslashes($parent['nome']) ?>', '<?= $parent['limite_ideal'] ?>', '')">Editar</button>
                        <a href="?delete_id=<?= $parent['id'] ?>" onclick="return confirm('Confirma exclusão?')" class="btn btn-danger btn-sm">Excluir</a>
                    </td>
                </tr>
                <?php if (isset($children[$parent['nome']])): ?>
                    <?php foreach ($children[$parent['nome']] as $child): ?>
                        <tr class="subrow-<?= $parent['id'] ?>" style="display:none;">
                            <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;↳ <?= htmlspecialchars($child['nome']) ?></td>
                            <td><?= number_format($child['limite_ideal'],2,',','.') ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm" onclick="editCat(<?= $child['id'] ?>, '<?= addslashes($child['nome']) ?>', '<?= $child['limite_ideal'] ?>', '<?= htmlspecialchars(addslashes($parent['nome'])) ?>')">Editar</button>
                                <a href="?delete_id=<?= $child['id'] ?>" onclick="return confirm('Confirma exclusão?')" class="btn btn-danger btn-sm">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Adicionar -->
<div class="modal fade" id="modalAdd" tabindex="-1" aria-labelledby="modalAddLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAddLabel">Nova Categoria / Subcategoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="cadastrar" value="1" />
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input name="nome" type="text" class="form-control" required/>
                </div>
                <div class="mb-3">
                    <label class="form-label">Categoria Pai (opcional)</label>
                    <select name="pai" class="form-select">
                        <option value="">Nenhum (categoria principal)</option>
                        <?php foreach ($parents as $p): ?>
                            <option><?= htmlspecialchars($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Deixe vazio se for categoria principal</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Limite (R$)</label>
                    <input name="limite" type="number" step="0.01" class="form-control" required/>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Adicionar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditLabel">Editar Categoria / Subcategoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="editar" value="1" />
                <input type="hidden" name="id" id="editId" />
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input name="nome" id="editNome" type="text" class="form-control" required />
                </div>
                <div class="mb-3">
                    <label class="form-label">Categoria Pai (opcional)</label>
                    <select name="pai" id="editPai" class="form-select">
                        <option value="">Nenhum (categoria principal)</option>
                        <?php foreach ($parents as $p): ?>
                            <option><?= htmlspecialchars($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Deixe vazio se for categoria principal</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Limite (R$)</label>
                    <input name="limite" id="editLimite" type="number" step="0.01" class="form-control" required />
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-warning">Salvar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCat(id, nome, limite, pai) {
    document.getElementById('editId').value = id;
    document.getElementById('editNome').value = nome;
    document.getElementById('editLimite').value = limite;
    document.getElementById('editPai').value = pai || '';
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}

function toggleSubs(id) {
    const rows = document.querySelectorAll('tr.subrow-' + id);
    rows.forEach(row => {
        row.style.display = (row.style.display === 'none' || row.style.display === '') ? 'table-row' : 'none';
    });
}

function toggleAllSubs() {
    const rows = document.querySelectorAll('tr[class^="subrow-"]');
    const anyHidden = Array.from(rows).some(row => row.style.display === 'none' || row.style.display === '');
    rows.forEach(row => {
        row.style.display = anyHidden ? 'table-row' : 'none';
    });
}
</script>

<?php include 'footer.php'; ?>
