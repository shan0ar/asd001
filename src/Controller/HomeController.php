<?php
namespace App\Controller;

class HomeController {
    private $config;
    public function __construct($config) { $this->config = $config; }
    public function index() {
        require __DIR__ . '/../View/home.php';
    }
}