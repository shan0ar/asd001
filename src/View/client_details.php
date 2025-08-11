<?php
require __DIR__ . '/layout.php';
$clients = \App\Model\Client::all($config['db']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($client['name']) ?> | OSINTApp</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js"></script>
</head>
<body>
<?= sidebar($clients, $client['id']) ?>
<main>
    <h1><?= htmlspecialchars($client['name']) ?></h1>
    <div class="client-desc"><?= nl2br(htmlspecialchars($client['description'])) ?></div>
    <div class="client-assets">
        <strong>Assets :</strong>
        <ul>
            <?php foreach($assets as $a): ?>
                <li><?= htmlspecialchars($a['asset_type']) ?> : <?= htmlspecialchars($a['value']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="scan-panel">
        <div class="calendar-panel">
            <div id="calendar"></div>
        </div>
        <div class="scan-controls">
            <div>Les scans pour ce client sont <b><?= htmlspecialchars($client['type']) ?></b></div>
            <form method="post" action="?route=scan_customize&id=<?= $client['id'] ?>">
                <label>Fréquence :</label>
                <select name="scan_type">
                    <option value="hebdomadaire">Hebdomadaire</option>
                    <option value="mensuel">Mensuel</option>
                    <option value="trimestriel">Trimestriel</option>
                    <option value="semestriel">Semestriel</option>
                    <option value="annuel">Annuel</option>
                </select>
                <label>Jour :</label>
                <select name="day">
                    <option value="monday">Lundi</option>
                    <option value="tuesday">Mardi</option>
                    <option value="wednesday">Mercredi</option>
                    <option value="thursday">Jeudi</option>
                    <option value="friday">Vendredi</option>
                    <option value="saturday">Samedi</option>
                    <option value="sunday">Dimanche</option>
                </select>
                <label>Heure :</label>
                <input type="number" name="hour" min="0" max="23" value="0" style="width:50px;">
                <label>Minute :</label>
                <input type="number" name="minute" min="0" max="59" value="1" style="width:50px;">
                <button class="btn" type="submit">Enregistrer</button>
            </form>
            <form method="post" action="?route=scan_launch&id=<?= $client['id'] ?>">
                <button class="btn-green" type="submit">Personnaliser / Lancer le prochain scan</button>
            </form>
        </div>
    </div>
    <h2>Résultats du jour sélectionné</h2>
    <!-- Tableau des sous-domaines découverts ce jour -->
    <div>
        <h3>Nouveaux sous-domaines découverts</h3>
        <table id="subdomains-table">
            <thead>
                <tr>
                    <th onclick="sortTable('subdomains-table', 0)">Sous-domaine</th>
                    <th onclick="sortTable('subdomains-table', 1)">IP</th>
                    <th onclick="sortTable('subdomains-table', 2)">Première détection</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($subdomainsToday as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['subdomain']) ?></td>
                    <td><?= htmlspecialchars($d['ip']) ?></td>
                    <td><?= htmlspecialchars($d['first_seen']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button onclick="window.location='?route=client_subdomains&id=<?= $client['id'] ?>'" class="btn">Afficher tous les sous-domaines détectés</button>
    </div>
    <!-- Tableau WhatWeb triable -->
    <div>
        <h3>Empreinte des services web détectés (WhatWeb)</h3>
        <table id="whatweb-table">
            <thead>
                <tr>
                    <th onclick="sortTable('whatweb-table', 0)">Domaine/Sous-domaine (IP)</th>
                    <th onclick="sortTable('whatweb-table', 1)">Port</th>
                    <th onclick="sortTable('whatweb-table', 2)">Sortie brute</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($whatwebToday as $w): ?>
                <tr>
                    <td><?= htmlspecialchars($w['domain_ip']) ?></td>
                    <td><?= htmlspecialchars($w['port']) ?></td>
                    <td><pre><?= htmlspecialchars($w['raw_output']) ?></pre></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
<script src="assets/calendar.js"></script>
<script src="assets/script.js"></script>
</body>
</html>