<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Adicionar Transação";
include "header.php";

// --- VALIDAR E CARREGAR DADOS DO CARTÃO ---
$cartao_id = $_GET['cartao_id'] ?? null;
if (!$cartao_id) {
    header('Location: cartoes.php');
    exit;
}

$stmt_cartao = $pdo->prepare("SELECT nome, limite, dia_fechamento, dia_vencimento FROM cartoes_credito WHERE id = ?");
$stmt_cartao->execute([$cartao_id]);
$cartao = $stmt_cartao->fetch(PDO::FETCH_ASSOC);

if (!$cartao) {
    echo "<div class='alert alert-danger'>Cartão não encontrado.</div>";
    include "footer.php";
    exit;
}

// --- CÁLCULO DE LIMITE DISPONÍVEL ---
$stmt_limite_usado = $pdo->prepare("
    SELECT SUM(p.valor) as total_pendente
    FROM parcelas_cartao p
    JOIN transacoes_cartao t ON p.transacao_id = t.id
    WHERE t.cartao_id = ? AND p.paga = 0
");
$stmt_limite_usado->execute([$cartao_id]);
$total_usado = $stmt_limite_usado->fetchColumn() ?: 0;
$limite_disponivel = $cartao['limite'] - $total_usado;

$mensagem = '';
$previsao_faturas = [];
$erro = '';

// --- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Função para sanitização de valor
    $toFloat = function ($str) {
        $str = str_replace(['R$', 'r$', ' '], '', $str);
        return (float)str_replace(',', '.', $str);
    };

    // Obter dados do POST
    $descricao = trim($_POST['descricao'] ?? '');
    $data_compra = $_POST['data_compra'] ?? date('Y-m-d');
    $parcelas = max(1, (int)($_POST['parcelas'] ?? 1));
    $parcela_inicial = max(1, (int)($_POST['parcela_inicial'] ?? 1));
    $recorrente = isset($_POST['recorrente']) ? 1 : 0;
    $valor = $toFloat($_POST['valor'] ?? '0'); // Usando 'valor' em vez de 'valor_total'

    $valor_parcela = $parcelas > 0 ? round($valor / $parcelas, 2) : 0.00;

    // Validações
    if (empty($descricao) || empty($data_compra)) {
        $erro = "Preencha todos os campos obrigatórios.";
    } elseif ($valor <= 0) {
        $erro = "O valor total deve ser maior que zero.";
    } elseif ($parcela_inicial > $parcelas) {
        $erro = "Erro: A parcela inicial não pode ser maior que o número total de parcelas.";
    } elseif ($valor > $limite_disponivel) {
        $erro = "Erro: O valor total (R$ " . number_format($valor, 2, ',', '.') . ") excede o limite disponível (R$ " . number_format($limite_disponivel, 2, ',', '.') . ").";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Inserir a transação principal
            $stmt = $pdo->prepare("INSERT INTO transacoes_cartao (cartao_id, descricao, valor, data_compra, parcelas, recorrente) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$cartao_id, $descricao, $valor, $data_compra, $parcelas, $recorrente]);
            $transacao_id = $pdo->lastInsertId();
            
            // 2. Gerar e inserir as parcelas
            $data_compra_obj = new DateTime($data_compra);
            $dia_fechamento = (int)$cartao['dia_fechamento'];
            $dia_vencimento = (int)$cartao['dia_vencimento'];

            for ($i = 0; $i < $parcelas; $i++) {
                $numero_parcela = $i + 1;
                $data_parcela_base = (clone $data_compra_obj)->modify("+$i months");
                $dia_compra_parcela = (int)$data_parcela_base->format('d');
                
                $data_vencimento_obj = (clone $data_parcela_base);
                if ($dia_compra_parcela > $dia_fechamento) {
                    $data_vencimento_obj->modify('+1 month');
                }
                $data_vencimento_obj->setDate($data_vencimento_obj->format('Y'), $data_vencimento_obj->format('m'), $dia_vencimento);

                $paga = ($numero_parcela < $parcela_inicial) ? 1 : 0;

                $stmt_parcela = $pdo->prepare("INSERT INTO parcelas_cartao (transacao_id, numero_parcela, valor, vencimento, paga) VALUES (?, ?, ?, ?, ?)");
                $stmt_parcela->execute([$transacao_id, $numero_parcela, $valor_parcela, $data_vencimento_obj->format('Y-m-d'), $paga]);

                $previsao_faturas[] = [
                    'parcela' => $numero_parcela,
                    'valor' => number_format($valor_parcela, 2, ',', '.'),
                    'fatura' => $data_vencimento_obj->format('m/Y'),
                    'vencimento' => $data_vencimento_obj->format('d/m/Y'),
                    'paga' => $paga
                ];
            }

            $pdo->commit();

            $mensagem = "Transação adicionada com sucesso! Valor total: R$ " . number_format($valor, 2, ',', '.') . ", Parcela: R$ " . number_format($valor_parcela, 2, ',', '.') . " x $parcelas";
            
            header("Location: transacoes_cartao.php?cartao_id=" . $cartao_id);
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = "Erro ao salvar a transação: " . $e->getMessage();
        }
    }
}
?>

