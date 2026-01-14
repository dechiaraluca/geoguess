CREATE TABLE IF NOT EXISTS `countries` (
    `id_country` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(2) NOT NULL UNIQUE,
    `osm_id` BIGINT NOT NULL UNIQUE,
    `search_area` BIGINT NOT NULL, 
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(`id_country`),
    INDEX idx_osm_id (`osm_id`),
    INDEX idx_search_area (`search_area`)
);

CREATE TABLE IF NOT EXISTS `cities` (
    `id_city` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `osm_id` BIGINT NOT NULL,
    `id_country` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(`id_city`),
    FOREIGN KEY(`id_country`) REFERENCES `countries`(`id_country`) 
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_country (`id_country`),
    INDEX idx_osm_id (`osm_id`)
);

CREATE TABLE IF NOT EXISTS `images` (
    `id_image` INT NOT NULL AUTO_INCREMENT,
    `url` VARCHAR(500) NOT NULL,
    `title` VARCHAR(255),
    `id_city` INT NOT NULL,
    `is_valid` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(`id_image`),
    FOREIGN KEY(`id_city`) REFERENCES `cities`(`id_city`) 
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_city (`id_city`),
    INDEX idx_valid (`is_valid`)
);

CREATE TABLE IF NOT EXISTS `players` (
    `id_player` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(`id_player`),
    INDEX idx_name (`name`)
);

CREATE TABLE IF NOT EXISTS `game_sessions` (
    `id_session` INT NOT NULL AUTO_INCREMENT,
    `id_player` INT NOT NULL,
    `lives_remaining` INT DEFAULT 5,
    `current_score` INT DEFAULT 0,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ended_at` TIMESTAMP NULL,
    `status` ENUM('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress',
    PRIMARY KEY(`id_session`),
    FOREIGN KEY(`id_player`) REFERENCES `players`(`id_player`) 
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_player (`id_player`),
    INDEX idx_status (`status`)
);

CREATE TABLE IF NOT EXISTS `answers` (
    `id_answer` INT NOT NULL AUTO_INCREMENT,
    `id_session` INT NOT NULL,
    `id_city` INT NOT NULL,
    `guessed_country` VARCHAR(100), 
    `is_correct` BOOLEAN NOT NULL,
    `time_taken` INT,  
    `answered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(`id_answer`),
    FOREIGN KEY(`id_session`) REFERENCES `game_sessions`(`id_session`) 
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY(`id_city`) REFERENCES `cities`(`id_city`) 
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_session (`id_session`),
    INDEX idx_city (`id_city`)
);

CREATE TABLE IF NOT EXISTS `scores` (
    `id_score` INT NOT NULL AUTO_INCREMENT,
    `id_player` INT NOT NULL,
    `id_session` INT NOT NULL,
    `final_score` INT NOT NULL,
    `total_questions` INT NOT NULL,
    `correct_answers` INT NOT NULL,
    `lives_used` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(`id_score`),
    FOREIGN KEY(`id_player`) REFERENCES `players`(`id_player`) 
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY(`id_session`) REFERENCES `game_sessions`(`id_session`) 
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_player (`id_player`),
    INDEX idx_score (`final_score` DESC)
);