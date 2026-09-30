-- =====================================================
--  Base de données : sonatrach_banque
--  Projet : SONATRACH - Direction Finance Béjaïa
--  Import : phpMyAdmin > Importer, ou  mysql -u root < schema.sql
-- =====================================================

CREATE DATABASE IF NOT EXISTS sonatrach_banque
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sonatrach_banque;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS brouillards, movements, operations, ventilations, commissions, beneficiaires, utilisateurs;
SET FOREIGN_KEY_CHECKS = 1;

-- Utilisateurs (admin / agent / responsable) — mots de passe hachés (bcrypt)
CREATE TABLE utilisateurs (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(50)  NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  nom_complet  VARCHAR(100) NOT NULL,
  role         ENUM('admin','agent','responsable') NOT NULL,
  email        VARCHAR(100) NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Bénéficiaires
CREATE TABLE beneficiaires (
  id_beneficiaire INT AUTO_INCREMENT PRIMARY KEY,
  nom             VARCHAR(100) NOT NULL,
  prenom          VARCHAR(100) NOT NULL,
  adresse         TEXT NULL,
  numero_compte   VARCHAR(50)  NOT NULL,
  INDEX idx_nom (nom)
) ENGINE=InnoDB;

-- Commissions
CREATE TABLE commissions (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  date_commission DATE NOT NULL,
  montant         DECIMAL(15,2) NOT NULL,
  devise          ENUM('DZD','EUR','USD') NOT NULL DEFAULT 'DZD',
  type            ENUM('Contant','Commission') NOT NULL,
  actif           TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Ventilations (imputations comptables)
CREATE TABLE ventilations (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(20)  NOT NULL UNIQUE,
  libelle    VARCHAR(150) NOT NULL,
  famille    ENUM('Exploitation','Investissement') NOT NULL DEFAULT 'Exploitation',
  etat       TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Mouvements (saisis par l'agent) : recettes et dépenses
CREATE TABLE movements (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  id_agent        INT NOT NULL,
  date_versement  DATE NOT NULL,
  id_beneficiaire INT NULL,
  nature          VARCHAR(150) NOT NULL,
  reference       VARCHAR(100) NOT NULL,
  type            ENUM('Recette','Depense') NOT NULL,
  mode_paiement   ENUM('Virement','Espèces','Chèque') NOT NULL DEFAULT 'Virement',
  montant         DECIMAL(15,2) NOT NULL,
  commission      DECIMAL(15,2) NOT NULL DEFAULT 0,
  id_commission   INT NULL,
  id_ventilation  INT NULL,
  etat            ENUM('En cours','Validé') NOT NULL DEFAULT 'En cours',
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_date (date_versement),
  CONSTRAINT fk_mv_agent  FOREIGN KEY (id_agent)        REFERENCES utilisateurs(id)               ON DELETE CASCADE,
  CONSTRAINT fk_mv_benef  FOREIGN KEY (id_beneficiaire) REFERENCES beneficiaires(id_beneficiaire) ON DELETE SET NULL,
  CONSTRAINT fk_mv_commis FOREIGN KEY (id_commission)   REFERENCES commissions(id)                ON DELETE SET NULL,
  CONSTRAINT fk_mv_ventil FOREIGN KEY (id_ventilation)  REFERENCES ventilations(id)               ON DELETE SET NULL
) ENGINE=InnoDB;

-- Brouillards de banque (générés puis approuvés par le responsable)
CREATE TABLE brouillards (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  id_responsable INT NOT NULL,
  date_debut     DATE NOT NULL,
  date_fin       DATE NOT NULL,
  total_recette  DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_depense  DECIMAL(15,2) NOT NULL DEFAULT 0,
  solde_final    DECIMAL(15,2) NOT NULL DEFAULT 0,
  etat           ENUM('En attente','Approuve','Rejete') NOT NULL DEFAULT 'En attente',
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_br_resp FOREIGN KEY (id_responsable) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------- Données de démonstration ----------------
-- Comptes : admin/admin123 · agent1/agent123 · responsable/resp123
INSERT INTO utilisateurs (username, password, nom_complet, role, email) VALUES
 ('admin',       '$2y$12$yFIIzEqMUuIzO7h.jz0PTeTEbhEIwWSBSnYgc.hNcuzlN.KOFyKDq', 'Administrateur',       'admin',       'admin@sonatrach.dz'),
 ('agent1',      '$2y$12$uo6BuOwashxV5ZGthKn1H.07JMKURgAf13wSj1FJc83ycpNqCawl.', 'Agent Finance',        'agent',       'agent1@sonatrach.dz'),
 ('responsable', '$2y$12$GGOa2TZ0815bfLnGnpNEDuIYIE4xmMGRcdejfvsp2KVHWUfQP/ZSG',  'Responsable Finance',  'responsable', 'resp@sonatrach.dz');

INSERT INTO beneficiaires (nom, prenom, adresse, numero_compte) VALUES
 ('BENALI', 'Ahmed', '12 Rue des Palmiers, Béjaïa', '001-2345678901-12'),
 ('AMRANI', 'Karim', 'Cité 200 logements, Béjaïa',  '001-9876543210-45');

INSERT INTO commissions (date_commission, montant, devise, type, actif) VALUES
 (CURDATE(),  500.00, 'DZD', 'Commission', 1),
 (CURDATE(), 1000.00, 'DZD', 'Contant',    1);

INSERT INTO ventilations (code, libelle, famille, etat) VALUES
 ('V001', 'Frais de mission',      'Exploitation',   1),
 ('V002', 'Achat de matériel',     'Investissement', 1),
 ('V003', 'Fournitures de bureau', 'Exploitation',   1);

INSERT INTO movements (id_agent, date_versement, id_beneficiaire, nature, reference, type, mode_paiement, montant, commission, id_commission, id_ventilation) VALUES
 (2, CURDATE(),                          1, 'Remboursement frais de mission', 'REF-001', 'Depense', 'Virement', 45000.00,  500.00, 1, 1),
 (2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 2, 'Achat ordinateurs portables',    'REF-002', 'Depense', 'Chèque',  320000.00, 1000.00, 2, 2),
 (2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 1, 'Encaissement prestation',        'REF-003', 'Recette', 'Virement', 500000.00,   0.00, NULL, NULL);
