-- imageset.sql - base para importação MySQL/MariaDB

DROP DATABASE IF EXISTS `imageset`;
CREATE DATABASE `imageset` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `imageset`;

-- --------------------------------------------------------
-- ESTRUTURA DE TABELAS
-- --------------------------------------------------------

CREATE TABLE `cartoes_credito` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `limite` DECIMAL(10,2) NOT NULL,
  `dia_fechamento` INT NOT NULL,
  `dia_vencimento` INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cartoes_credito` (`id`, `nome`, `limite`, `dia_fechamento`, `dia_vencimento`) VALUES
(2, 'VISA', 29780.00, 25, 1),
(3, 'VUON Card', 2400.00, 25, 5);

CREATE TABLE `financeiro` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `descricao` VARCHAR(255) NOT NULL,
  `categoria` VARCHAR(100) DEFAULT NULL,
  `valor` DECIMAL(10,2) NOT NULL,
  `tipo` ENUM('entrada', 'saida') NOT NULL,
  `data` DATE NOT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `imagens` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome_arquivo` VARCHAR(255) NOT NULL,
  `caminho` VARCHAR(255) NOT NULL,
  `nome_colecao` VARCHAR(255) NOT NULL,
  `artista` VARCHAR(255) NOT NULL,
  `data_colecao` DATE NOT NULL,
  `empresa` VARCHAR(100) NOT NULL,
  `tags` VARCHAR(500) NOT NULL,
  `score` FLOAT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Criação da tabela 'transacoes_cartao' primeiro
CREATE TABLE `transacoes_cartao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cartao_id` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_compra` date NOT NULL,
  `parcelas` int(11) NOT NULL,
  `recorrente` tinyint(1) DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Agora a tabela 'parcelas_cartao'
CREATE TABLE `parcelas_cartao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transacao_id` int(11) NOT NULL,
  `numero_parcela` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `vencimento` date NOT NULL,
  `paga` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `transacao_id` (`transacao_id`),
  CONSTRAINT `parcelas_cartao_ibfk_1` FOREIGN KEY (`transacao_id`) REFERENCES `transacoes_cartao`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `planejamento_mensal` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `mes_ano` VARCHAR(7) NOT NULL,
  `categoria` VARCHAR(100) NOT NULL,
  `valor_planejado` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_planejamento` (`mes_ano`, `categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `planejamento_mensal` (`id`, `mes_ano`, `categoria`, `valor_planejado`) VALUES
(1, '2025-08', 'alimentação', 300.00),
(2, '2025-08', 'outros', 100.00),
(3, '2025-08', 'Saldo anterior', 0.00),
(4, '2025-08', 'saúde', 100.00),
(5, '2025-08', 'supermercado', 300.00);

-- --------------------------------------------------------
-- DADOS EXEMPLO INCLUÍDOS (caso precise de mais dados, informe)
-- --------------------------------------------------------

COMMIT;
