<?php
require_once "../auth.php";
require_once "../conexao.php";
$title = "Controle Financeiro";
include "header.php";

// --- Mensagens de sucesso/erro da sessão ---
$mensagem = $_SESSION['mensagem'] ?? '';
$erro = $_SESSION['erro'] ?? '';
unset($_SESSION['mensagem']);
unset($_SESSION['erro']);

// --- FILTROS ---
$filtroCategoria = $_GET['categoria'] ?? '';
$filtroDataInicio = $_GET['data_inicio'] ?? '';
$filtroDataFim = $_GET['data_fim'] ?? '';

$where = [];
$params = [];

if ($filtroCategoria != '') {
    $where[] = "categoria = :categoria";
    $params[':categoria'] = $filtroCategoria;
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

// --- CONSULTAS ---
$stmtEntradas = $pdo->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = 'entrada' " . $whereSQL);
$stmtEntradas->execute($params);
$totalEntradas = $stmtEntradas->fetchColumn() ?: 0;

$stmtSaidas = $pdo->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = 'saida' " . $whereSQL);
$stmtSaidas->execute($params);
$totalSaidas = $stmtSaidas->fetchColumn() ?: 0;

$saldo = $totalEntradas - $totalSaidas;

// Buscar lançamentos filtrados
$stmt = $pdo->prepare("SELECT * FROM financeiro $whereSQL ORDER BY data DESC");
$stmt->execute($params);
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dados para gráfico mensal
$stmtMes = $pdo->prepare("
    SELECT DATE_FORMAT(data, '%Y-%m') as mes,
           SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as entradas,
           SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as saídas
    FROM financeiro
    $whereSQL
    GROUP BY mes
    ORDER BY mes
");
$stmtMes->execute($params);
$dadosMes = $stmtMes->fetchAll(PDO::FETCH_ASSOC);
$labelsMes = array_column($dadosMes, 'mes');
$valEntradas = array_column($dadosMes, 'entradas');
$valSaidas = array_column($dadosMes, 'saídas');

// Obter categorias existentes
$categorias = $pdo->query("SELECT DISTINCT categoria FROM financeiro WHERE categoria IS NOT NULL AND categoria != ''")->fetchAll(PDO::FETCH_COLUMN);

// Buscar cartões para o formulário de transações
$stmtCartoes = $pdo->query("SELECT id, nome, limite, dia_fechamento, dia_vencimento FROM cartoes_credito ORDER BY nome");
$cartoes = $stmtCartoes->fetchAll(PDO::FETCH_ASSOC);

$previsao_faturas = [];

// Processar formulário de lançamentos (crédito/débito)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo_lancamento']) && $_POST['tipo_lancamento'] == 'financeiro') {
    $descricao = $_POST['descricao'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $valor = str_replace(',', '.', $_POST['valor'] ?? '0');
    $tipo = $_POST['tipo'] ?? '';
    $data = $_POST['data'] ?? '';

    if (empty($descricao) || empty($valor) || !is_numeric($valor) || $valor <= 0 || empty($tipo) || !in_array($tipo, ['entrada', 'saida']) || empty($data)) {
        $_SESSION['erro'] = "Erro: Preencha todos os campos obrigatórios corretamente.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO financeiro (descricao, categoria, valor, tipo, data) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$descricao, $categoria ?: null, $valor, $tipo, $data]);
            $_SESSION['mensagem'] = "Lançamento adicionado com sucesso! Valor: R$ " . number_format($valor, 2, ',', '.') . " ($tipo)";
        } catch (PDOException $e) {
            error_log("Erro ao inserir lançamento: " . $e->getMessage());
            $_SESSION['erro'] = "Erro ao salvar o lançamento. Tente novamente.";
        }
    }
    header('Location: index.php');
    exit;
}

// Processar formulário de transações de cartão
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo_lancamento']) && $_POST['tipo_lancamento'] == 'cartao') {
    // Sanitização e leitura
    $cartao_id     = (int)($_POST['cartao_id'] ?? 0);
    $descricao     = trim($_POST['descricao'] ?? '');
    $data_compra   = $_POST['data_compra'] ?? '';
    $parcelas      = max(1, (int)($_POST['parcelas'] ?? 1));
    $tipo_valor    = $_POST['tipo_valor'] ?? 'total';
    $recorrente    = isset($_POST['recorrente']) ? 1 : 0;
    $parcela_atual = max(1, (int)($_POST['parcela_atual'] ?? 1));
    
    $toFloat = function ($str) {
        $str = trim((string)$str);
        $str = str_replace([' ', 'R$', 'r$', 'R$ ', 'r$ '], '', $str);
        $str = str_replace('.', '', $str);
        $str = str_replace(',', '.', $str);
        return (float)$str;
    };

    $stmt_cartao = $pdo->prepare("SELECT id, dia_fechamento, dia_vencimento FROM cartoes_credito WHERE id = ?");
    $stmt_cartao->execute([$cartao_id]);
    $cartao = $stmt_cartao->fetch(PDO::FETCH_ASSOC);

    if (!$cartao) {
        $_SESSION['erro'] = "Cartão inválido.";
    } elseif (empty($descricao) || empty($data_compra)) {
        $_SESSION['erro'] = "Preencha todos os campos obrigatórios.";
    } else {
        if ($tipo_valor === 'parcela') {
            $valor_parcela = $toFloat($_POST['valor_parcela'] ?? '0');
            $valor_total   = round($valor_parcela * $parcelas, 2);
        } else {
            $valor_total   = $toFloat($_POST['valor_total'] ?? '0');
            $valor_parcela = $parcelas > 0 ? round($valor_total / $parcelas, 2) : 0.00;
        }

        if ($valor_total <= 0 || $valor_parcela <= 0) {
            $_SESSION['erro'] = "Valor inválido.";
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO transacoes_cartao (cartao_id, descricao, valor, data_compra, parcelas, recorrente)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$cartao_id, $descricao, $valor_total, $data_compra, $parcelas, $recorrente]);
                $transacao_id = (int)$pdo->lastInsertId();

                $dia_fechamento = (int)$cartao['dia_fechamento'];
                $dia_vencimento = (int)$cartao['dia_vencimento'];
                $data_compra_obj = new DateTime($data_compra);

                for ($i = 0; $i < $parcelas; $i++) {
                    $numero_parcela = $i + 1;
                    $data_parcela_base = (clone $data_compra_obj)->modify("+$i months");
                    $dia_compra_parcela = (int)$data_parcela_base->format('d');

                    if ($dia_compra_parcela > $dia_fechamento) {
                        $data_parcela_base->modify('+1 month');
                    }

                    $data_vencimento = (clone $data_parcela_base)->setDate(
                        (int)$data_parcela_base->format('Y'),
                        (int)$data_parcela_base->format('m'),
                        $dia_vencimento
                    );

                    $paga = ($numero_parcela < $parcela_atual) ? 1 : 0;

                    $stmt_parcela = $pdo->prepare("
                        INSERT INTO parcelas_cartao (transacao_id, numero_parcela, valor, vencimento, paga)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt_parcela->execute([$transacao_id, $numero_parcela, $valor_parcela, $data_vencimento->format('Y-m-d'), $paga]);

                    $previsao_faturas[] = [
                        'parcela'    => $numero_parcela,
                        'valor'      => number_format($valor_parcela, 2, ',', '.'),
                        'fatura'     => $data_parcela_base->format('m/Y'),
                        'vencimento' => $data_vencimento->format('d/m/Y'),
                        'paga'       => $paga
                    ];
                }

                $pdo->commit();

                $_SESSION['mensagem'] = "Transação de cartão adicionada com sucesso! Valor total: R$ " .
                    number_format($valor_total, 2, ',', '.') .
                    " — Parcela: R$ " . number_format($valor_parcela, 2, ',', '.') .
                    " x $parcelas" . ($parcela_atual > 1 ? " (parcelas 1–" . ($parcela_atual - 1) . " já pagas)" : "");
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Erro ao inserir transação/cartão: " . $e->getMessage());
                $_SESSION['erro'] = "Erro ao salvar a transação do cartão. Verifique os dados e tente novamente.";
            }
        }
    }
    header('Location: index.php');
    exit;
}


