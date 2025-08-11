<?php
namespace App\Controller;

use App\Model\User;

class AuthController {
    private $config;
    public function __construct($config) { $this->config = $config; }

    public function login() {
        require __DIR__ . '/../View/login.php';
    }

    public function authenticate() {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $user = User::findByUsername($username, $this->config['db']);
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: ?route=home');
        } else {
            $error = "Identifiants invalides.";
            require __DIR__ . '/../View/login.php';
        }
    }

    public function logout() {
        session_destroy();
        header('Location: ?route=login');
    }
}