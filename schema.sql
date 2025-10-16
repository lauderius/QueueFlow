-- Pitágoras Filas - Schema + Seed (MySQL InnoDB, utf8mb4)
-- Utilize este ficheiro numa base de dados vazia.
-- Se a base já tem dados, avalie antes de executar TRUNCATE/INSERT.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- Tabela: queues
CREATE TABLE IF NOT EXISTS `queues` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `group_name` VARCHAR(255) NOT NULL,
  `avg_service_sec` INT NOT NULL DEFAULT 180,
  `last_number` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `queues_name_idx` (`name`),
  INDEX `queues_group_idx` (`group_name`),
  INDEX `queues_active_idx` (`active`),
  UNIQUE KEY `queues_unique_name_group` (`name`,`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: tickets
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ext_id` VARCHAR(255) NULL,
  `queue_id` BIGINT UNSIGNED NOT NULL,
  `number` INT NOT NULL,
  `status` ENUM('waiting','serving','done','cancel') NOT NULL,
  `started_at` TIMESTAMP NULL DEFAULT NULL,
  `finished_at` TIMESTAMP NULL DEFAULT NULL,
  `person` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tickets_ext_id_unique` (`ext_id`),
  INDEX `tickets_queue_status_idx` (`queue_id`,`status`),
  INDEX `tickets_queue_number_idx` (`queue_id`,`number`),
  CONSTRAINT `tickets_queue_id_foreign` FOREIGN KEY (`queue_id`) REFERENCES `queues`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: ticket_updates
CREATE TABLE IF NOT EXISTS `ticket_updates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `msg` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `ticket_updates_ticket_created_idx` (`ticket_id`,`created_at`),
  CONSTRAINT `ticket_updates_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: admins
CREATE TABLE IF NOT EXISTS `admins` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user` VARCHAR(255) NOT NULL,
  `hash` VARCHAR(128) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_user_unique` (`user`),
  INDEX `admins_active_idx` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: queue_history
CREATE TABLE IF NOT EXISTS `queue_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue_id` BIGINT UNSIGNED NOT NULL,
  `duration_sec` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `qh_queue_created_idx` (`queue_id`,`created_at`),
  CONSTRAINT `qh_queue_id_foreign` FOREIGN KEY (`queue_id`) REFERENCES `queues`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;

-- Seed inicial das filas (executar numa base vazia)
INSERT IGNORE INTO `queues` (`name`,`group_name`,`avg_service_sec`,`last_number`,`active`,`created_at`,`updated_at`) VALUES
('Pagamentos - Passe','Pagamentos',180,0,1,NOW(),NOW()),
('Pagamentos - Propina','Pagamentos',180,0,1,NOW(),NOW()),
('Pagamentos - Bata','Pagamentos',180,0,1,NOW(),NOW()),
('Pagamentos - Certificados','Pagamentos',180,0,1,NOW(),NOW()),
('Pagamentos - Declarações','Pagamentos',180,0,1,NOW(),NOW()),
('Pagamentos - Faltas','Pagamentos',180,0,1,NOW(),NOW()),
('Pagamentos - Provas de atraso ou exame','Pagamentos',180,0,1,NOW(),NOW()),
('Pagamentos - Uniforme social','Pagamentos',180,0,1,NOW(),NOW()),
('Pagamentos - Beca','Pagamentos',180,0,1,NOW(),NOW()),
('Pagamentos - Fato do estágio','Pagamentos',180,0,1,NOW(),NOW()),
('Pagamentos - Outros','Pagamentos',180,0,1,NOW(),NOW()),
('Secretaria - Administrativa','Secretaria',180,0,1,NOW(),NOW()),
('Secretaria - Pedagógica','Secretaria',180,0,1,NOW(),NOW()),
('Outros - Reclamações','Outros',180,0,1,NOW(),NOW()),
('Outros - Outros serviços','Outros',180,0,1,NOW(),NOW());
