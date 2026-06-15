-- ============================================================
--  petals_db  |  Clean Schema + Seed Data
--  Compatible: MySQL 8.0+
-- ============================================================

CREATE DATABASE IF NOT EXISTS petals_db;
USE petals_db;

-- ------------------------------------------------------------
--  TABLE: admin
-- ------------------------------------------------------------
CREATE TABLE `admin` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(50)  NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `email`      VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `admin` (`id`, `username`, `password`, `email`, `created_at`) VALUES
(1, 'admin', '$2y$10$yflneXq/Jm1gveefRWVcr./8PCCE/ejK08hzjIoHNjpBKRjKGCprS', 'admin@petals.com', '2026-06-14 12:20:04');

-- ------------------------------------------------------------
--  TABLE: customers
-- ------------------------------------------------------------
CREATE TABLE `customers` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(100) DEFAULT NULL,
  `phone`      VARCHAR(20)  DEFAULT NULL,
  `address`    TEXT,
  `created_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `address`, `created_at`) VALUES
(1, 'Sofia Reyes',      'sofia@email.com',    '+63 912 345 6789', '123 Mango St, Cebu City',       '2026-06-14 12:20:04'),
(2, 'Bianca Cruz',      'bianca@email.com',   '+63 917 234 5678', '456 Rose Ave, Manila',           '2026-06-14 12:20:04'),
(3, 'Isabelle Santos',  'isabelle@email.com', '+63 922 876 5432', '789 Lily Rd, Davao',             '2026-06-14 12:20:04'),
(4, 'Marika Gomez',     'marika@email.com',   '+63 918 111 2222', '321 Petal Ln, Quezon City',      '2026-06-14 12:20:04');

-- ------------------------------------------------------------
--  TABLE: flowers
-- ------------------------------------------------------------
CREATE TABLE `flowers` (
  `id`          INT            NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)   NOT NULL,
  `category`    VARCHAR(50)    DEFAULT NULL,
  `price`       DECIMAL(10,2)  NOT NULL,
  `stock`       INT            DEFAULT '0',
  `description` TEXT,
  `image_url`   VARCHAR(255)   DEFAULT NULL,
  `created_at`  TIMESTAMP      NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `flowers` (`id`, `name`, `category`, `price`, `stock`, `description`, `image_url`, `created_at`) VALUES
(1, 'Red Rose',    'Rose',      4.99,  120, 'Classic symbol of love.',    'uploads/1781444977_Luxurytwodozenredrosesabove.webp',                   '2026-06-14 12:20:04'),
(2, 'Pink Tulip',  'Tulip',     3.50,   85, 'Delicate spring beauty.',    'uploads/1781444908_tulips-pink-lilac.webp',                              '2026-06-14 12:20:04'),
(3, 'White Lily',  'Lily',      5.99,   60, 'Pure and elegant.',          'uploads/1781444844_White_Lilies_Bouquet_8f6e9827-d3e1-418e-9896-05928b60718c.webp', '2026-06-14 12:20:04'),
(4, 'Sunflower',   'Sunflower', 2.99,  200, 'Bright and cheerful.',       'uploads/1781444614_296582392_623541682280003_5695555043883292808_n.jpg', '2026-06-14 12:20:04'),
(5, 'Lavender',    'Lavender',  3.75,   95, 'Calming purple blooms.',     'uploads/1781444711_FullSizeRender8.webp',                                '2026-06-14 12:20:04'),
(6, 'Peony',       'Peony',     7.50,   40, 'Lush romantic petals.',      'uploads/1781444770_71PjoC6k1dL._AC_SL1500_.jpg',                        '2026-06-14 12:20:04');

-- ------------------------------------------------------------
--  TABLE: orders
-- ------------------------------------------------------------
CREATE TABLE `orders` (
  `id`          INT           NOT NULL AUTO_INCREMENT,
  `customer_id` INT           DEFAULT NULL,
  `flower_id`   INT           DEFAULT NULL,
  `quantity`    INT           DEFAULT '1',
  `total_price` DECIMAL(10,2) DEFAULT NULL,
  `status`      ENUM('pending','processing','delivered','cancelled') DEFAULT 'pending',
  `order_date`  TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `flower_id`   (`flower_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`flower_id`)   REFERENCES `flowers`   (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `orders` (`id`, `customer_id`, `flower_id`, `quantity`, `total_price`, `status`, `order_date`) VALUES
(1, 1, 1, 3,  14.97, 'delivered',  '2025-01-09 16:00:00'),
(2, 2, 2, 5,  17.50, 'delivered',  '2025-02-13 16:00:00'),
(3, 3, 3, 2,  11.98, 'delivered',  '2025-03-07 16:00:00'),
(4, 4, 6, 1,   7.50, 'delivered',  '2025-04-20 16:00:00'),
(5, 1, 4, 4,  19.96, 'delivered',  '2025-05-04 16:00:00'),
(6, 2, 1, 2,   9.98, 'delivered',  '2025-05-31 16:00:00'),
(7, 3, 5, 3,  11.25, 'processing', '2025-06-09 16:00:00'),
(8, 4, 2, 1,   5.99, 'pending',    '2025-06-11 16:00:00');

-- ============================================================
--  Done!
-- ============================================================