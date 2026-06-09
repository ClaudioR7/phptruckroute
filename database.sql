-- ============================================================
--  TruckRoute Pro v5.0 — Schema completo
--  AMM Duarte Transportadora
--  Compatível com MySQL 8.0+ e MariaDB 10.5+
-- ============================================================

CREATE DATABASE IF NOT EXISTS truckroute
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE truckroute;

-- ─── Usuários ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
    id         INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(120)    NOT NULL,
    email      VARCHAR(120)    NOT NULL UNIQUE,
    senha      VARCHAR(255)    NOT NULL,
    perfil     ENUM('admin','motorista') NOT NULL DEFAULT 'motorista',
    cnh        VARCHAR(20),
    telefone   VARCHAR(20),
    ativo      TINYINT(1)      NOT NULL DEFAULT 1,
    criado_em  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_perfil_ativo (perfil, ativo)
) ENGINE=InnoDB;

-- ─── Veículos ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS veiculos (
    id             INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    placa          VARCHAR(10)     NOT NULL UNIQUE,
    modelo         VARCHAR(80)     NOT NULL,
    marca          VARCHAR(60),
    ano            YEAR,
    capacidade_kg  DECIMAL(10,2),
    consumo_km_l   DECIMAL(5,2)    NOT NULL DEFAULT 3.50,
    tanque_litros  DECIMAL(6,2),
    km_atual       DECIMAL(12,2)   NOT NULL DEFAULT 0,
    ativo          TINYINT(1)      NOT NULL DEFAULT 1,
    criado_em      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB;

-- ─── Viagens ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS viagens (
    id                    INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    veiculo_id            INT UNSIGNED  NOT NULL,
    motorista_id          INT UNSIGNED  NOT NULL,
    origem                VARCHAR(255)  NOT NULL,
    destino               VARCHAR(255)  NOT NULL,
    waypoints_json        TEXT,
    distancia_km          DECIMAL(10,2),
    duracao_estimada_s    INT UNSIGNED,
    polyline              MEDIUMTEXT,
    itinerario_json       MEDIUMTEXT,
    km_saida              DECIMAL(12,2),
    litros_saida          DECIMAL(8,2),
    custo_combustivel_est DECIMAL(10,2) DEFAULT 0,
    custo_pedagio_est     DECIMAL(10,2) DEFAULT 0,
    km_chegada            DECIMAL(12,2),
    km_percorrido         DECIMAL(10,2) GENERATED ALWAYS AS (
                              CASE WHEN km_chegada IS NOT NULL AND km_saida IS NOT NULL
                                   THEN ROUND(km_chegada - km_saida, 2) ELSE NULL END
                          ) VIRTUAL,
    litros_abastecidos    DECIMAL(8,2)  DEFAULT 0,
    custo_combustivel     DECIMAL(10,2) DEFAULT 0,
    consumo_real_km_l     DECIMAL(6,2),
    custo_pedagio         DECIMAL(10,2) DEFAULT 0,
    outros_custos         DECIMAL(10,2) DEFAULT 0,
    custo_total           DECIMAL(10,2) GENERATED ALWAYS AS (
                              ROUND(COALESCE(custo_combustivel,0) + COALESCE(custo_pedagio,0) + COALESCE(outros_custos,0), 2)
                          ) VIRTUAL,
    valor_frete           DECIMAL(10,2) DEFAULT 0,
    rentabilidade         DECIMAL(10,2) GENERATED ALWAYS AS (
                              ROUND(COALESCE(valor_frete,0) - (COALESCE(custo_combustivel,0) + COALESCE(custo_pedagio,0) + COALESCE(outros_custos,0)), 2)
                          ) VIRTUAL,
    data_saida            DATETIME,
    data_chegada          DATETIME,
    status                ENUM('planejada','em_andamento','concluida','cancelada') NOT NULL DEFAULT 'planejada',
    observacoes           TEXT,
    criado_em             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_motorista_status (motorista_id, status),
    INDEX idx_veiculo (veiculo_id),
    INDEX idx_data_saida (data_saida),
    FOREIGN KEY (veiculo_id)   REFERENCES veiculos(id) ON UPDATE CASCADE,
    FOREIGN KEY (motorista_id) REFERENCES usuarios(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ─── Abastecimentos ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS abastecimentos (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    viagem_id     INT UNSIGNED,
    veiculo_id    INT UNSIGNED  NOT NULL,
    motorista_id  INT UNSIGNED  NOT NULL,
    km_no_momento DECIMAL(12,2) NOT NULL,
    litros        DECIMAL(8,2)  NOT NULL,
    preco_litro   DECIMAL(6,3)  NOT NULL,
    valor_total   DECIMAL(10,2) GENERATED ALWAYS AS (ROUND(litros * preco_litro, 2)) VIRTUAL,
    posto         VARCHAR(120),
    data_hora     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacoes   TEXT,
    INDEX idx_veiculo_data (veiculo_id, data_hora),
    INDEX idx_viagem (viagem_id),
    FOREIGN KEY (viagem_id)    REFERENCES viagens(id)    ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (veiculo_id)   REFERENCES veiculos(id)   ON UPDATE CASCADE,
    FOREIGN KEY (motorista_id) REFERENCES usuarios(id)   ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ─── Posições GPS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS posicoes (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    viagem_id     INT UNSIGNED    NOT NULL,
    veiculo_id    INT UNSIGNED    NOT NULL,
    lat           DECIMAL(10,7)   NOT NULL,
    lng           DECIMAL(10,7)   NOT NULL,
    velocidade    DECIMAL(6,2),
    direcao       SMALLINT UNSIGNED,
    registrado_em DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_viagem_tempo (viagem_id, registrado_em),
    FOREIGN KEY (viagem_id)  REFERENCES viagens(id)  ON DELETE CASCADE,
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ─── KPIs Diários ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS kpis_diarios (
    id                 INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    data               DATE          NOT NULL UNIQUE,
    total_viagens      INT UNSIGNED  DEFAULT 0,
    total_km           DECIMAL(12,2) DEFAULT 0,
    total_litros       DECIMAL(10,2) DEFAULT 0,
    total_custo        DECIMAL(12,2) DEFAULT 0,
    total_receita      DECIMAL(12,2) DEFAULT 0,
    media_consumo_km_l DECIMAL(6,2)  DEFAULT 0,
    atualizado_em      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_data (data)
) ENGINE=InnoDB;

-- ─── Dados Iniciais ───────────────────────────────────────────
-- Senha padrão: password
INSERT IGNORE INTO usuarios (nome, email, senha, perfil) VALUES
    ('Administrador', 'admin@ammduarte.com.br',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT IGNORE INTO usuarios (nome, email, senha, perfil, cnh, telefone) VALUES
    ('João Motorista', 'motorista@ammduarte.com.br',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
     'motorista', '12345678901', '(21) 99999-0001');

INSERT IGNORE INTO veiculos (placa, modelo, marca, ano, consumo_km_l, tanque_litros, km_atual) VALUES
    ('ABC-1234', 'Axor 2544', 'Mercedes-Benz', 2020, 3.5, 600, 125000),
    ('DEF-5678', 'FH 540',    'Volvo',         2021, 3.8, 700,  87000);

-- ─── Migração v4→v5 (só se já tiver banco criado) ─────────────
-- Execute este bloco SE estiver atualizando um banco existente:
-- ALTER TABLE viagens MODIFY COLUMN veiculo_id INT UNSIGNED NOT NULL;
-- ALTER TABLE viagens MODIFY COLUMN motorista_id INT UNSIGNED NOT NULL;
-- ALTER TABLE viagens ADD COLUMN IF NOT EXISTS itinerario_json MEDIUMTEXT AFTER polyline;
