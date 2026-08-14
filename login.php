<?php
/**
 * Page de connexion - Attendance Pro
 * Fichier: login.php
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'autoload.php';

use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Auth;

Session::start();

if (isset($_GET['logout'])) {
    Session::destroy();
    header('Location: login.php');
    exit;
}

if (Auth::check()) {
    header('Location: index.php?controller=dashboard&action=index');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!Csrf::verify($token)) {
        $error = 'Jeton de sécurité invalide. Veuillez recharger la page.';
    } elseif ($username === '' || $password === '') {
        $error = 'Veuillez saisir votre nom d\'utilisateur et votre mot de passe.';
    } else {
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'AuthController.php';
        
        $controller = new \App\Controllers\AuthController();
        
        $_POST['username'] = $username;
        $_POST['password'] = $password;
        
        ob_start();
        $controller->login();
        $output = ob_get_clean();
        
        if (strpos($output, 'Tableau de bord') !== false || !Session::get('flash_error')) {
            header('Location: index.php?controller=dashboard&action=index');
            exit;
        }
        
        $error = Session::get('flash_error', 'Identifiants incorrects.');
    }
}

$csrfToken = Csrf::token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Attendance Pro</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-brand">
            <div class="brand-icon"><i class="fa-solid fa-fingerprint"></i></div>
            <h1>Attendance Pro</h1>
            <p>Système de Gestion de Présence</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form class="login-form" method="POST" action="" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label class="form-label" for="username">Nom d'utilisateur</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="username" name="username"
                           placeholder="admin" value="<?= htmlspecialchars($username) ?>" required autofocus>
                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Mot de passe</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="••••••••" required>
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">
                <i class="fa-solid fa-right-to-bracket"></i> Se connecter
            </button>
        </form>

        <div class="login-footer">
            &copy; <?= date('Y') ?> Attendance Pro. Tous droits réservés.
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
