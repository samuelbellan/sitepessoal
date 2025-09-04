<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Gerenciar Cartões de Crédito";
include "header.php";

// Buscar cartões
$stmt = $pdo->query("SELECT * FROM cartoes_credito ORDER BY nome");
$cartoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Gerenciar Cartões de Crédito</h1>
    <div>
        <a href="adicionar_cartao.php" class="btn btn-success">+ Adicionar Cartão</a>
        <a href="faturas_todos.php" class="btn btn-info ms-2">Ver Faturas de Todos os Cartões</a>
        <a href="index.php" class="btn btn-secondary ms-2">Voltar para o Controle Financeiro</a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Limite</th>
                <th>Dia Fechamento</th>
                <th>Dia Vencimento</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($cartoes): ?>
                <?php foreach ($cartoes as $c): ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td><?= htmlspecialchars($c['nome']) ?></td>
                        <td>R$ <?= number_format($c['limite'], 2, ',', '.') ?></td>
                        <td><?= $c['dia_fechamento'] ?></td>
                        <td><?= $c['dia_vencimento'] ?></td>
                        <td>
                            <a href="transacoes_cartao.php?cartao_id=<?= $c['id'] ?>" class="btn btn-sm btn-info">Transações</a>
                            <a href="editar_cartao.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                            <a href="remover_cartao.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja remover este cartão? Todas as transações serão excluídas.')">Remover</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center">Nenhum cartão cadastrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "footer.php"; ?>