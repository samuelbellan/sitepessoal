<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Editar Transação";
include "header.php";

$transacao_id = $_GET['id'];
$cartao_id = $_GET['cartao_id'];

// Buscar informações do cartão
$stmt_cartao = $pdo->prepare("SELECT nome, limite, dia_fechamento, dia_vencimento FROM cartoes_credito WHERE id = ?");
$stmt_cartao->execute([$cartao_id]);
$cartao = $stmt_cartao->fetch(PDO::FETCH_ASSOC);

// Buscar transação
$stmt = $pdo->prepare("SELECT * FROM transacoes_cartao WHERE id = ? AND cartao_id = ?");
$stmt->execute([$transacao_id, $cartao_id]);
$transacao = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transacao) {
    die("Transação não encontrada.");
}

// Calcular limite disponível
$stmt_transacoes = $pdo->prepare("SELECT id, valor, parcelas, parcela_atual, recorrente, paga FROM transacoes_cartao WHERE cartao_id = ? AND id != ?");
$stmt_transacoes->execute([$cartao_id, $transacao_id]);
$transacoes = $stmt_transacoes->fetchAll(PDO::FETCH_ASSOC);
$total_usado = 0;
foreach ($transacoes as $t) {
    $is_recorrente = isset($t['recorrente']) && $t['recorrente'] == 1;
    if ($is_recorrente) {
        if (!$t['paga']) {
            $total_usado += $t['valor'];
        }
    } else {
        $valor_parcela = $t['valor'] / $t['parcelas'];
        $parcelas_pendentes = $t['paga'] ? 0 : max(0, $t['parcelas'] - ($t['parcela_atual'] - 1));
        $total_usado += $valor_parcela * $parcelas_pendentes;
    }
}
$limite_disponivel = $cartao['limite'] - $total_usado;

