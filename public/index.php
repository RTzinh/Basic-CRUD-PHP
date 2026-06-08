<?php
declare(strict_types=1);

require __DIR__ . '/../config/db.php';

session_start();

/**
 * Simple helper to require authentication.
 */
function requireLogin(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: ?page=login');
        exit;
    }
}

/**
 * Hash the password using bcrypt.
 */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Attempt to authenticate.
 */
function attemptLogin(PDO $pdo, string $email, string $password): bool
{
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
        return true;
    }

    return false;
}

/**
 * Create a new user (basic registration).
 */
function register(PDO $pdo, string $name, string $email, string $password): bool
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        return false;
    }

    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (:name, :email, :password_hash, :role, NOW())');
    return $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => hashPassword($password),
        'role' => 'admin',
    ]);
}

/**
 * Basic sanitize helper to avoid XSS in this demo skeleton.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$page = $_GET['page'] ?? 'login';
$error = null;
$success = null;

// Routing
switch ($page) {
    case 'logout':
        session_destroy();
        header('Location: ?page=login');
        exit;

    case 'register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (register($pdo, $_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '')) {
                $success = 'User registered. Please log in.';
            } else {
                $error = 'E-mail already registered.';
            }
        }
        renderHeader('Register');
        renderAuthForm('register', $error, $success);
        renderFooter();
        exit;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (attemptLogin($pdo, $_POST['email'] ?? '', $_POST['password'] ?? '')) {
                header('Location: ?page=dashboard');
                exit;
            }
            $error = 'Invalid credentials.';
        }
        renderHeader('Login');
        renderAuthForm('login', $error, $success);
        renderFooter();
        exit;

    case 'dashboard':
        requireLogin();
        renderDashboard($pdo);
        exit;

    case 'clients':
        requireLogin();
        handleClients($pdo);
        exit;

    case 'services':
        requireLogin();
        handleServices($pdo);
        exit;

    case 'appointments':
        requireLogin();
        handleAppointments($pdo);
        exit;

    default:
        http_response_code(404);
        echo 'Page not found';
        exit;
}

/**
 * Rendering helpers.
 */
function renderHeader(string $title): void
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . e($title) . ' - Service Scheduler</title>';
    echo '<link rel="stylesheet" href="styles.css"></head><body><div class="container">';
    echo '<h1>Service Scheduler</h1>';
}

function renderFooter(): void
{
    echo '</div></body></html>';
}

function renderNav(): void
{
    echo '<nav><a href="?page=dashboard">Dashboard</a> | <a href="?page=clients">Clients</a> | <a href="?page=services">Services</a> | <a href="?page=appointments">Appointments</a> | <a href="?page=logout">Log out</a></nav>';
}

