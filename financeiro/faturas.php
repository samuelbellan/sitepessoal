<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Faturas Consolidadas";
include "header.php";

$cartao_id = $_GET['cartao_id'];

// Buscar informações do cartão
$stmt_cartao = $pdo->prepare("SELECT nome, limite, dia_fechamento, dia_vencimento FROM cartoes_credito WHERE id = ?");
$stmt_cartao->execute([$cartao_id]);
$cartao = $stmt_cartao->fetch(PDO::FETCH_ASSOC);

// Buscar todas as transações
$stmt = $pdo->prepare("SELECT * FROM transacoes_cartao WHERE cartao_id = ?");
$stmt->execute([$cartao_id]);
$transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dia_fechamento = $cartao['dia_fechamento'];
$dia_vencimento = $cartao['dia_vencimento'];

// Agrupar faturas
$faturas = [];
$transacao_faturas = [];
foreach ($transacoes as $t) {
    $data = new DateTime($t['data_compra']);
    $valor_parcela = $t['valor'] / $t['parcelas'];

    // Determinar a primeira fatura
    if ($t['primeira_fatura']) {
        $data_primeira_fatura = new DateTime($t['primeira_fatura'] . '-01');
    } else {
        $data_primeira_fatura = clone $data;
        $dia_compra = (int)$data->format('d');
        if ($dia_compra > $dia_fechamento) {
            $data_primeira_fatura->modify('+1 month');
        }
    }

    for ($i = 0; $i < $t['parcelas']; $i++) {
        $data_parcela = (clone $data_primeira_fatura)->modify("+$i months");
        $mes_fatura = $data_parcela->format('m');
        $ano_fatura = $data_parcela->format('Y');
        $key = "$ano_fatura-$mes_fatura";

        $data_fechamento_dt = new DateTime("$ano_fatura-$mes_fatura-$dia_fechamento");
        $data_vencimento_dt = (clone $data_fechamento_dt)->modify('+1 month');
        $data_vencimento_dt->setDate($data_vencimento_dt->format('Y'), $data_vencimento_dt->format('m'), $dia_vencimento);

        if (!isset($faturas[$key])) {
            $faturas[$key] = [
                'fatura' => $data_fechamento_dt->format('m/Y'),
                'vencimento' => $data_vencimento_dt->format('d/m/Y'),
                'data_vencimento_sql' => $data_vencimento_dt->format('Y-m-d'),
                'total' => 0,
                'detalhes' => [],
                'paga' => true
            ];
        }

        $faturas[$key]['total'] += $valor_parcela;
        $faturas[$key]['detalhes'][] = [
            'transacao_id' => $t['id'],
            'parcela_num' => $i + 1,
            'descricao' => $t['descricao'],
            'valor_parcela' => $valor_parcela,
            'paga' => $t['paga']
        ];
        $transacao_faturas[$key][] = ['transacao_id' => $t['id'], 'parcela_num' => $i + 1];
        if (!$t['paga']) {
            $faturas[$key]['paga'] = false;
        }
    }
}

// Ordenar por chave (data)
ksort($faturas);

// Marcar fatura como paga
if (isset($_GET['marcar_paga']) && isset($_GET['fatura'])) {
    $fatura_key = $_GET['fatura'];
    if (isset($faturas[$fatura_key]) && !$faturas[$fatura_key]['paga']) {
        $stmt_financeiro = $pdo->prepare("INSERT INTO financeiro (descricao, valor, tipo, data, categoria) VALUES (?, ?, 'saida', ?, ?)");
        $stmt_financeiro->execute([
            "Pagamento da Fatura {$faturas[$fatura_key]['fatura']} - {$cartao['nome']}",
            $faturas[$fatura_key]['total'],
            $faturas[$fatura_key]['data_vencimento_sql'],
            "Pagamento de Cartão {$cartao['nome']}"
        ]);

        foreach ($transacao_faturas[$fatura_key] as $tf) {
            $stmt_update = $pdo->prepare("UPDATE transacoes_cartao SET paga = 1 WHERE id = ?");
            $stmt_update->execute([$tf['transacao_id']]);
        }

        header("Location: faturas.php?cartao_id=$cartao_id");
        exit;
    }
}