$mensagem = '';
$previsao_faturas = [];
$erro = '';
$mostrar_anteriores = isset($_GET['mostrar_anteriores']) && $_GET['mostrar_anteriores'] == '1';
$campos = [
    'descricao' => $transacao['descricao'],
    'data_compra' => $transacao['data_compra'] ?: '',
    'parcela_atual' => $transacao['parcela_atual'],
    'total_parcelas' => $transacao['parcelas'],
    'tipo_valor' => 'parcela',
    'valor_parcela' => number_format($transacao['valor'] / $transacao['parcelas'], 2, ',', '.'),
    'primeira_fatura' => $transacao['primeira_fatura'] ?? '',
    'recorrente' => $transacao['recorrente'] ?? 0
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $campos['descricao'] = $_POST['descricao'] ?? '';
    $campos['data_compra'] = $_POST['data_compra'] ?: date('Y-m-d');
    $campos['tipo_valor'] = $_POST['tipo_valor'] ?? 'parcela';
    $campos['valor_parcela'] = str_replace(',', '.', $_POST['valor_parcela'] ?? '');
    $campos['primeira_fatura'] = $_POST['primeira_fatura'] ?? '';
    $campos['recorrente'] = isset($_POST['recorrente']) ? 1 : 0;

    // Definir valores padrão para parcela_atual e total_parcelas
    if ($campos['recorrente']) {
        $campos['parcela_atual'] = 1;
        $campos['total_parcelas'] = 1;
    } else {
        $campos['parcela_atual'] = isset($_POST['parcela_atual']) ? (int)$_POST['parcela_atual'] : 1;
        $campos['total_parcelas'] = isset($_POST['total_parcelas']) ? (int)$_POST['total_parcelas'] : 1;
    }

    if (!$erro) {
        // Validar parcela atual para transações não recorrentes
        if (!$campos['recorrente'] && ($campos['parcela_atual'] < 1 || $campos['parcela_atual'] > $campos['total_parcelas'])) {
            $erro = "Erro: A parcela atual deve estar entre 1 e o total de parcelas.";
        }

        // Validar valor_parcela
        if (!is_numeric($campos['valor_parcela']) || $campos['valor_parcela'] <= 0) {
            $erro = "Erro: O valor da parcela deve ser um número positivo.";
        }

        if (!$erro) {
            $valor_total = $campos['valor_parcela'] * $campos['total_parcelas'];
            $valor_total_atual = $transacao['valor'] / $transacao['parcelas'] * max(0, $transacao['parcelas'] - ($transacao['parcela_atual'] - 1));
            $limite_disponivel += $valor_total_atual; // Liberar o valor pendente atual
            if ($valor_total > $limite_disponivel) {
                $erro = "Erro: O valor total (R$ " . number_format($valor_total, 2, ',', '.') . ") excede o limite disponível (R$ " . number_format($limite_disponivel, 2, ',', '.') . ").";
            } else {
                $stmt = $pdo->prepare("UPDATE transacoes_cartao SET descricao = ?, valor = ?, data_compra = ?, parcelas = ?, primeira_fatura = ?, parcela_atual = ?, recorrente = ? WHERE id = ? AND cartao_id = ?");
                $stmt->execute([
                    $campos['descricao'],
                    $valor_total,
                    $campos['data_compra'],
                    $campos['total_parcelas'],
                    $campos['primeira_fatura'] ?: null,
                    $campos['parcela_atual'],
                    $campos['recorrente'],
                    $transacao_id,
                    $cartao_id
                ]);

                $data = new DateTime($campos['data_compra']);
                $dia_fechamento = $cartao['dia_fechamento'];
                $dia_vencimento = $cartao['dia_vencimento'];
                $mes_atual = date('Y-m');

                if ($campos['primeira_fatura']) {
                    $data_primeira_fatura = new DateTime($campos['primeira_fatura'] . '-01');
                } else {
                    $data_primeira_fatura = clone $data;
                    $dia_compra = (int)$data->format('d');
                    if ($dia_compra > $dia_fechamento) {
                        $data_primeira_fatura->modify('+1 month');
                    }
                }

                $offset = $campos['recorrente'] ? 0 : $campos['parcela_atual'] - 1;
                $data_primeira_fatura->modify("-$offset months");

                $num_faturas = $campos['recorrente'] ? 12 : $campos['total_parcelas'];
                for ($i = 0; $i < $num_faturas; $i++) {
                    $data_parcela = (clone $data_primeira_fatura)->modify("+$i months");
                    $mes_fatura = $data_parcela->format('m');
                    $ano_fatura = $data_parcela->format('Y');
                    $data_fechamento = new DateTime("$ano_fatura-$mes_fatura-$dia_fechamento");
                    $data_vencimento = (clone $data_fechamento)->modify('+1 month');
                    $data_vencimento->setDate($data_vencimento->format('Y'), $data_vencimento->format('m'), $dia_vencimento);

                    $previsao_faturas[] = [
                        'parcela' => $campos['recorrente'] ? 'Recorrente' : ($i + 1),
                        'valor' => number_format($campos['valor_parcela'], 2, ',', '.'),
                        'fatura' => $data_fechamento->format('m/Y'),
                        'vencimento' => $data_vencimento->format('d/m/Y'),
                        'atual' => "$ano_fatura-$mes_fatura" >= $mes_atual
                    ];
                }

                $mensagem = "Transação atualizada com sucesso! Valor total: R$ " . number_format($valor_total, 2, ',', '.') . 
                            ($campos['recorrente'] ? " (Recorrente)" : ", Parcela: R$ " . number_format($campos['valor_parcela'], 2, ',', '.') . 
                            " (Parcela {$campos['parcela_atual']}/{$campos['total_parcelas']})");

                $campos = [
                    'descricao' => '',
                    'data_compra' => '',
                    'parcela_atual' => 1,
                    'total_parcelas' => 1,
                    'tipo_valor' => 'parcela',
                    'valor_parcela' => '',
                    'primeira_fatura' => '',
                    'recorrente' => 0
                ];
            }
        }
    }
}

// Gerar opções para meses
$meses_faturas = [];
$hoje = new DateTime();
for ($i = -12; $i <= 12; $i++) {
    $data = (clone $hoje)->modify("$i months");
    $meses_faturas[] = $data->format('Y-m');
}
sort($meses_faturas);
?>

<h1>Editar Transação - <?= htmlspecialchars($cartao['nome']) ?></h1>

<?php if ($mensagem): ?>
    <div class="alert alert-success"><?= $mensagem ?></div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="alert alert-danger"><?= $erro ?></div>
<?php endif; ?>

