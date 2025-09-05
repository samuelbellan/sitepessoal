<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Transações de Cartão";
include "header.php";

$cartao_id = $_GET['cartao_id'] ?? null;

if (!$cartao_id) {
    echo "<div class='alert alert-danger'>ID do cartão não fornecido.</div>";
    include "footer.php";
    exit;
}

// Buscar dados do cartão
$stmt_cartao = $pdo->prepare("SELECT nome, limite FROM cartoes_credito WHERE id = ?");
$stmt_cartao->execute([$cartao_id]);
$cartao = $stmt_cartao->fetch(PDO::FETCH_ASSOC);

if (!$cartao) {
    echo "<div class='alert alert-danger'>Cartão não encontrado.</div>";
    include "footer.php";
    exit;
}

// --- FILTRO DE MÊS ---
$filtro_mes = $_GET['mes'] ?? date('Y-m');

// Gerar opções para meses (últimos 12 meses, mês atual e próximos 12 meses)
$meses = [];
$hoje = new DateTime();
for ($i = -12; $i <= 12; $i++) {
    $data = (clone $hoje)->modify("$i months");
    $meses[] = $data->format('Y-m');
}
sort($meses);

// Cálculo de uso do limite
$stmt_uso_limite = $pdo->prepare("
    SELECT SUM(p.valor) AS total_pendente
    FROM parcelas_cartao p
    JOIN transacoes_cartao t ON p.transacao_id = t.id
    WHERE t.cartao_id = ? AND p.paga = 0
");
$stmt_uso_limite->execute([$cartao_id]);
$total_pendente = $stmt_uso_limite->fetchColumn() ?: 0;
$limite_disponivel = $cartao['limite'] - $total_pendente;

// Buscar parcelas do mês filtrado
$stmt_parcelas = $pdo->prepare("
    SELECT p.*, t.descricao, t.valor AS valor_total_transacao, t.parcelas as total_parcelas
    FROM parcelas_cartao p
    JOIN transacoes_cartao t ON p.transacao_id = t.id
    WHERE t.cartao_id = ? AND DATE_FORMAT(p.vencimento, '%Y-%m') = ?
    ORDER BY p.vencimento ASC
");
$stmt_parcelas->execute([$cartao_id, $filtro_mes]);
$parcelas_mes = $stmt_parcelas->fetchAll(PDO::FETCH_ASSOC);
$soma_parcelas = array_sum(array_column($parcelas_mes, 'valor'));
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container py-4">
    <!-- Top e filtros -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold"><i class="fa fa-credit-card text-warning"></i> Extrato do Cartão: <?= htmlspecialchars($cartao['nome']) ?></h2>
            <small class="text-muted">Veja as transações, parcelas e limite de crédito</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="adicionar_transacao.php?cartao_id=<?= $cartao_id ?>" class="btn btn-success"><i class="fa fa-plus"></i> Nova Transação</a>
            <button onclick="exportToCSV()" class="btn btn-primary"><i class="fa fa-file-csv"></i> Exportar CSV</button>
            <button onclick="exportToExcel()" class="btn btn-success"><i class="fa fa-file-excel"></i> Exportar Excel</button>
            <button onclick="exportToPDF()" class="btn btn-danger"><i class="fa fa-file-pdf"></i> Exportar PDF</button>
            <a href="cartoes.php" class="btn btn-outline-primary"><i class="fa fa-arrow-left"></i> Voltar Cartões</a>
        </div>
    </div>

    <form method="get" class="mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Mês</label>
                <select name="mes" class="form-select">
                    <?php foreach ($meses as $m): ?>
                        <option value="<?= $m ?>" <?= $filtro_mes == $m ? 'selected' : '' ?>><?= date('m/Y', strtotime($m . '-01')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="fa fa-search"></i> Filtrar</button>
            </div>
        </div>
        <input type="hidden" name="cartao_id" value="<?= $cartao_id ?>">
    </form>

    <div class="row mb-4 g-3">
        <div class="col-md-6">
            <div class="card shadow h-100 bg-gradient bg-primary-subtle border-0">
                <div class="card-body text-center">
                    <i class="fa fa-university fa-2x text-primary"></i>
                    <div class="fw-semibold mt-2">Limite Total</div>
                    <div class="fs-4 fw-bold">R$ <?= number_format($cartao['limite'], 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow h-100 bg-gradient bg-warning-subtle border-0">
                <div class="card-body text-center">
                    <i class="fa fa-money-bill-wave fa-2x text-warning"></i>
                    <div class="fw-semibold mt-2">Limite Disponível</div>
                    <div class="fs-4 fw-bold">R$ <?= number_format($limite_disponivel, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-info-subtle fw-semibold">
            <i class="fa fa-list text-info"></i> Parcela(s) do Mês <?= date("m/Y", strtotime($filtro_mes.'-01')) ?>
        </div>
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0" id="extratoTable">
                    <thead class="table-light">
                        <tr>
                            <th>ID Parcela</th>
                            <th>Descrição</th>
                            <th>Valor Parcela</th>
                            <th>Parcela</th>
                            <th>Valor Total</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($parcelas_mes): ?>
                            <?php foreach ($parcelas_mes as $p): ?>
                                <?php 
                                    $status_cor = $p['paga'] ? 'table-success' : 'table-danger';
                                    $status_texto = $p['paga'] ? "<span class='badge bg-success'>Paga</span>" : "<span class='badge bg-danger'>Pendente</span>";
                                ?>
                                <tr class="<?= $status_cor ?>">
                                    <td><?= $p['id'] ?></td>
                                    <td><?= htmlspecialchars($p['descricao']) ?></td>
                                    <td>R$ <?= number_format($p['valor'], 2, ',', '.') ?></td>
                                    <td><?= $p['numero_parcela'] ?> de <?= $p['total_parcelas'] ?></td>
                                    <td>R$ <?= number_format($p['valor_total_transacao'], 2, ',', '.') ?></td>
                                    <td><?= date("d/m/Y", strtotime($p['vencimento'])) ?></td>
                                    <td><?= $status_texto ?></td>
                                    <td>
                                        <a href="editar_parcela.php?id=<?= $p['id'] ?>&cartao_id=<?= $cartao_id ?>" class="btn btn-sm btn-warning"><i class="fa fa-edit"></i> Editar</a>
                                        <a href="remover_parcela.php?id=<?= $p['id'] ?>&cartao_id=<?= $cartao_id ?>&mes=<?= $filtro_mes ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja remover esta parcela?')"><i class="fa fa-trash"></i> Remover</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center">Nenhuma parcela encontrada para este mês.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if ($parcelas_mes): ?>
                    <tfoot><!-- Totalizador do mês -->
                        <tr class="table-secondary fw-bold">
                            <td colspan="2" class="text-end">Somatório do Mês:</td>
                            <td class="text-start">R$ <?= number_format($soma_parcelas, 2, ',', '.') ?></td>
                            <td colspan="5"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
function exportToCSV() {
    const table = document.getElementById('extratoTable');
    const rows = table.querySelectorAll('tbody tr');
    const data = [];
    const headers = ['ID Parcela', 'Descrição', 'Valor Parcela', 'Parcela', 'Valor Total', 'Vencimento', 'Status'];
    data.push(headers);
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 7) {
            const rowData = [
                cells[0].innerText || '',
                cells[1].innerText || '',
                cells[2].innerText || '',
                cells[3].innerText || '',
                cells[4].innerText || '',
                cells[5].innerText || '',
                cells[6].innerText || ''
            ];
            data.push(rowData);
        }
    });
    const footer = ['', 'Somatório', 'R$ <?= number_format($soma_parcelas, 2, ',', '.') ?>', '', '', '', ''];
    data.push(footer);
    const csv = Papa.unparse(data);
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'extrato_<?= htmlspecialchars($cartao['nome']) ?>_<?= $filtro_mes ?>.csv';
    link.click();
}

function exportToExcel() {
    const table = document.getElementById('extratoTable');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Extrato");
    XLSX.writeFile(wb, `extrato_<?= htmlspecialchars($cartao['nome']) ?>_<?= $filtro_mes ?>.xlsx`);
}

function exportToPDF() {
    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const table = document.getElementById('extratoTable');
        html2canvas(table).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const imgProps = doc.getImageProperties(imgData);
            const pdfWidth = doc.internal.pageSize.getWidth();
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
            doc.addImage(imgData, 'PNG', 10, 10, pdfWidth - 20, pdfHeight);
            doc.save('extrato_<?= htmlspecialchars($cartao['nome']) ?>_<?= $filtro_mes ?>.pdf');
        });
    } catch (error) {
        console.error('Erro ao exportar para PDF:', error.message, error.stack);
        alert('Ocorreu um erro ao exportar para PDF: ' + error.message + '. Verifique o console para mais detalhes.');
    }
}
</script>
<?php include "footer.php"; ?>
