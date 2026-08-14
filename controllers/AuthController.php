<?php
namespace App\Controllers;

use App\Models\User;
use App\Helpers\Session;
use App\Helpers\Auth;
use App\Helpers\AuditLogger;

/**
 * Contrôleur d'authentification : connexion, déconnexion, mot de passe oublié.
 */
class AuthController extends BaseController
{
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }

    /**
     * GET : affiche le formulaire de connexion. POST : authentifie l'utilisateur.
     */
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['username', 'password']);

            if ($errors === true) {
                $username = $this->getPost('username');
                $password = $_POST['password'] ?? ''; // non assaini volontairement
                $user = $this->authenticate($username, $password);

                if ($user) {
                    Session::regenerate();
                    $perm = $this->userModel->loadPermissions($user['id']);
                    Session::set('user_id', $user['id']);
                    Session::set('user', $user);
                    Session::set('user_role', $perm['role']);
                    Session::set('permissions', $perm['permissions']);

                    $this->userModel->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
                    AuditLogger::log('login', 'auth', 'Connexion réussie', $user['id']);

                    $this->redirect($this->url('dashboard', 'index'));
                }

                Session::set('flash_error', 'Nom d\'utilisateur ou mot de passe incorrect.');
            }

            $this->render('auth/login', [
                'pageTitle' => 'Connexion',
                'errors'    => is_array($errors) ? $errors : [],
                'username'  => $this->getPost('username'),
            ], false);
            return;
        }

        if (Auth::check()) {
            $this->redirect($this->url('dashboard', 'index'));
        }

        $this->render('auth/login', ['pageTitle' => 'Connexion'], false);
    }

    /**
     * Déconnecte l'utilisateur et redirige vers la connexion.
     */
    public function logout(): void
    {
        if (Auth::check()) {
            AuditLogger::log('logout', 'auth', 'Déconnexion', Auth::id());
        }
        Session::destroy();
        $this->redirect($this->url('auth', 'login'));
    }

    /**
     * GET : affiche le formulaire. POST : traite la demande de réinitialisation.
     */
    public function forgotPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['email']);
            if ($errors === true) {
                $email = filter_var($this->getPost('email'), FILTER_SANITIZE_EMAIL);
                $user = $this->userModel->findBy('email', $email);

                // Réponse générique pour ne pas révéler l'existence du compte.
                if ($user) {
                    AuditLogger::log('forgot_password', 'auth', 'Demande de réinitialisation', $user['id']);
                }
                Session::set('flash_success', 'Si l\'adresse existe, un e-mail de réinitialisation a été envoyé.');
                $this->redirect($this->url('auth', 'login'));
            }

            $this->render('auth/forgot_password', [
                'pageTitle' => 'Mot de passe oublié',
                'errors'    => is_array($errors) ? $errors : [],
                'email'     => $this->getPost('email'),
            ], false);
            return;
        }

        $this->render('auth/forgot_password', ['pageTitle' => 'Mot de passe oublié'], false);
    }

    /**
     * Vérifie les identifiants auprès du modèle utilisateur.
     */
    private function authenticate(string $username, string $password)
    {
        if ($username === '' || $password === '') {
            return false;
        }
        return $this->userModel->verifyCredentials($username, $password);
    }
}
