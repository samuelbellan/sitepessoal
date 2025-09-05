<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Faturas Consolidadas - Todos os Cartões";
include "header.php";

$hoje = new DateTime();
$mes_atual = $_GET['mes'] ?? $hoje->format('Y-m');

// Gerar opções para meses
$meses = [];
for ($i = -12; $i <= 12; $i++) {
    $data = (clone $hoje)->modify("$i months");
    $meses[] = $data->format('Y-m');
}
sort($meses);

// --- FILTRO POR CARTÃO ---
$filtro_cartao_id = $_GET['cartao_id'] ?? null;
$where_cartao = '';
$params_grafico = [];
if ($filtro_cartao_id) {
    $where_cartao = " AND t.cartao_id = ?";
    $params_grafico[] = $filtro_cartao_id;
}

// Buscar todos os cartões cadastrados para o filtro
$stmt_todos_cartoes = $pdo->query("SELECT id, nome FROM cartoes_credito ORDER BY nome");
$todos_cartoes = $stmt_todos_cartoes->fetchAll(PDO::FETCH_ASSOC);

// --- GRÁFICO ---
$stmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(p.vencimento, '%Y-%m') AS mes_fatura,
        SUM(p.valor) AS total,
        MIN(p.paga) AS alguma_pendente
    FROM parcelas_cartao p
    JOIN transacoes_cartao t ON p.transacao_id = t.id
    WHERE DATE_FORMAT(p.vencimento, '%Y-%m') >= ? {$where_cartao}
    GROUP BY mes_fatura
    ORDER BY mes_fatura ASC
");
$stmt->execute(array_merge([$hoje->format('Y-m')], $params_grafico));
$faturas_consolidadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$labels_grafico = array_column($faturas_consolidadas, 'mes_fatura');
$dados_grafico = array_column($faturas_consolidadas, 'total');
$status_grafico = array_column($faturas_consolidadas, 'alguma_pendente');

