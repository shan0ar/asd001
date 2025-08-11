<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/autoload.php';

$config = require __DIR__ . '/../config/config.php';

$path = $_GET['route'] ?? 'login';

if (!isset($_SESSION['user_id']) && $path !== 'login' && $path !== 'auth') {
    header('Location: ?route=login');
    exit;
}

switch ($path) {
    case 'login':
        (new \App\Controller\AuthController($config))->login();
        break;
    case 'logout':
        (new \App\Controller\AuthController($config))->logout();
        break;
    case 'auth':
        (new \App\Controller\AuthController($config))->authenticate();
        break;
    case 'home':
        (new \App\Controller\HomeController($config))->index();
        break;
    case 'clients':
        (new \App\Controller\ClientController($config))->list();
        break;
    case 'client_create':
        (new \App\Controller\ClientController($config))->create();
        break;
    case 'client_details':
        (new \App\Controller\ClientController($config))->details();
        break;
    case 'scan_customize':
        (new \App\Controller\ClientController($config))->customizeScan();
        break;
    case 'scan_launch':
        (new \App\Controller\ClientController($config))->launchScan();
        break;
    case 'api_search_clients':
        (new \App\Controller\ApiController($config))->searchClients();
        break;
    // ... autres routes
    default:
        header('HTTP/1.0 404 Not Found');
        echo "Page introuvable";
}