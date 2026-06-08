-- Sample data for local development.
-- Login: admin@example.com / admin123  (DEV only)

INSERT INTO users (name, email, password_hash, role, created_at) VALUES
('Site Admin', 'admin@example.com', '$2y$10$8EIYy59pV4uLFPt1UVburufNXYnMe6aQ4H1MtshJOjUEjj1c8Pm5q', 'admin', NOW());

INSERT INTO clients (name, email, phone, notes, created_at) VALUES
('Alice Johnson', 'alice.johnson@example.com', '+1 202 555 0143', 'Prefers morning appointments.', NOW()),
('Bob Smith',     'bob.smith@example.com',     '+1 202 555 0177', 'Allergic to certain products.', NOW()),
('Carla Mendes',  'carla.mendes@example.com',  '+1 202 555 0199', 'Returning customer.',          NOW());

INSERT INTO services (name, description, price, duration_minutes, created_at) VALUES
('Haircut',          'Standard wash, cut and style.',        45.00, 45, NOW()),
('Beard Trim',       'Shape and trim with hot towel finish.', 25.00, 30, NOW()),
('Full Color',       'Single-process hair coloring.',        120.00, 90, NOW()),
('Deep Conditioning','Restorative treatment for damaged hair.',35.00, 30, NOW());

INSERT INTO appointments (client_id, service_id, user_id, scheduled_to, status, notes, created_at) VALUES
(1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 10 HOUR, 'confirmed', 'First visit.',        NOW()),
(2, 2, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 14 HOUR, 'pending',   'Asked for a window seat.', NOW()),
(3, 3, 1, CURDATE() + INTERVAL 16 HOUR,                            'pending',   'Bring color swatches.', NOW());
