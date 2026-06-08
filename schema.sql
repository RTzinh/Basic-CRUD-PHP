-- Schema for the Service Scheduler app.
-- Mirrors exactly the tables/columns queried by public/index.php.

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(50)  NOT NULL DEFAULT 'admin',
    created_at    DATETIME     NOT NULL
);

CREATE TABLE IF NOT EXISTS clients (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255),
    phone      VARCHAR(50),
    notes      TEXT,
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS services (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(255)   NOT NULL,
    description      TEXT,
    price            DECIMAL(10, 2) NOT NULL DEFAULT 0,
    duration_minutes INT            NOT NULL DEFAULT 30,
    created_at       DATETIME       NOT NULL
);

CREATE TABLE IF NOT EXISTS appointments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    client_id    INT NOT NULL,
    service_id   INT NOT NULL,
    user_id      INT,
    scheduled_to DATETIME    NOT NULL,
    status       VARCHAR(20) NOT NULL DEFAULT 'pending',
    notes        TEXT,
    created_at   DATETIME    NOT NULL,
    FOREIGN KEY (client_id)  REFERENCES clients(id)  ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
);