function renderDashboard(PDO $pdo): void
{
    $stats = dashboardStats($pdo);
    $upcoming = fetchUpcomingAppointments($pdo);

    renderHeader('Dashboard');
    renderNav();

    echo '<h2>Overview</h2>';
    echo '<div class="dashboard-grid">';
    echo renderStatCard('Clients', (string)$stats['clients']);
    echo renderStatCard('Services', (string)$stats['services']);
    echo renderStatCard('Pending appts.', (string)$stats['pending']);
    echo renderStatCard('Today', (string)$stats['today']);
    echo '</div>';

    echo '<h3>Upcoming appointments</h3>';
    if (!$upcoming) {
        echo '<p class="muted">No upcoming appointments.</p>';
    } else {
        echo '<table><tr><th>When</th><th>Client</th><th>Service</th><th>Status</th></tr>';
        foreach ($upcoming as $item) {
            echo '<tr>';
            echo '<td>' . e($item['scheduled_to']) . '</td>';
            echo '<td>' . e($item['client']) . '</td>';
            echo '<td>' . e($item['service']) . '</td>';
            echo '<td>' . e($item['status']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    renderFooter();
}

function renderStatCard(string $label, string $value): string
{
    return '<div class="card"><div class="card-value">' . e($value) . '</div><div class="card-label">' . e($label) . '</div></div>';
}

function dashboardStats(PDO $pdo): array
{
    $clients = (int)$pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
    $services = (int)$pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
    $pending = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();
    $today = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_to) = CURDATE()")->fetchColumn();

    return [
        'clients' => $clients,
        'services' => $services,
        'pending' => $pending,
        'today' => $today,
    ];
}

function fetchUpcomingAppointments(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT a.scheduled_to, c.name AS client, s.name AS service, a.status FROM appointments a JOIN clients c ON c.id = a.client_id JOIN services s ON s.id = a.service_id WHERE a.scheduled_to >= NOW() ORDER BY a.scheduled_to ASC LIMIT 5');
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function renderAuthForm(string $type, ?string $error, ?string $success): void
{
    if ($error) {
        echo '<div class="alert error">' . e($error) . '</div>';
    }
    if ($success) {
        echo '<div class="alert success">' . e($success) . '</div>';
    }

    if ($type === 'login') {
        echo '<form method="POST"><label>E-mail</label><input name="email" type="email" required>';
        echo '<label>Password</label><input name="password" type="password" required>';
        echo '<button type="submit">Sign in</button></form>';
        echo '<p><a href="?page=register">Create account</a></p>';
    } else {
        echo '<form method="POST"><label>Name</label><input name="name" required>';
        echo '<label>E-mail</label><input name="email" type="email" required>';
        echo '<label>Password</label><input name="password" type="password" required>';
        echo '<button type="submit">Register</button></form>';
        echo '<p><a href="?page=login">I already have an account</a></p>';
    }
}

/**
 * CRUD: Clients
 */
function handleClients(PDO $pdo): void
{
    $error = $success = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['delete_id'])) {
            $stmt = $pdo->prepare('DELETE FROM clients WHERE id = :id');
            $stmt->execute(['id' => (int)$_POST['delete_id']]);
            $success = 'Client removed.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO clients (name, email, phone, notes, created_at) VALUES (:name, :email, :phone, :notes, NOW())');
            $stmt->execute([
                'name' => $_POST['name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'notes' => $_POST['notes'] ?? '',
            ]);
            $success = 'Client saved.';
        }
    }

    $clients = $pdo->query('SELECT * FROM clients ORDER BY created_at DESC')->fetchAll();

    renderHeader('Clients');
    renderNav();
    if ($error) {
        echo '<div class="alert error">' . e($error) . '</div>';
    }
    if ($success) {
        echo '<div class="alert success">' . e($success) . '</div>';
    }

    echo '<h2>New Client</h2>';
    echo '<form method="POST"><label>Name</label><input name="name" required>';
    echo '<label>E-mail</label><input name="email" type="email">';
    echo '<label>Phone</label><input name="phone">';
    echo '<label>Notes</label><textarea name="notes"></textarea>';
    echo '<button type="submit">Save</button></form>';

    echo '<h2>Registered Clients</h2><table><tr><th>Name</th><th>Contact</th><th>Actions</th></tr>';
    foreach ($clients as $client) {
        echo '<tr><td>' . e($client['name']) . '</td><td>' . e($client['email'] . ' / ' . $client['phone']) . '</td><td>';
        echo '<form method="POST" style="display:inline"><input type="hidden" name="delete_id" value="' . (int)$client['id'] . '"><button type="submit" onclick="return confirm(\'Delete?\')">Delete</button></form>';
        echo '</td></tr>';
    }
    echo '</table>';
    renderFooter();
}

/**
 * CRUD: Services
 */