<h1>Adicionar Transação - <?= htmlspecialchars($cartao['nome']) ?></h1>
<p>Limite Disponível: R$ <?= number_format($limite_disponivel, 2, ',', '.') ?></p>

<?php if ($mensagem): ?>
    <div class="alert alert-success"><?= $mensagem ?></div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="alert alert-danger"><?= $erro ?></div>
<?php endif; ?>

<form method="post" class="row g-3">
    <input type="hidden" name="cartao_id" value="<?= $cartao_id ?>">
    <div class="col-md-6">
        <label class="form-label">Descrição</label>
        <input type="text" name="descricao" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Data da Compra</label>
        <input type="date" name="data_compra" id="data_compra" class="form-control" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Valor Total</label>
        <input type="text" name="valor" id="valor" class="form-control" placeholder="Ex: 100.00" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Número de Parcelas</label>
        <input type="number" name="parcelas" id="parcelas" class="form-control" min="1" value="1" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Parcela Inicial</label>
        <input type="number" name="parcela_inicial" id="parcela_inicial" class="form-control" min="1" value="1" required>
    </div>
    <div class="col-md-3">
        <div class="form-check mt-5">
            <input class="form-check-input" type="checkbox" name="recorrente" id="recorrente" value="1">
            <label class="form-check-label" for="recorrente">Transação Recorrente (Mensal)</label>
        </div>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="transacoes_cartao.php?cartao_id=<?= $cartao_id ?>" class="btn btn-secondary">Voltar</a>
    </div>
</form>

<?php if ($previsao_faturas): ?>
    <h2 class="mt-5 mb-3">Previsão de Faturas</h2>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Parcela</th>
                    <th>Valor</th>
                    <th>Fatura (Mês/Ano)</th>
                    <th>Vencimento</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($previsao_faturas as $fatura): ?>
                    <tr class="<?= $fatura['paga'] ? 'table-success' : '' ?>">
                        <td><?= $fatura['parcela'] ?> de <?= count($previsao_faturas) ?></td>
                        <td>R$ <?= $fatura['valor'] ?></td>
                        <td><?= $fatura['fatura'] ?></td>
                        <td><?= $fatura['vencimento'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
document.getElementById('recorrente').addEventListener('change', function() {
    const isRecorrente = this.checked;
    const parcelasInput = document.getElementById('parcelas');
    const parcelaInicialInput = document.getElementById('parcela_inicial');
    
    parcelasInput.disabled = isRecorrente;
    parcelaInicialInput.disabled = isRecorrente;

    if(isRecorrente) {
        parcelasInput.value = 1;
        parcelaInicialInput.value = 1;
    }
});
</script>

<?php include "footer.php"; ?>