// --- LÓGICA DO NOVO MÓDULO DE ORÇAMENTO ---
$mesAtual = date('Y-m');

// 1. Buscar o valor planejado para cada categoria
$stmtPlanejado = $pdo->prepare("
    SELECT categoria, valor_planejado
    FROM planejamento_mensal
    WHERE mes_ano = ?
");
$stmtPlanejado->execute([$mesAtual]);
$planejado = $stmtPlanejado->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Buscar os gastos reais para o mês atual (exclui pagamentos de fatura)
$stmtReal = $pdo->prepare("
    SELECT categoria, SUM(valor) as valor_real
    FROM financeiro
    WHERE tipo = 'saida' AND categoria != 'Pagamento de Fatura' AND DATE_FORMAT(data, '%Y-%m') = ?
    GROUP BY categoria
");
$stmtReal->execute([$mesAtual]);
$real = $stmtReal->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Unir os dados para exibição
$controle_mensal = [];
$todas_categorias = array_unique(array_merge(array_keys($planejado), array_keys($real)));
foreach ($todas_categorias as $cat) {
    $valor_planejado = $planejado[$cat] ?? 0;
    $valor_real = $real[$cat] ?? 0;
    $diferenca = $valor_planejado - $valor_real;
    
    $controle_mensal[] = [
        'categoria' => $cat,
        'planejado' => $valor_planejado,
        'real' => $valor_real,
        'diferenca' => $diferenca
    ];
}


// Calcular faturas pendentes de cartões
$faturasPendentes = [];
$mesAtual = date('Y-m');

$stmtFaturas = $pdo->prepare("
    SELECT
        c.id AS cartao_id,
        c.nome AS cartao_nome,
        MIN(p.vencimento) AS vencimento,
        SUM(p.valor) AS total_fatura
    FROM parcelas_cartao p
    JOIN transacoes_cartao t ON p.transacao_id = t.id
    JOIN cartoes_credito c   ON t.cartao_id = c.id
    WHERE p.paga = 0
      AND DATE_FORMAT(p.vencimento, '%Y-%m') = ?
    GROUP BY c.id, c.nome
    ORDER BY c.nome
");
$stmtFaturas->execute([$mesAtual]);
$faturasPendentes = $stmtFaturas->fetchAll(PDO::FETCH_ASSOC);

// Buscar todas as transações de cartão para a tabela
$stmtTransacoes = $pdo->query("
    SELECT t.*, c.nome AS cartao_nome,
           (SELECT COUNT(*) FROM parcelas_cartao p WHERE p.transacao_id = t.id AND p.paga = 0) as parcelas_pendentes
    FROM transacoes_cartao t
    JOIN cartoes_credito c ON t.cartao_id = c.id
    ORDER BY t.data_compra DESC
");
$transacoesCartao = $stmtTransacoes->fetchAll(PDO::FETCH_ASSOC);

// Processar formulário de orçamento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo_lancamento']) && $_POST['tipo_lancamento'] == 'orcamento') {
    $mes_ano = $_POST['mes_ano'] ?? date('Y-m');
    $planejadoForm = $_POST['planejado'] ?? [];

    try {
        $pdo->beginTransaction();

        foreach ($planejadoForm as $categoria => $valor) {
            $valor = (float)str_replace(',', '.', $valor);

            // Se já existir, faz update; se não, faz insert
            $stmt = $pdo->prepare("
                INSERT INTO planejamento_mensal (mes_ano, categoria, valor_planejado)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE valor_planejado = VALUES(valor_planejado)
            ");
            $stmt->execute([$mes_ano, $categoria, $valor]);
        }

        $pdo->commit();
        $_SESSION['mensagem'] = "Orçamento atualizado com sucesso!";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Erro orçamento: " . $e->getMessage());
        $_SESSION['erro'] = "Erro ao salvar orçamento.";
    }

    header("Location: index.php");
    exit;
}


?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Controle Financeiro</h1>
        <div>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalLancamento">+ Adicionar Lançamento</button>
            <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#modalCartao">+ Adicionar Transação de Cartão</button>
            <a href="cartoes.php" class="btn btn-primary ms-2">Gerenciar Cartões</a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-success"><?= $mensagem ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= $erro ?></div>
    <?php endif; ?>

    <div class="modal fade" id="modalLancamento" tabindex="-1" aria-labelledby="modalLancamentoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLancamentoLabel">Adicionar Lançamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="tipo_lancamento" value="financeiro">
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="descricao" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Categoria</label>
                            <input type="text" name="categoria" class="form-control" list="categoriasList">
                            <datalist id="categoriasList">
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Valor</label>
                            <input type="text" name="valor" class="form-control" placeholder="Ex: 100.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" required>
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
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalCartao" tabindex="-1" aria-labelledby="modalCartaoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCartaoLabel">Adicionar Transação de Cartão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="tipo_lancamento" value="cartao">
                        <div class="mb-3">
                            <label class="form-label">Cartão</label>
                            <select name="cartao_id" class="form-select" required>
                                <option value="">Selecione um cartão</option>
                                <?php foreach ($cartoes as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?> (Limite: R$ <?= number_format($c['limite'], 2, ',', '.') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="descricao" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data da Compra</label>
                            <input type="date" name="data_compra" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Número de Parcelas</label>
                            <input type="number" name="parcelas" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="mb-3">
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
                        <div class="mb-3">
                            <label class="form-label" id="label_valor">Valor Total</label>
                            <input type="text" name="valor_total" id="valor_total" class="form-control" placeholder="Ex: 100.00" required>
                            <input type="text" name="valor_parcela" id="valor_parcela" class="form-control d-none" placeholder="Ex: 50.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Parcela Inicial (se já houver anteriores pagas)</label>
                                <input type="number" name="parcela_inicial" id="parcela_inicial" class="form-control" min="1" value="1" placeholder="Ex: 8">
                            <div class="form-text">Ex.: informe 8 para marcar as parcelas 1 a 7 como pagas.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-check-label">
                                <input type="checkbox" name="recorrente" value="1"> Recorrente
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditarOrcamento" tabindex="-1" aria-labelledby="modalEditarOrcamentoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarOrcamentoLabel">Editar Orçamento de <?= date('m/Y') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                <input type="hidden" name="tipo_lancamento" value="orcamento">
                <input type="hidden" name="mes_ano" value="<?= $mesAtual ?>">
                
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                        <th>Categoria</th>
                        <th>Planejado (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todas_categorias as $cat): ?>
                        <tr>
                            <td><?= htmlspecialchars($cat) ?></td>
                            <td>
                            <input type="number" step="0.01" class="form-control"
                                    name="planejado[<?= htmlspecialchars($cat) ?>]"
                                    value="<?= $planejado[$cat] ?? '' ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
                </div>
                <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
            </div>
        </div>
    </div>


    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Categoria</label>
            <select name="categoria" class="form-select">
                <option value="">Todas</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= $c ?>" <?= $filtroCategoria == $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
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
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
        </div>
    </form>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm text-bg-success">
                <div class="card-body">
                    <h5>Total Entradas</h5>
                    <p class="fs-4">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm text-bg-danger">
                <div class="card-body">
                    <h5>Total Saídas</h5>
                    <p class="fs-4">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm text-bg-primary">
                <div class="card-body">
                    <h5>Saldo Atual</h5>
                    <p class="fs-4">R$ <?= number_format($saldo, 2, ',', '.') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Orçamento do Mês (<?= date('m/Y') ?>)</h5>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarOrcamento">Editar Orçamento</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Categoria</th>
                            <th>Planejado</th>
                            <th>Real</th>
                            <th>Diferença</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($controle_mensal): ?>
                            <?php foreach ($controle_mensal as $item): ?>
                                <?php
                                    $cor_diferenca = '';
                                    if ($item['diferenca'] < 0) {
                                        $cor_diferenca = 'text-danger fw-bold';
                                    } elseif ($item['diferenca'] > 0) {
                                        $cor_diferenca = 'text-success';
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['categoria']) ?></td>
                                    <td>R$ <?= number_format($item['planejado'], 2, ',', '.') ?></td>
                                    <td>R$ <?= number_format($item['real'], 2, ',', '.') ?></td>
                                    <td class="<?= $cor_diferenca ?>">R$ <?= number_format($item['diferenca'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">Nenhum orçamento definido para este mês.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <?php if ($faturasPendentes): ?>
    <h2 class="mb-3">Faturas Pendentes (<?= date('m/Y', strtotime($mesAtual)) ?>) </h2>
    <div class="table-responsive mb-4">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Cartão</th>
                    <th>Total</th>
                    <th>Vencimento</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($faturasPendentes as $fatura): ?>
                    <tr>
                        <td><?= htmlspecialchars($fatura['cartao_nome']) ?></td>
                        <td>R$ <?= number_format($fatura['total_fatura'], 2, ',', '.') ?></td>
                        <td><?= date("d/m/Y", strtotime($fatura['vencimento'])) ?></td>
                        <td>
                            <form method="post" action="pagar_fatura.php" style="display:inline;">
                                <input type="hidden" name="cartao_id" value="<?= $fatura['cartao_id'] ?>">
                                <input type="hidden" name="vencimento_mes" value="<?= date('Y-m', strtotime($fatura['vencimento'])) ?>">
                                <button type="submit" class="btn btn-sm btn-success"
                                    onclick="return confirm('Confirmar pagamento da fatura deste cartão?')">
                                        Pagar Fatura
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<button class="btn btn-outline-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#tabelaTransacoes" aria-expanded="false" aria-controls="tabelaTransacoes">
    Mostrar / Ocultar Transações de Cartão
</button>

<div class="collapse" id="tabelaTransacoes">
    <div class="card card-body">
        <h2 class="mb-3">Transações de Cartão</h2>
        <div class="table-responsive mb-4">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Cartão</th>
                        <th>Descrição</th>
                        <th>Valor Total</th>
                        <th>Parcelas</th>
                        <th>Data Compra</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmtTransacoes = $pdo->query("
                        SELECT t.*, c.nome AS cartao_nome,
                               (SELECT COUNT(*) FROM parcelas_cartao p WHERE p.transacao_id = t.id AND p.paga = 0) as parcelas_pendentes
                        FROM transacoes_cartao t
                        JOIN cartoes_credito c ON t.cartao_id = c.id
                        ORDER BY t.data_compra DESC
                    ");
                    $transacoesCartao = $stmtTransacoes->fetchAll(PDO::FETCH_ASSOC);

                    if ($transacoesCartao):
                        foreach ($transacoesCartao as $t):
                            $status = $t['parcelas_pendentes'] == 0
                                ? "<span class='badge bg-success'>Paga</span>"
                                : "<span class='badge bg-danger'>Pendente</span>";
                    ?>
                        <tr class="<?= $t['parcelas_pendentes'] == 0 ? 'table-success' : 'table-danger' ?>">
                            <td><?= $t['id'] ?></td>
                            <td><?= htmlspecialchars($t['cartao_nome']) ?></td>
                            <td><?= htmlspecialchars($t['descricao']) ?></td>
                            <td>R$ <?= number_format($t['valor'], 2, ',', '.') ?></td>
                            <td><?= $t['parcelas'] ?></td>
                            <td><?= date("d/m/Y", strtotime($t['data_compra'])) ?></td>
                            <td><?= $status ?></td>
                        </tr>
                    <?php
                        endforeach;
                    else:
                    ?>
                        <tr><td colspan="7" class="text-center">Nenhuma transação encontrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

    <?php if ($previsao_faturas): ?>
        <h2 class="mb-3">Previsão de Faturas</h2>
        <div class="table-responsive mb-4">
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

    <div class="row mb-5">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Entradas x Saídas</h5>
                    <canvas id="graficoPizza"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Movimentação Mensal</h5>
                    <canvas id="graficoBarras"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Valor</th>
                    <th>Tipo</th>
                    <th>Data</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($lancamentos): ?>
                    <?php foreach ($lancamentos as $l): ?>
                        <tr>
                            <td><?= $l['id'] ?></td>
                            <td><?= htmlspecialchars($l['descricao']) ?></td>
                            <td><?= htmlspecialchars($l['categoria']) ?></td>
                            <td>R$ <?= number_format($l['valor'], 2, ',', '.') ?></td>
                            <td>
                                <span class="badge <?= $l['tipo'] == 'entrada' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= ucfirst($l['tipo']) ?>
                                </span>
                            </td>
                            <td><?= date("d/m/Y", strtotime($l['data'])) ?></td>
                            <td>
                                <a href="editar.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                <a href="remover.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja remover este lançamento?')">Remover</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center">Nenhum lançamento encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('graficoPizza'), {
    type: 'pie',
    data: {
        labels: ['Entradas', 'Saídas'],
        datasets: [{
            data: [<?= $totalEntradas ?>, <?= $totalSaidas ?>],
            backgroundColor: ['#28a745', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'R$ ' + context.parsed.toFixed(2).replace('.', ',');
                    }
                }
            }
        }
    }
});

new Chart(document.getElementById('graficoBarras'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labelsMes) ?>,
        datasets: [
            { label: 'Entradas', data: <?= json_encode($valEntradas) ?>, backgroundColor: '#28a745' },
            { label: 'Saídas', data: <?= json_encode($valSaidas) ?>, backgroundColor: '#dc3545' }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': R$ ' + context.parsed.y.toFixed(2).replace('.', ',');
                    }
                }
            }
        },
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Valor (R$)' } },
            x: { title: { display: true, text: 'Mês' } }
        }
    }
});
</script>

<script>
(function() {
    const radios = document.querySelectorAll('input[name="tipo_valor"]');
    const total  = document.getElementById('valor_total');
    const parcela= document.getElementById('valor_parcela');
    const label  = document.getElementById('label_valor');

    function syncValorFields() {
        const isTotal = document.querySelector('input[name="tipo_valor"]:checked').value === 'total';
        total.classList.toggle('d-none', !isTotal);
        parcela.classList.toggle('d-none', isTotal);
        label.textContent = isTotal ? 'Valor Total' : 'Valor da Parcela';
        // IMPORTANTÍSSIMO: alternar required para não travar o submit
        total.required   = isTotal;
        parcela.required = !isTotal;
    }

    radios.forEach(r => r.addEventListener('change', syncValorFields));
    // estado inicial:
    syncValorFields();
})();
</script>


<?php include "footer.php"; ?>