<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Adicionar Transação";
include "header.php";

$cartao_id = $_GET['cartao_id'];

// Buscar informações do cartão
$stmt_cartao = $pdo->prepare("SELECT nome, limite, dia_fechamento, dia_vencimento FROM cartoes_credito WHERE id = ?");
$stmt_cartao->execute([$cartao_id]);
$cartao = $stmt_cartao->fetch(PDO::FETCH_ASSOC);

// Calcular limite disponível
$stmt_transacoes = $pdo->prepare("SELECT SUM(valor / parcelas) as total_pendente FROM transacoes_cartao WHERE cartao_id = ? AND paga = 0");
$stmt_transacoes->execute([$cartao_id]);
$total_pendente = $stmt_transacoes->fetchColumn() ?? 0;
$limite_disponivel = $cartao['limite'] - $total_pendente;

$mensagem = '';
$previsao_faturas = [];
$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $descricao = $_POST['descricao'];
    $data_compra = $_POST['data_compra'];
    $parcelas = (int)$_POST['parcelas'];
    $tipo_valor = $_POST['tipo_valor'];
    
    // Calcular valor total ou valor da parcela
    if ($tipo_valor == 'parcela') {
        $valor_parcela = str_replace(',', '.', $_POST['valor_parcela']);
        $valor_total = $valor_parcela * $parcelas;
    } else {
        $valor_total = str_replace(',', '.', $_POST['valor_total']);
        $valor_parcela = $valor_total / $parcelas;
    }

    // Validar limite
    if ($valor_total > $limite_disponivel) {
        $erro = "Erro: O valor total (R$ " . number_format($valor_total, 2, ',', '.') . ") excede o limite disponível (R$ " . number_format($limite_disponivel, 2, ',', '.') . ").";
    } else {
        // Inserir transação
        $stmt = $pdo->prepare("INSERT INTO transacoes_cartao (cartao_id, descricao, valor, data_compra, parcelas) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$cartao_id, $descricao, $valor_total, $data_compra, $parcelas]);

        // Calcular previsão de faturas
        $data = new DateTime($data_compra);
        $dia_fechamento = $cartao['dia_fechamento'];
        $dia_vencimento = $cartao['dia_vencimento'];

        for ($i = 0; $i < $parcelas; $i++) {
            $data_parcela = (clone $data)->modify("+$i months");
            $ano = $data_parcela->format('Y');
            $mes = $data_parcela->format('m');
            $dia_compra = (int)$data_parcela->format('d');

            if ($dia_compra > $dia_fechamento) {
                $data_parcela->modify('+1 month');
            }

            $mes_fatura = $data_parcela->format('m');
            $ano_fatura = $data_parcela->format('Y');
            $data_fechamento = new DateTime("$ano_fatura-$mes_fatura-$dia_fechamento");
            $data_vencimento = (clone $data_fechamento)->modify('+1 month');
            $data_vencimento->setDate($data_vencimento->format('Y'), $data_vencimento->format('m'), $dia_vencimento);

            $previsao_faturas[] = [
                'parcela' => $i + 1,
                'valor' => number_format($valor_parcela, 2, ',', '.'),
                'fatura' => $data_fechamento->format('m/Y'),
                'vencimento' => $data_vencimento->format('d/m/Y')
            ];
        }

        $mensagem = "Transação adicionada com sucesso! Valor total: R$ " . number_format($valor_total, 2, ',', '.') . 
                    ", Parcela: R$ " . number_format($valor_parcela, 2, ',', '.') . " x $parcelas";
    }
}
?>

<h1>Adicionar Transação - <?= htmlspecialchars($cartao['nome']) ?></h1>

<?php if ($mensagem): ?>
    <div class="alert alert-success"><?= $mensagem ?></div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="alert alert-danger"><?= $erro ?></div>
<?php endif; ?>

<form method="post" class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Descrição</label>
        <input type="text" name="descricao" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Data da Compra</label>
        <input type="date" name="data_compra" class="form-control" required value="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Número de Parcelas</label>
        <input type="number" name="parcelas" class="form-control" min="1" value="1" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Tipo de Valor</label>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="tipo_valor" id="tipo_valor_total" value="total" checked>
            <label class="form-check-label" for="tipo_valor_total">Valor Total</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="tipo_valor" id="tipo_valor_parcela" value="parcela">
            <label class="form-check-label" for="tipo_valor_parcela">Valor da Parcela</label>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label" id="label_valor">Valor Total</label>
        <input type="text" name="valor_total" id="valor_total" class="form-control" placeholder="Ex: 100.00" required>
        <input type="text" name="valor_parcela" id="valor_parcela" class="form-control d-none" placeholder="Ex: 50.00">
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="transacoes_cartao.php?cartao_id=<?= $cartao_id ?>" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php if ($previsao_faturas): ?>
    <h2 class="mt-5">Previsão de Faturas</h2>
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
                    <tr>
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
document.querySelectorAll('input[name="tipo_valor"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const isTotal = this.value === 'total';
        document.getElementById('valor_total').classList.toggle('d-none', !isTotal);
        document.getElementById('valor_parcela').classList.toggle('d-none', isTotal);
        document.getElementById('label_valor').textContent = isTotal ? 'Valor Total' : 'Valor da Parcela';
    });
});
</script>

<?php include "footer.php"; ?>