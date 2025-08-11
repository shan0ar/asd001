<?php
namespace App\Controller;

use App\Model\Client;
use App\Model\Scan;

class ClientController {
    private $config;
    public function __construct($config) { $this->config = $config; }

    public function list() {
        $clients = Client::all($this->config['db']);
        require __DIR__ . '/../View/clients.php';
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $description = $_POST['description'] ?? '';
            $assets = $_POST['assets'] ?? [];
            $type = $_POST['type'] ?? '';
            $db = $this->config['db'];
            $client_id = Client::create($name, $description, $type, $assets, $db);
            header('Location: ?route=client_details&id=' . $client_id);
            exit;
        }
        require __DIR__ . '/../View/client_create.php';
    }

    public function details() {
        $id = $_GET['id'] ?? null;
        if (!$id) exit("Client ID requis.");
        $client = Client::find($id, $this->config['db']);
        $assets = Client::assets($id, $this->config['db']);
        $scans = Scan::byClient($id, $this->config['db']);
        require __DIR__ . '/../View/client_details.php';
    }

    public function customizeScan() {
        // POST: Save custom scan schedule for a client
        $id = $_GET['id'] ?? null;
        if (!$id) exit("Client ID requis.");
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $scan_type = $_POST['scan_type'] ?? 'hebdomadaire';
            $day = $_POST['day'] ?? 'monday';
            $hour = $_POST['hour'] ?? '00';
            $minute = $_POST['minute'] ?? '01';
            $cron = "$scan_type;$day;$hour;$minute";
            Client::setScanSchedule($id, $scan_type, $cron, $this->config['db']);
            header('Location: ?route=client_details&id=' . $id);
        }
    }

    public function launchScan() {
        // Lancement du scan (simulation, à adapter pour exécution réelle)
        $id = $_GET['id'] ?? null;
        if (!$id) exit("Client ID requis.");
        Scan::launch($id, $this->config['db']);
        header('Location: ?route=client_details&id=' . $id);
    }
}