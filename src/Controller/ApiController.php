<?php
namespace App\Controller;
use App\Model\Client;

class ApiController {
    private $config;
    public function __construct($config) { $this->config = $config; }
    public function searchClients() {
        $q = $_GET['q'] ?? '';
        $clients = Client::search($q, $this->config['db']);
        header('Content-Type: application/json');
        echo json_encode($clients);
    }
}