<form method="post" class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Descrição</label>
        <input type="text" name="descricao" class="form-control" value="<?= htmlspecialchars($campos['descricao']) ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Data da Compra (Opcional)</label>
        <input type="date" name="data_compra" id="data_compra" class="form-control" value="<?= $campos['data_compra'] ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Parcela Atual</label>
        <input type="number" name="parcela_atual" id="parcela_atual" class="form-control" min="1" value="<?= $campos['parcela_atual'] ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Total de Parcelas</label>
        <input type="number" name="total_parcelas" id="total_parcelas" class="form-control" min="1" value="<?= $campos['total_parcelas'] ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Valor da Parcela</label>
        <input type="text" name="valor_parcela" id="valor_parcela" class="form-control" value="<?= $campos['valor_parcela'] ?>" placeholder="Ex: 22.99" required>
        <input type="hidden" name="tipo_valor" value="parcela">
    </div>
    <div class="col-md-6">
        <label class="form-label">Mês da Primeira Fatura (Opcional)</label>
        <select name="primeira_fatura" id="primeira_fatura" class="form-select">
            <option value="">Automático (baseado na data da compra)</option>
            <?php foreach ($meses_faturas as $mf): ?>
                <option value="<?= $mf ?>" <?= $campos['primeira_fatura'] == $mf ? 'selected' : '' ?>><?= date('m/Y', strtotime($mf . '-01')) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="recorrente" id="recorrente" value="1" <?= $campos['recorrente'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="recorrente">Transação Recorrente (Mensal)</label>
        </div>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="transacoes_cartao.php?cartao_id=<?= $cartao_id ?>" class="btn btn-secondary">Voltar</a>
    </div>
</form>

<?php if ($previsao_faturas): ?>
    <div class="d-flex justify-content-between align-items-center mt-5 mb-3">
        <h2>Previsão de Faturas</h2>
        <a href="?id=<?= $transacao_id ?>&cartao_id=<?= $cartao_id ?>&mostrar_anteriores=<?= $mostrar_anteriores ? '0' : '1' ?>" class="btn btn-info">
            <?= $mostrar_anteriores ? 'Ocultar Faturas Anteriores' : 'Mostrar Faturas Anteriores' ?>
        </a>
    </div>
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
                    <?php if ($fatura['atual'] || $mostrar_anteriores): ?>
                        <tr>
                            <td><?= $fatura['parcela'] ?> <?= $fatura['parcela'] == 'Recorrente' ? '' : 'de ' . $campos['total_parcelas'] ?></td>
                            <td>R$ <?= $fatura['valor'] ?></td>
                            <td><?= $fatura['fatura'] ?> <?= !$fatura['atual'] ? '<span class="badge bg-secondary">Passada</span>' : '' ?></td>
                            <td><?= $fatura['vencimento'] ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
const diaFechamento = <?= $cartao['dia_fechamento'] ?>;

function calcularPrimeiraFatura() {
    let dataCompra = document.getElementById('data_compra').value;
    const parcelaAtual = parseInt(document.getElementById('parcela_atual').value) || 1;
    const totalParcelas = parseInt(document.getElementById('total_parcelas').value) || 1;
    const recorrente = document.getElementById('recorrente').checked;
    const selectPrimeiraFatura = document.getElementById('primeira_fatura');

    document.getElementById('parcela_atual').disabled = recorrente;
    document.getElementById('total_parcelas').disabled = recorrente;

    if (recorrente) {
        selectPrimeiraFatura.value = '';
        return;
    }

    if (parcelaAtual > 0 && totalParcelas >= parcelaAtual) {
        let data;
        if (dataCompra) {
            data = new Date(dataCompra);
        } else {
            data = new Date();
            data.setDate(1);
        }
        const diaCompra = data.getDate();
        let primeiraFatura = new Date(data);

        if (diaCompra > diaFechamento) {
            primeiraFatura.setMonth(primeiraFatura.getMonth() + 1);
        }

        primeiraFatura.setMonth(primeiraFatura.getMonth() - (parcelaAtual - 1));

        const ano = primeiraFatura.getFullYear();
        const mes = String(primeiraFatura.getMonth() + 1).padStart(2, '0');
        const valorCalculado = `${ano}-${mes}`;

        for (let option of selectPrimeiraFatura.options) {
            if (option.value === valorCalculado) {
                option.selected = true;
                break;
            }
        }
    } else {
        selectPrimeiraFatura.value = '';
    }
}

document.getElementById('parcela_atual').addEventListener('input', calcularPrimeiraFatura);
document.getElementById('total_parcelas').addEventListener('input', calcularPrimeiraFatura);
document.getElementById('data_compra').addEventListener('input', calcularPrimeiraFatura);
document.getElementById('recorrente').addEventListener('change', calcularPrimeiraFatura);

calcularPrimeiraFatura();
</script>

<?php include "footer.php"; ?>