// Desfazer pagamento
if (isset($_GET['desfazer_paga']) && isset($_GET['fatura'])) {
    $fatura_key = $_GET['fatura'];
    if (isset($faturas[$fatura_key]) && $faturas[$fatura_key]['paga']) {
        $stmt_delete = $pdo->prepare("DELETE FROM financeiro WHERE descricao = ? AND data = ?");
        $stmt_delete->execute([
            "Pagamento da Fatura {$faturas[$fatura_key]['fatura']} - {$cartao['nome']}",
            $faturas[$fatura_key]['data_vencimento_sql']
        ]);

        foreach ($transacao_faturas[$fatura_key] as $tf) {
            $stmt_update = $pdo->prepare("UPDATE transacoes_cartao SET paga = 0 WHERE id = ?");
            $stmt_update->execute([$tf['transacao_id']]);
        }

        header("Location: faturas.php?cartao_id=$cartao_id");
        exit;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Faturas Consolidadas - <?= htmlspecialchars($cartao['nome']) ?></h1>
    <a href="transacoes_cartao.php?cartao_id=<?= $cartao_id ?>" class="btn btn-secondary">Voltar</a>
</div>

<!-- Gráfico -->
<?php if ($faturas): ?>
    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Totais das Faturas (Pagas e Pendentes)</h5>
                    <canvas id="graficoFaturas"></canvas>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($faturas): ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Fatura (Mês/Ano)</th>
                    <th>Total</th>
                    <th>Vencimento</th>
                    <th>Status</th>
                    <th>Detalhes</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($faturas as $key => $f): ?>
                    <tr>
                        <td><?= $f['fatura'] ?></td>
                        <td>R$ <?= number_format($f['total'], 2, ',', '.') ?></td>
                        <td><?= $f['vencimento'] ?></td>
                        <td>
                            <span class="badge <?= $f['paga'] ? 'bg-success' : 'bg-warning' ?>">
                                <?= $f['paga'] ? 'Paga' : 'Pendente' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($f['detalhes']): ?>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($f['detalhes'] as $d): ?>
                                        <li><?= htmlspecialchars($d['descricao']) ?> (Parc. <?= $d['parcela_num'] ?>): R$ <?= number_format($d['valor_parcela'], 2, ',', '.') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                Sem detalhes.
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$f['paga']): ?>
                                <a href="faturas.php?cartao_id=<?= $cartao_id ?>&marcar_paga=1&fatura=<?= $key ?>" class="btn btn-sm btn-success" onclick="return confirm('Confirmar pagamento da fatura de <?= $f['fatura'] ?>? Isso adicionará uma saída no controle financeiro.')">Marcar como Paga</a>
                            <?php else: ?>
                                <a href="faturas.php?cartao_id=<?= $cartao_id ?>&desfazer_paga=1&fatura=<?= $key ?>" class="btn btn-sm btn-danger" onclick="return confirm('Desfazer pagamento da fatura de <?= $f['fatura'] ?>? Isso removerá a saída do controle financeiro.')">Desfazer Pagamento</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">Nenhuma fatura encontrada.</div>
<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if ($faturas): ?>
    const faturasLabels = <?= json_encode(array_column($faturas, 'fatura')) ?>;
    const faturasValores = <?= json_encode(array_column($faturas, 'total')) ?>;
    const faturasStatus = <?= json_encode(array_column($faturas, 'paga')) ?>;

    const cores = faturasStatus.map(paga => paga ? '#28a745' : '#ffc107');

    new Chart(document.getElementById('graficoFaturas'), {
        type: 'bar',
        data: {
            labels: faturasLabels,
            datasets: [{
                label: 'Total da Fatura',
                data: faturasValores,
                backgroundColor: cores,
                borderColor: '#000',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
<?php endif; ?>
</script>

<?php include "footer.php"; ?>
