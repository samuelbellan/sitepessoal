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

// --- CÁLCULO DE USO DO LIMITE ---
// Somar o valor de todas as parcelas pendentes (paga=0)
$stmt_uso_limite = $pdo->prepare("
    SELECT SUM(p.valor) AS total_pendente
    FROM parcelas_cartao p
    JOIN transacoes_cartao t ON p.transacao_id = t.id
    WHERE t.cartao_id = ? AND p.paga = 0
");
$stmt_uso_limite->execute([$cartao_id]);
$total_pendente = $stmt_uso_limite->fetchColumn() ?: 0;
$limite_disponivel = $cartao['limite'] - $total_pendente;

// --- BUSCAR PARCELAS DO MÊS FILTRADO ---
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

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Extrato do Cartão: <?= htmlspecialchars($cartao['nome']) ?></h1>
    <div>
        <a href="adicionar_transacao.php?cartao_id=<?= $cartao_id ?>" class="btn btn-success">+ Adicionar Transação</a>
        <button onclick="exportToCSV()" class="btn btn-primary">Exportar CSV</button>
        <button onclick="exportToExcel()" class="btn btn-primary">Exportar Excel</button>
        <button onclick="exportToPDF()" class="btn btn-primary">Exportar PDF</button>
    </div>
</div>

<form method="get" class="mb-4">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Mês</label>
            <select name="mes" class="form-select">
                <?php foreach ($meses as $m): ?>
                    <option value="<?= $m ?>" <?= $filtro_mes == $m ? 'selected' : '' ?>><?= date('m/Y', strtotime($m . '-01')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
    </div>
    <input type="hidden" name="cartao_id" value="<?= $cartao_id ?>">
</form>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm text-bg-primary">
            <div class="card-body">
                <h5>Limite Total</h5>
                <p class="fs-4">R$ <?= number_format($cartao['limite'], 2, ',', '.') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm text-bg-warning">
            <div class="card-body">
                <h5>Limite Disponível</h5>
                <p class="fs-4">R$ <?= number_format($limite_disponivel, 2, ',', '.') ?></p>
            </div>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-bordered align-middle" id="extratoTable">
        <thead class="table-dark">
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
                            <a href="editar_parcela.php?id=<?= $p['id'] ?>&cartao_id=<?= $cartao_id ?>" class="btn btn-sm btn-warning">Editar</a>
                            <a href="remover_parcela.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja remover esta parcela?')">Remover</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="text-center">Nenhuma parcela encontrada para este mês.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="table-responsive">
    <table class="table table-bordered">
        <tr class="table-secondary">
            <td colspan="2" class="text-end">Somatório do Mês:</td>
            <td class="text-start">R$ <?= number_format($soma_parcelas, 2, ',', '.') ?></td>
            <td colspan="5"></td>
        </tr>
    </table>
</div>

<a href="cartoes.php" class="btn btn-secondary mt-3">Voltar para Cartões</a>

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
    try {
        if (typeof XLSX === 'undefined') {
            throw new Error('Biblioteca XLSX não foi carregada. Verifique o CDN ou inclua a biblioteca localmente.');
        }

        const table = document.getElementById('extratoTable');
        const rows = table.querySelectorAll('tbody tr');
        const data = [];
        const headers = ['ID Parcela', 'Descrição', 'Valor Parcela', 'Parcela', 'Valor Total', 'Vencimento', 'Status'];
        data.push(headers);
        rows.forEach((row, index) => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 7) {
                const valorParcelaText = cells[2].innerText.replace('R$ ', '').replace(',', '.').trim();
                const totalText = cells[4].innerText.replace('R$ ', '').replace(',', '.').trim();
                const valorParcela = parseFloat(valorParcelaText);
                const total = parseFloat(totalText);
                const rowData = [
                    cells[0].innerText || '',
                    cells[1].innerText || '',
                    isNaN(valorParcela) ? cells[2].innerText : valorParcela,
                    cells[3].innerText || '',
                    isNaN(total) ? cells[4].innerText : total,
                    cells[5].innerText || '',
                    cells[6].innerText || ''
                ];
                if (rowData.length !== headers.length) {
                    throw new Error(`Linha ${index + 1} tem ${rowData.length} colunas, esperado ${headers.length}.`);
                }
                data.push(rowData);
            }
        });
        
        console.log('Dados para exportação Excel:', data);
        if (!data || data.length === 0) {
            throw new Error('Nenhum dado disponível para exportação.');
        }
        
        const ws = XLSX.utils.aoa_to_sheet(data);
        console.log('Worksheet criada:', ws);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Extrato');
        console.log('Workbook criado:', wb);
        XLSX.writeFile(wb, `extrato_<?= htmlspecialchars($cartao['nome']) ?>_<?= $filtro_mes ?>.xlsx`);
    } catch (error) {
        console.error('Erro ao exportar para Excel:', error.message, error.stack);
        alert('Ocorreu um erro ao exportar para Excel: ' + error.message + '. Verifique o console para mais detalhes.');
    }
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