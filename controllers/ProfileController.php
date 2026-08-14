<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\AuditLogger;
use App\Models\User;

class ProfileController extends BaseController
{
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }

    public function index(): void
    {
        Auth::requireAuth();
        $userId = Auth::id();
        $user = $userId ? $this->userModel->find($userId) : null;

        if (!$user) {
            Session::set('flash_error', 'Utilisateur introuvable.');
            $this->redirect('index.php?controller=dashboard&action=index');
        }

        $this->render('profile/index', [
            'pageTitle' => 'Mon profil',
            'user' => $user,
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function update(): void
    {
        Auth::requireAuth();
        $userId = Auth::id();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?controller=profile&action=index');
        }

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            Session::set('flash_error', 'Jeton de sécurité invalide.');
            $this->redirect('index.php?controller=profile&action=index');
        }

        $user = $this->userModel->find($userId);
        if (!$user) {
            Session::set('flash_error', 'Utilisateur introuvable.');
            $this->redirect('index.php?controller=dashboard&action=index');
        }

        $data = [
            'first_name' => $this->getPost('first_name'),
            'last_name'  => $this->getPost('last_name'),
            'email'      => $this->getPost('email'),
            'phone'      => $this->getPost('phone'),
        ];

        $errors = $this->validate(['first_name', 'last_name', 'email']);
        if ($errors === true) {
            $old = $user;
            $this->userModel->update($userId, $data);
            AuditLogger::log('update', 'profile', 'Profil mis à jour', $userId, $old, $data);

            $updatedUser = $this->userModel->find($userId);
            if ($updatedUser) {
                Session::set('user', $updatedUser);
            }

            Session::set('flash_success', 'Profil mis à jour avec succès.');
            $this->redirect('index.php?controller=profile&action=index');
        }

        $this->render('profile/index', [
            'pageTitle' => 'Mon profil',
            'user' => array_merge($user, $_POST),
            'errors' => is_array($errors) ? $errors : [],
        ]);
    }

    public function changePassword(): void
    {
        Auth::requireAuth();
        $userId = Auth::id();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?controller=profile&action=index');
        }

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            Session::set('flash_error', 'Jeton de sécurité invalide.');
            $this->redirect('index.php?controller=profile&action=index');
        }

        $currentPassword = $this->getPost('current_password');
        $newPassword = $this->getPost('new_password');
        $confirmPassword = $this->getPost('confirm_password');

        $user = $this->userModel->find($userId);
        if (!$user) {
            Session::set('flash_error', 'Utilisateur introuvable.');
            $this->redirect('index.php?controller=dashboard&action=index');
        }

        $errors = [];

        if (empty($currentPassword) || !password_verify($currentPassword, $user['password_hash'])) {
            $errors['current_password'] = 'Mot de passe actuel incorrect.';
        }

        if (empty($newPassword) || strlen($newPassword) < 6) {
            $errors['new_password'] = 'Le mot de passe doit contenir au moins 6 caractères.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($errors)) {
            Session::set('flash_error', 'Veuillez corriger les erreurs.');
            Session::set('profile_errors', $errors);
            $this->redirect('index.php?controller=profile&action=index');
        }

        $old = $user;
        $this->userModel->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);
        AuditLogger::log('update', 'profile', 'Mot de passe modifié', $userId, $old);

        Session::set('flash_success', 'Mot de passe modifié avec succès.');
        $this->redirect('index.php?controller=profile&action=index');
    }
}