function handleServices(PDO $pdo): void
{
    $error = $success = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['delete_id'])) {
            $stmt = $pdo->prepare('DELETE FROM services WHERE id = :id');
            $stmt->execute(['id' => (int)$_POST['delete_id']]);
            $success = 'Service removed.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO services (name, description, price, duration_minutes, created_at) VALUES (:name, :description, :price, :duration_minutes, NOW())');
            $stmt->execute([
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'price' => (float)($_POST['price'] ?? 0),
                'duration_minutes' => (int)($_POST['duration_minutes'] ?? 30),
            ]);
            $success = 'Service saved.';
        }
    }

    $services = $pdo->query('SELECT * FROM services ORDER BY created_at DESC')->fetchAll();

    renderHeader('Services');
    renderNav();
    if ($error) {
        echo '<div class="alert error">' . e($error) . '</div>';
    }
    if ($success) {
        echo '<div class="alert success">' . e($success) . '</div>';
    }

    echo '<h2>New Service</h2>';
    echo '<form method="POST"><label>Name</label><input name="name" required>';
    echo '<label>Description</label><textarea name="description"></textarea>';
    echo '<label>Price</label><input name="price" type="number" step="0.01" min="0">';
    echo '<label>Duration (minutes)</label><input name="duration_minutes" type="number" min="10" step="5" value="30">';
    echo '<button type="submit">Save</button></form>';

    echo '<h2>Registered Services</h2><table><tr><th>Name</th><th>Price</th><th>Duration</th><th>Actions</th></tr>';
    foreach ($services as $service) {
        echo '<tr><td>' . e($service['name']) . '</td><td>R$ ' . e(number_format((float)$service['price'], 2, ',', '.')) . '</td><td>' . e((string)$service['duration_minutes']) . ' min</td><td>';
        echo '<form method="POST" style="display:inline"><input type="hidden" name="delete_id" value="' . (int)$service['id'] . '"><button type="submit" onclick="return confirm(\'Delete?\')">Delete</button></form>';
        echo '</td></tr>';
    }
    echo '</table>';
    renderFooter();
}

/**
 * CRUD: Appointments
 */
function handleAppointments(PDO $pdo): void
{
    $error = $success = null;

    $clients = $pdo->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();
    $services = $pdo->query('SELECT id, name FROM services ORDER BY name')->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['delete_id'])) {
            $stmt = $pdo->prepare('DELETE FROM appointments WHERE id = :id');
            $stmt->execute(['id' => (int)$_POST['delete_id']]);
            $success = 'Appointment removed.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO appointments (client_id, service_id, user_id, scheduled_to, status, notes, created_at) VALUES (:client_id, :service_id, :user_id, :scheduled_to, :status, :notes, NOW())');
            $stmt->execute([
                'client_id' => (int)($_POST['client_id'] ?? 0),
                'service_id' => (int)($_POST['service_id'] ?? 0),
                'user_id' => $_SESSION['user']['id'] ?? null,
                'scheduled_to' => $_POST['scheduled_to'] ?? '',
                'status' => $_POST['status'] ?? 'pending',
                'notes' => $_POST['notes'] ?? '',
            ]);
            $success = 'Appointment created.';
        }
    }

    $appointments = $pdo->query('SELECT a.id, c.name AS client, s.name AS service, a.scheduled_to, a.status FROM appointments a JOIN clients c ON c.id = a.client_id JOIN services s ON s.id = a.service_id ORDER BY a.scheduled_to DESC')->fetchAll();

    renderHeader('Appointments');
    renderNav();
    if ($error) {
        echo '<div class="alert error">' . e($error) . '</div>';
    }
    if ($success) {
        echo '<div class="alert success">' . e($success) . '</div>';
    }

    echo '<h2>New Appointment</h2>';
    echo '<form method="POST"><label>Client</label><select name="client_id" required>';
    foreach ($clients as $client) {
        echo '<option value="' . (int)$client['id'] . '">' . e($client['name']) . '</option>';
    }
    echo '</select>';
    echo '<label>Service</label><select name="service_id" required>';
    foreach ($services as $service) {
        echo '<option value="' . (int)$service['id'] . '">' . e($service['name']) . '</option>';
    }
    echo '</select>';
    echo '<label>Date/Time</label><input name="scheduled_to" type="datetime-local" required>';
    echo '<label>Status</label><select name="status"><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="done">Done</option></select>';
    echo '<label>Notes</label><textarea name="notes"></textarea>';
    echo '<button type="submit">Schedule</button></form>';

    echo '<h2>Appointments</h2><table><tr><th>Client</th><th>Service</th><th>When</th><th>Status</th><th>Actions</th></tr>';
    foreach ($appointments as $appt) {
        echo '<tr><td>' . e($appt['client']) . '</td><td>' . e($appt['service']) . '</td><td>' . e($appt['scheduled_to']) . '</td><td>' . e($appt['status']) . '</td><td>';
        echo '<form method="POST" style="display:inline"><input type="hidden" name="delete_id" value="' . (int)$appt['id'] . '"><button type="submit" onclick="return confirm(\'Delete?\')">Delete</button></form>';
        echo '</td></tr>';
    }
    echo '</table>';
    renderFooter();
}
