-- phpMyAdmin SQL Dump

-- version 5.2.1

-- https://www.phpmyadmin.net/

--

-- Host: 127.0.0.1

-- Tempo de geração: 04/09/2025 às 23:35

-- Versão do servidor: 10.4.32-MariaDB

-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

START TRANSACTION;

SET time_zone = "+00:00";

--

-- Banco de dados: 'imageset'
CREATE DATABASE IF NOT EXISTS imageset
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_general_ci;

-- Estrutura para tabela `cartoes_credito`
USE imageset;

CREATE TABLE `cartoes_credito` (
`id` int(11) NOT NULL,
`nome` varchar(100) NOT NULL,
`limite` decimal(10,2) NOT NULL,
`dia_fechamento` int(11) NOT NULL,
`dia_vencimento` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Despejando dados para a tabela `cartoes_credito`

INSERT INTO `cartoes_credito` (`id`, `nome`, `limite`, `dia_fechamento`, `dia_vencimento`) VALUES
(2, 'VISA', 29780.00, 25, 1),
(3, 'VUON Card', 2400.00, 25, 5);

-- --------------------------------------------------------

-- Estrutura para tabela `categorias_orcamento`

CREATE TABLE `categorias_orcamento` (
`id` int(11) NOT NULL,
`nome` varchar(100) NOT NULL,
`limite_ideal` decimal(10,2) NOT NULL DEFAULT 0.00,
`categoria_pai` varchar(100) DEFAULT NULL,
`tipo` enum('receita','despesa') NOT NULL DEFAULT 'despesa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Despejando dados para a tabela `categorias_orcamento`

INSERT INTO `categorias_orcamento` (`id`, `nome`, `limite_ideal`, `categoria_pai`, `tipo`) VALUES
(6, 'Alimentação', 350.00, NULL, 'despesa'),
(7, 'Habitação/Fixa', 0.00, NULL, 'despesa'),
(8, 'Lucinda', 200.00, 'Habitação/Fixa', 'despesa'),
(9, 'Pix Carla', 326.00, 'Habitação/Fixa', 'despesa'),
(10, 'IPTU casa Caiobá', 48.00, 'Habitação/Fixa', 'despesa'),
(11, 'Habitação/Variável', 0.00, NULL, 'despesa'),
(12, 'Conta de Luz', 110.00, 'Habitação/Variável', 'despesa'),
(13, 'Conta de Água', 110.00, 'Habitação/Variável', 'despesa'),
(14, 'Celular e Internet', 203.00, 'Habitação/Variável', 'despesa'),
(15, 'Saúde', 100.00, NULL, 'despesa'),
(16, 'Combustível', 600.00, NULL, 'despesa'),
(17, 'Barbeiro/Salão', 235.00, NULL, 'despesa'),
(18, 'Vestuário', 100.00, NULL, 'despesa'),
(19, 'Outros/Besteiras', 150.00, NULL, 'despesa'),
(20, 'Educação', 1250.00, NULL, 'despesa'),
(21, 'Lazer/Lanches/Passeios', 200.00, NULL, 'despesa'),
(22, 'Aluguel', 0.00, NULL, 'receita'),
(23, 'Casa Caiobá', 700.00, 'Aluguel', 'receita'),
(24, 'Aluguel Zamilda', 130.00, 'Aluguel', 'receita'),
(25, 'Salário', 0.00, NULL, 'receita'),
(26, 'Folha normal', 5393.95, 'Salário', 'receita'),
(27, 'Auxílio Alimentação', 2200.00, 'Salário', 'receita'),
(28, 'Auxílio educação', 558.78, 'Salário', 'receita'),
(29, 'Auxílio transporte', 500.00, 'Salário', 'receita');

-- --------------------------------------------------------

-- Estrutura para tabela `financeiro`

CREATE TABLE `financeiro` (
`id` int(11) NOT NULL,
`descricao` varchar(255) NOT NULL,
`categoria` varchar(100) DEFAULT NULL,
`valor` decimal(10,2) NOT NULL,
`tipo` enum('entrada','saida') NOT NULL,
`data` date NOT NULL,
`criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- Estrutura para tabela `imagens`

CREATE TABLE `imagens` (
`id` int(11) NOT NULL,
`nome_arquivo` varchar(255) NOT NULL,
`caminho` varchar(255) NOT NULL,
`nome_colecao` varchar(255) NOT NULL,
`artista` varchar(255) NOT NULL,
`data_colecao` date NOT NULL,
`empresa` varchar(100) NOT NULL,
`tags` varchar(500) NOT NULL,
`score` float NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- Estrutura para tabela `parcelas_cartao`

CREATE TABLE `parcelas_cartao` (
`id` int(11) NOT NULL,
`transacao_id` int(11) NOT NULL,
`numero_parcela` int(11) NOT NULL,
`valor` decimal(10,2) NOT NULL,
`vencimento` date NOT NULL,
`paga` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Despejando dados para a tabela `parcelas_cartao`

INSERT INTO `parcelas_cartao` (`id`, `transacao_id`, `numero_parcela`, `valor`, `vencimento`, `paga`) VALUES
(25, 7, 1, 2250.00, '2025-09-01', 0),
(26, 7, 2, 2250.00, '2025-10-01', 0),
(27, 7, 3, 2250.00, '2025-11-01', 0),
(28, 7, 4, 2250.00, '2025-12-01', 0);

-- --------------------------------------------------------

-- Estrutura para tabela `planejamento_mensal`

CREATE TABLE `planejamento_mensal` (
`id` int(11) NOT NULL,
`mes_ano` varchar(7) NOT NULL,
`categoria` varchar(100) NOT NULL,
`valor_planejado` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Despejando dados para a tabela `planejamento_mensal`

INSERT INTO `planejamento_mensal` (`id`, `mes_ano`, `categoria`, `valor_planejado`) VALUES
(78, '2025-09', 'Alimentação', 320.00),
(79, '2025-09', 'Aluguel', 0.00),
(80, '2025-09', 'Aluguel Zamilda', 130.00),
(81, '2025-09', 'Casa Caiobá', 700.00),
(82, '2025-09', 'Barbeiro/Salão', 235.00),
(83, '2025-09', 'Combustível', 600.00),
(84, '2025-09', 'Educação', 1250.00),
(85, '2025-09', 'Habitação/Fixa', 0.00),
(86, '2025-09', 'IPTU casa Caiobá', 48.00),
(87, '2025-09', 'Lucinda', 200.00),
(88, '2025-09', 'Pix Carla', 326.00),
(89, '2025-09', 'Habitação/Variável', 0.00),
(90, '2025-09', 'Celular e Internet', 203.00),
(91, '2025-09', 'Conta de Água', 110.00),
(92, '2025-09', 'Conta de Luz', 110.00),
(93, '2025-09', 'Lazer/Lanches/Passeios', 200.00),
(94, '2025-09', 'Outros/Besteiras', 150.00),
(95, '2025-09', 'Salário', 0.00),
(96, '2025-09', 'Auxílio Alimentação', 2200.00),
(97, '2025-09', 'Auxílio educação', 558.78),
(98, '2025-09', 'Auxílio transporte', 500.00),
(99, '2025-09', 'Folha normal', 5393.95),
(100, '2025-09', 'Saúde', 100.00),
(101, '2025-09', 'Vestuário', 100.00);

-- --------------------------------------------------------

-- Estrutura para tabela `transacoes_cartao`

CREATE TABLE `transacoes_cartao` (
`id` int(11) NOT NULL,
`cartao_id` int(11) NOT NULL,
`descricao` varchar(255) NOT NULL,
`valor` decimal(10,2) NOT NULL,
`data_compra` date NOT NULL,
`parcelas` int(11) NOT NULL,
`recorrente` tinyint(1) DEFAULT 0,
`criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Despejando dados para a tabela `transacoes_cartao`

INSERT INTO `transacoes_cartao` (`id`, `cartao_id`, `descricao`, `valor`, `data_compra`, `parcelas`, `recorrente`, `criado_em`) VALUES
(7, 2, 'teste', 9000.00, '2025-09-04', 4, 0, '2025-09-04 21:28:01');

-- Índices para tabelas despejadas

ALTER TABLE `cartoes_credito`
ADD PRIMARY KEY (`id`);

ALTER TABLE `categorias_orcamento`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `nome` (`nome`);

ALTER TABLE `financeiro`
ADD PRIMARY KEY (`id`);

ALTER TABLE `imagens`
ADD PRIMARY KEY (`id`);

ALTER TABLE `parcelas_cartao`
ADD PRIMARY KEY (`id`),
ADD KEY `transacao_id` (`transacao_id`);

ALTER TABLE `planejamento_mensal`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `uk_planejamento` (`mes_ano`,`categoria`);

ALTER TABLE `transacoes_cartao`
ADD PRIMARY KEY (`id`);

-- AUTO_INCREMENT para tabelas despejadas

ALTER TABLE `cartoes_credito`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `categorias_orcamento`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

ALTER TABLE `financeiro`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `imagens`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `parcelas_cartao`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

ALTER TABLE `planejamento_mensal`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

ALTER TABLE `transacoes_cartao`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

-- Restrições para tabelas despejadas

ALTER TABLE `parcelas_cartao`
ADD CONSTRAINT `parcelas_cartao_ibfk_1` FOREIGN KEY (`transacao_id`) REFERENCES `transacoes_cartao` (`id`) ON DELETE CASCADE;

COMMIT;