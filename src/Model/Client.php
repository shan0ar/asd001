<?php
namespace App\Model;

use PDO;

class Client {
    public static function all($dbconf) {
        $pdo = self::pdo($dbconf);
        return $pdo->query("SELECT * FROM clients ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find($id, $dbconf) {
        $pdo = self::pdo($dbconf);
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function assets($client_id, $dbconf) {
        $pdo = self::pdo($dbconf);
        $stmt = $pdo->prepare("SELECT * FROM client_assets WHERE client_id = ?");
        $stmt->execute([$client_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create($name, $desc, $type, $assets, $dbconf) {
        $pdo = self::pdo($dbconf);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO clients (name, description, type) VALUES (?, ?, ?) RETURNING id");
        $stmt->execute([$name, $desc, $type]);
        $client_id = $stmt->fetchColumn();
        foreach ($assets as $asset) {
            $astmt = $pdo->prepare("INSERT INTO client_assets (client_id, asset_type, value) VALUES (?, ?, ?)");
            $astmt->execute([$client_id, $asset['type'], $asset['value']]);
        }
        $pdo->commit();
        return $client_id;
    }

    public static function setScanSchedule($id, $scan_type, $cron, $dbconf) {
        $pdo = self::pdo($dbconf);
        $stmt = $pdo->prepare("UPDATE clients SET type = ?, description = description || '\n[SCAN_SCHEDULE:$cron]' WHERE id = ?");
        $stmt->execute([$scan_type, $id]);
    }

    public static function search($q, $dbconf) {
        $pdo = self::pdo($dbconf);
        $stmt = $pdo->prepare("SELECT id, name FROM clients WHERE LOWER(name) LIKE LOWER(?) ORDER BY name");
        $stmt->execute(['%' . $q . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function pdo($dbconf) {
        static $pdo;
        if (!$pdo) {
            $pdo = new PDO(
                "pgsql:host={$dbconf['host']};port={$dbconf['port']};dbname={$dbconf['dbname']}",
                $dbconf['user'],
                $dbconf['pass']
            );
        }
        return $pdo;
    }
}