// Extrato do mês
$extrato_mes = [];
if ($filtro_cartao_id) {
    $stmt_extrato = $pdo->prepare("
        SELECT p.*, t.descricao AS descricao_transacao, t.valor AS valor_total_transacao, c.nome AS cartao_nome
        FROM parcelas_cartao p
        JOIN transacoes_cartao t ON p.transacao_id = t.id
        JOIN cartoes_credito c ON t.cartao_id = c.id
        WHERE t.cartao_id = ? AND DATE_FORMAT(p.vencimento, '%Y-%m') = ?
        ORDER BY p.vencimento ASC, t.descricao ASC
    ");
    $stmt_extrato->execute([$filtro_cartao_id, $mes_atual]);
    $extrato_mes = $stmt_extrato->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt_extrato = $pdo->prepare("
        SELECT p.*, t.descricao AS descricao_transacao, t.valor AS valor_total_transacao, c.nome AS cartao_nome
        FROM parcelas_cartao p
        JOIN transacoes_cartao t ON p.transacao_id = t.id
        JOIN cartoes_credito c ON t.cartao_id = c.id
        WHERE DATE_FORMAT(p.vencimento, '%Y-%m') = ?
        ORDER BY c.nome, p.vencimento ASC, t.descricao ASC
    ");
    $stmt_extrato->execute([$mes_atual]);
    $extrato_mes = $stmt_extrato->fetchAll(PDO::FETCH_ASSOC);
}
$total_fatura_mes = $extrato_mes ? array_sum(array_column($extrato_mes, 'valor')) : 0;
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container py-4">

    <!-- Topbar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold"><i class="fa fa-file-invoice-dollar text-info"></i> Faturas Consolidadas</h2>
            <small class="text-muted">Visualize os extratos de todos os cartões consolidados por mês</small>
        </div>
        <div>
            <a href="cartoes.php" class="btn btn-outline-primary me-2"><i class="fa fa-arrow-left"></i> Voltar</a>
            <button onclick="window.print()" class="btn btn-info me-2"><i class="fa fa-print"></i> Imprimir</button>
            <button onclick="exportToCSV()" class="btn btn-primary me-2"><i class="fa fa-file-csv"></i> Exportar CSV</button>
            <button onclick="exportToExcel()" class="btn btn-success"><i class="fa fa-file-excel"></i> Exportar Excel</button>
        </div>
    </div>

    <!-- Filtros -->
    <form method="get" class="mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Mês da Fatura (p/ Impressão)</label>
                <select name="mes" class="form-select">
                    <?php foreach ($meses as $m): ?>
                        <option value="<?= $m ?>" <?= $mes_atual == $m ? 'selected' : '' ?>>
                            <?= date('m/Y', strtotime($m . '-01')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Filtrar por Cartão</label>
                <select name="cartao_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($todos_cartoes as $cartao_op): ?>
                        <option value="<?= $cartao_op['id'] ?>" <?= $filtro_cartao_id == $cartao_op['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cartao_op['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa fa-search"></i> Filtrar</button>
            </div>
        </div>
    </form>

    <!-- Gráfico -->
    <?php if ($faturas_consolidadas): ?>
        <div class="card shadow mb-4">
            <div class="card-header bg-info-subtle fw-semibold">
                <i class="fa fa-chart-bar text-info"></i> Totais das Faturas Futuras
                <small class="ms-2 text-muted">(Verde = Paga, Amarelo = Pendente)</small>
            </div>
            <div class="card-body">
                <canvas id="graficoFaturasTodos"></canvas>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">Nenhuma fatura futura encontrada.</div>
    <?php endif; ?>

    <!-- Extrato Detalhado -->
    <div class="card shadow mb-4 print-only" id="extratoTableDiv">
        <div class="card-header bg-warning-subtle fw-semibold">
            <i class="fa fa-list text-warning"></i> Extrato Detalhado - <?= date('m/Y', strtotime($mes_atual . '-01')) ?>
        </div>
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0" id="extratoTable">
                    <thead class="table-light">
                        <tr>
                            <th>Cartão</th>
                            <th>Descrição</th>
                            <th>Parcela</th>
                            <th>Valor Parcela</th>
                            <th>Valor Total</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($extrato_mes): ?>
                            <?php foreach ($extrato_mes as $p): ?>
                                <?php
                                    $status_reg = $p['paga'] ? 'table-success' : '';
                                    $status_texto = $p['paga'] ? "<span class='badge bg-success'>Paga</span>" : "<span class='badge bg-danger'>Pendente</span>";
                                ?>
                                <tr class="<?= $status_reg ?>">
                                    <td><?= htmlspecialchars($p['cartao_nome']) ?></td>
                                    <td><?= htmlspecialchars($p['descricao_transacao']) ?></td>
                                    <td><?= $p['numero_parcela'] ?></td>
                                    <td>R$ <?= number_format($p['valor'], 2, ',', '.') ?></td>
                                    <td>R$ <?= number_format($p['valor_total_transacao'], 2, ',', '.') ?></td>
                                    <td><?= date("d/m/Y", strtotime($p['vencimento'])) ?></td>
                                    <td><?= $status_texto ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center">Nenhuma transação encontrada para este mês.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if ($extrato_mes): ?>
                    <tfoot class="table-dark fw-bold">
                        <tr>
                            <td colspan="3" class="text-end">VALOR TOTAL DA FATURA:</td>
                            <td colspan="4">R$ <?= number_format($total_fatura_mes, 2, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
@media screen {
    .print-only { display: none !important; }
}
@media print {
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    body * { visibility: hidden; }
    .print-only, .print-only * { visibility: visible; }
    .print-only { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
<script>
<?php if ($faturas_consolidadas): ?>
const labelsTodos = <?= json_encode($labels_grafico) ?>;
const valoresTodos = <?= json_encode($dados_grafico) ?>;
const statusTodos = <?= json_encode($status_grafico) ?>;
const coresTodos = statusTodos.map(paga => paga == 1 ? '#28a745' : '#ffc107');

new Chart(document.getElementById('graficoFaturasTodos'), {
    type: 'bar',
    data: {
        labels: labelsTodos,
        datasets: [{
            label: 'Total das Faturas',
            data: valoresTodos,
            backgroundColor: coresTodos,
            borderColor: coresTodos,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'R$ ' + context.parsed.y.toFixed(2).replace('.', ',');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: { display: true, text: 'Valor (R$)' }
            },
            x: {
                title: { display: true, text: 'Fatura (Mês/Ano)' }
            }
        }
    }
});
<?php endif; ?>

function exportToCSV() {
    const extratoTableDiv = document.getElementById('extratoTableDiv');
    extratoTableDiv.style.display = 'block';
    const table = document.getElementById('extratoTable');
    let csv = [];
    const rows = table.querySelectorAll('tr');
    rows.forEach(function(row) {
        let rowData = [];
        const cols = row.querySelectorAll('th, td');
        for (let i = 0; i < cols.length; i++) {
            let text = '"' + cols[i].innerText.replace(/"/g, '""') + '"';
            rowData.push(text);
        }
        csv.push(rowData.join(','));
    });
    extratoTableDiv.style.display = 'none';
    const csvFile = new Blob(["\uFEFF" + csv.join('\n')], {type: 'text/csv;charset=utf-8;'});
    const downloadLink = document.createElement('a');
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.download = `extrato_faturas_<?= $mes_atual ?>.csv`;
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

function exportToExcel() {
    const extratoTableDiv = document.getElementById('extratoTableDiv');
    extratoTableDiv.style.display = 'block';
    const table = document.getElementById('extratoTable');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Extrato");
    XLSX.writeFile(wb, `extrato_faturas_<?= $mes_atual ?>.xlsx`);
    extratoTableDiv.style.display = 'none';
}
</script>
<?php include "footer.php"; ?>
