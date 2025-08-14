<?php
require __DIR__ . '/layout.php';
$clients = \App\Model\Client::all($config['db']);

// --- Blacklists et fonctions (placées avant pour usage dans parsing) ---
$blacklistTechnosPath = '/var/www/html/blacklist_technos.txt';
$blacklistTechnos = [];
if (file_exists($blacklistTechnosPath)) {
    $blacklistTechnos = file($blacklistTechnosPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $blacklistTechnos = array_map('trim', $blacklistTechnos);
}
function isTechnoBlacklisted($techno, $blacklistTechnos) {
    $techno = trim((string)($techno ?? ''));
    foreach ($blacklistTechnos as $bl) {
        $bl = trim($bl);
        if ($bl === '') continue;
        // Si \Title\ : match exact
        if (preg_match('/^\\\\(.+)\\\\$/', $bl, $m)) {
            if ($techno === $m[1]) return true;
        } else {
            if (stripos($techno, $bl) !== false) return true;
        }
    }
    return false;
}
function isNationaliteOrUrl($techno) {
    $techno = trim((string)($techno ?? ''));
    if (preg_match('/^(FR|UK|ES|IT|DE|NL|US|RU|CN|JP|IN|BR|PT|PL|BE|CA|CH|SE|NO|FI|DK|IE|AT|GR|TR|CZ|SK|HU|RO|BG|SI|HR|LT|LV|EE|LU|LI|MC|SM|VA|AD|IS|AL|BY|MD|UA|GE|AM|AZ|KZ|UZ|TM|KG|TJ|MN|RS|ME|MK|BA|XK|AF|DZ|AO|AR|AU|BD|CL|CO|CU|EC|EG|ET|GH|ID|IL|IQ|IR|KE|KR|MA|MX|MY|NG|NP|NZ|PA|PE|PH|PK|QA|SA|SG|SY|TH|TN|VE|VN|ZA|ZW)$/i', $techno)) {
        return true;
    }
    if (preg_match('/^\/\//', $techno)) return true;
    if (preg_match('/^[a-z0-9\-\.]+\.[a-z]{2,}(\/)?$/i', $techno)) return true;
    return false;
}
/**
 * Extrait la version (numéro après la techno) pour l'insertion et l'affichage
 * Retourne un tableau de [Technologie (nettoyée), Version (si trouvée)]
 */
function parseTechnoVersion($techno, $version, $blacklistTechnos) {
    $rows = [];
    $t = trim((string)($techno ?? ''));
    $v = trim((string)($version ?? ''));

    if (isTechnoBlacklisted($t, $blacklistTechnos)) return $rows;
    if (isNationaliteOrUrl($t)) return $rows;

    // Cas Nom[Elementor 3.31.2; ...]
    if (preg_match('/^([^\[]+)\[([^\]]+)\]?$/', $t, $m)) {
        $main = trim($m[1]);
        $inside = trim($m[2]);
        $parts = explode(';', $inside);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            // Version dans le part
            if (preg_match('/^([^\d]*)([0-9]+(?:\.[0-9]+)+)/', $part, $n)) {
                $rows[] = [$main . ' ' . trim($n[1]), $n[2]];
            } else {
                $rows[] = [$main . ' ' . $part, ''];
            }
        }
        return $rows;
    }

    // Cas général : concatène techno + version si version non vide
    $label = $t;
    if ($v !== '') $label .= ' ' . $v;

    // Cherche version dans la chaîne complète (après le nom de la techno)
    if (preg_match('/^([^\d]*)([0-9]+(?:\.[0-9]+)+)/', $label, $n)) {
        $rows[] = [trim($n[1]), $n[2]];
        return $rows;
    }

    // Sinon, pas de version détectée
    $rows[] = [$label, ''];
    return $rows;
}

// --- Extraction de tous les champs textuels du JSON ---
function extractAllStringsFromJson($data, &$result = []) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            extractAllStringsFromJson($value, $result);
        }
    } elseif (is_string($data)) {
        $result[] = strtolower($data);
    }
}

$technologiesJsonPath = '/var/www/html/asd001/technologies.json';
$allowedTechnologiesStrings = [];
if (file_exists($technologiesJsonPath)) {
    $json = file_get_contents($technologiesJsonPath);
    $techArray = json_decode($json, true);
    extractAllStringsFromJson($techArray, $allowedTechnologiesStrings);
}

// --- AJOUT : Suppression des résultats Amass ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_amass_results']) && isset($client['id'])) {
    $pdo = new PDO(
        "pgsql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['dbname']}",
        $config['db']['user'],
        $config['db']['pass']
    );
    // Suppression des résultats temporaires Amass
    $stmt = $pdo->prepare("DELETE FROM amass_scan_tmp WHERE client_id = :client_id");
    $stmt->execute(['client_id' => $client['id']]);
    // Suppression dans les autres tables possibles
    $tables = ['amass_results', 'amass_result', 'amass_details', 'amass_scan', 'discovered_subdomains', 'subdomains'];
    foreach ($tables as $t) {
        if ($t === 'amass_scan' || $t === 'amass_results') {
            $sql = "DELETE FROM $t WHERE client_id = :client_id";
        } else {
            $sql = "DELETE FROM $t WHERE client_id = :client_id";
        }
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['client_id' => $client['id']]);
        } catch (Exception $e) {
            // ignore si la table ou colonne n'existe pas
        }
    }
    // Suppression des fichiers log Amass restants
    foreach (glob("/tmp/amass_scan_*.log") as $logfile) {
        @unlink($logfile);
    }
    header("Location: ?route=client_details&id=" . $client['id']);
    exit;
}

// --- Lancement du scan Amass EN ARRIERE PLAN + ENREGISTREMENT EN BASE + MAJ TEMPS REEL ---
$amass_debug_output = null;
$amass_logfile = null;
$amass_scan_id = null;
if (isset($client['id'])) {
    $amass_scan_id = null;
    $amass_logfile = null;
    $pdo = new PDO(
        "pgsql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['dbname']}",
        $config['db']['user'],
        $config['db']['pass']
    );

    // Lancement du scan
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['launch_amass_now'])) {
        $stmt = $pdo->prepare("SELECT value FROM assets WHERE client_id = :id AND asset_type = 'domain' LIMIT 1");
        $stmt->execute(['id' => $client['id']]);
        $row = $stmt->fetch();
        if ($row) {
            $domaine = trim($row['value']);
            // 1. Insère une ligne temporaire en base
            $insert = $pdo->prepare("INSERT INTO amass_scan_tmp (client_id, output, finished) VALUES (:client_id, '', false) RETURNING id");
            $insert->execute(['client_id' => $client['id']]);
            $amass_scan_id = $insert->fetchColumn();
            $amass_logfile = "/tmp/amass_scan_{$amass_scan_id}.log";
            // 2. Vide le log avant de lancer un nouveau scan
            file_put_contents($amass_logfile, "Scan Amass en cours...\n");

            // 3. Lancer amass en arrière-plan
            $cmd_amass = "HOME=/tmp timeout 150s /opt/go/bin/amass enum -passive -dir /tmp/amass_tmp -d " . escapeshellarg($domaine) . " > " . escapeshellarg($amass_logfile) . " 2>&1 &";
            shell_exec($cmd_amass);

            // 4. Lancer la synchro en arrière-plan
            $cmd_sync = "/usr/local/bin/amass_sync_to_db.sh $amass_scan_id " . escapeshellarg($amass_logfile) . " > /dev/null 2>&1 &";
            shell_exec($cmd_sync);

            // 5. Redirige pour éviter le relancement lors du refresh
            header("Location: ?route=client_details&id=" . $client['id']);
            exit;
        } else {
            $amass_debug_output = "Aucun domaine principal n'a été trouvé pour ce client.";
        }
    }
    // Affiche le dernier scan temporaire pour ce client
    $stmt = $pdo->prepare("SELECT output, finished FROM amass_scan_tmp WHERE client_id = :client_id ORDER BY start_time DESC LIMIT 1");
    $stmt->execute(['client_id' => $client['id']]);
    $row = $stmt->fetch();
    $amass_debug_output = $row ? $row['output'] : '';
    $amass_scan_finished = $row ? $row['finished'] : false;
}

// --- Lancement manuel Whatweb (inchangé) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['launch_whatweb_now']) && isset($client['id'])) {
    $pdo = new PDO(
        "pgsql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['dbname']}",
        $config['db']['user'],
        $config['db']['pass']
    );
    // Vérifier si un scan existe déjà aujourd'hui pour ce client
    $stmt = $pdo->prepare("SELECT id FROM whatweb WHERE client_id = :client_id AND scan_date::date = CURRENT_DATE");
    $stmt->execute(['client_id' => $client['id']]);
    $already = $stmt->fetch();
    if ($already) {
        $whatweb_error = "Un scan existe déjà pour aujourd'hui. Supprime-le d'abord si tu veux le relancer.";
    } else {
        $stmt = $pdo->prepare("SELECT value FROM assets WHERE client_id = :id AND asset_type = 'domain' LIMIT 1");
        $stmt->execute(['id' => $client['id']]);
        $row = $stmt->fetch();
        if ($row) {
            $domaine = $row['value'];
            $escaped = escapeshellarg($domaine);
            $output = shell_exec("whatweb $escaped 2>&1");
            $output_clean = preg_replace('/\e\[[0-9;]*m/', '', $output);

            $insert = $pdo->prepare("INSERT INTO whatweb (client_id, scan_date, domain_ip, raw_output) VALUES (:client_id, NOW(), :domain_ip, :raw_output) RETURNING id");
            $insert->execute([
                'client_id' => $client['id'],
                'domain_ip' => $domaine,
                'raw_output' => $output_clean
            ]);
            $whatweb_id = $insert->fetchColumn();

            // --- PARSING WHATWEB AVEC FILTRAGE NOM + MOTS ENTRE CROCHETS ---
            if ($whatweb_id && $output_clean) {
                $lines = explode("\n", $output_clean);
                foreach ($lines as $line) {
                    if (preg_match('/\[(?:[0-9]{3} [A-Z]+)\](.+)$/', $line, $match)) {
                        $techs = explode(',', $match[1]);
                        foreach ($techs as $tech) {
                            $tech = trim($tech);
                            if ($tech === '') continue;
                            // Extraction du nom et de la valeur éventuelle entre []
                            if (preg_match('/^([^\[]+)\[([^\]]+)\]$/', $tech, $m)) {
                                $name = trim($m[1]);
                                $value = trim($m[2]);
                            } else {
                                $name = $tech;
                                $value = '';
                            }

                            // --- Exclusion blacklist stricte \nom\
                            $isBlacklistedStrict = false;
                            foreach ($blacklistTechnos as $bl) {
                                $bl = trim($bl);
                                if ($bl === '') continue;
                                if (preg_match('/^\\\\(.+)\\\\$/', $bl, $bm)) {
                                    if ($name === $bm[1]) {
                                        $isBlacklistedStrict = true;
                                        break;
                                    }
                                }
                            }
                            if ($isBlacklistedStrict) continue;

                            // --- WhiteList : nom regex dans technologies.json (insensible à la casse, inclusif)
                            $foundInJson = false;
                            foreach ($allowedTechnologiesStrings as $ref) {
                                if ($ref !== '' && preg_match('/'.preg_quote($ref, '/').'/i', $name)) {
                                    $foundInJson = true;
                                    break;
                                }
                            }
                            if (!$foundInJson) continue;

                            // --- Insertion en base selon le parsing adapté
                            $parsedRows = parseTechnoVersion($name, $value, $blacklistTechnos);
                            foreach ($parsedRows as $rowParsed) {
                                $insertTech = $pdo->prepare("INSERT INTO whatweb_technos (whatweb_id, name, version) VALUES (:whatweb_id, :name, :version)");
                                $insertTech->execute([
                                    'whatweb_id' => $whatweb_id,
                                    'name' => $rowParsed[0],      // nom nettoyé
                                    'version' => $rowParsed[1]    // version extraite (ou vide)
                                ]);
                            }
                        }
                    }
                }
            }

            header("Location: ?route=client_details&id=".$client['id']);
            exit;
        } else {
            $whatweb_error = "Aucun domaine principal n'a été trouvé pour ce client.";
        }
    }
}

// --- Suppression du résultat whatweb du jour ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_whatweb_today']) && isset($client['id'])) {
    $pdo = new PDO(
        "pgsql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['dbname']}",
        $config['db']['user'],
        $config['db']['pass']
    );
    $today = (new DateTime('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d');
    $ids = $pdo->prepare("SELECT id FROM whatweb WHERE client_id = :client_id AND scan_date::date = :today");
    $ids->execute(['client_id' => $client['id'], 'today' => $today]);
    $idsArr = $ids->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($idsArr)) {
        $in = implode(',', array_map('intval', $idsArr));
        $pdo->exec("DELETE FROM whatweb_technos WHERE whatweb_id IN ($in)");
        $pdo->exec("DELETE FROM whatweb WHERE id IN ($in)");
    }
    header("Location: ?route=client_details&id=".$client['id']);
    exit;
}

// --- Récupération des résultats Whatweb ---
$stmt = $pdo->prepare("
    SELECT
        w.id AS whatweb_id,
        w.scan_date,
        w.domain_ip,
        w.raw_output,
        t.name AS techno,
        t.version
    FROM whatweb w
    LEFT JOIN whatweb_technos t ON t.whatweb_id = w.id
    WHERE w.client_id = :client_id
    ORDER BY w.scan_date DESC, w.id DESC, t.name ASC
");
$stmt->execute(['client_id' => $client['id']]);
$whatwebResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Dates pour le calendrier ---
$calendarResults = [];
foreach ($whatwebResults as $row) {
    $date = (new DateTime($row['scan_date']))->format('Y-m-d');
    $calendarResults[$date] = true;
}

// --- Gestion sélection du jour affiché ---
$selectedDay = $_GET['calendar_day'] ?? null;
$selectedDayResults = [];
if ($selectedDay) {
    foreach ($whatwebResults as $row) {
        $date = (new DateTime($row['scan_date']))->format('Y-m-d');
        if ($date === $selectedDay) {
            $selectedDayResults[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($client['name']) ?> | OSINTApp</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js"></script>
    <style>
        .calendar-box { position: absolute; top: 1.2em; right: 22em; background: #fff; border: 1px solid #bbb; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); padding: 0.5em 0.5em 0.5em 0.5em; width: 220px; min-width: 180px; z-index: 10; font-size: 0.93em; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 1em; margin-bottom: 0.2em; }
        .calendar-arrow { cursor: pointer; user-select: none; font-size: 1.1em; color: #666; border-radius: 2px; padding: 0 0.2em; }
        .calendar-arrow:hover { background: #e0e0e0; }
        .calendar-table { width: 100%; border-collapse: collapse; text-align: center; margin-bottom: 0.3em; }
        .calendar-table th { color: #888; font-weight: normal; font-size: 0.97em; }
        .calendar-table td { width: 1.4em; height: 1.6em; cursor: pointer; border-radius: 4px; transition: background 0.15s, color 0.15s; font-size: 0.97em; }
        .calendar-table td.calendar-today { border: 1.5px solid #00a400; }
        .calendar-table td.calendar-result { background: #b5f7b1; color: #1a651a; font-weight: bold; }
        .calendar-table td.calendar-result:hover { background: #81e77a; }
        .calendar-table td.calendar-selected { background: #0c8c0c; color: #fff; }
        .calendar-table td.calendar-other-month { color: #ccc; background: #f8f8f8; }
        .whatweb-info-btn { display: inline-block; background: #eee; border-radius: 50%; width: 1.2em; height: 1.2em; text-align: center; font-size: 1em; font-weight: bold; color: #333; cursor: pointer; margin-left: 0.4em; border: 1px solid #bbb; line-height: 1.1em; }
        .whatweb-raw-output { display: none; max-width: 900px; background: #fcfcfc; border: 1px solid #aaa; color: #222; font-size: 0.97em; font-family: monospace; white-space: pre-wrap; padding: 0.8em 1em; margin: 0.6em 0 1.3em 0; overflow-x: auto; }
        .whatweb-raw-output.active { display: block; }
        .whatweb-scan-btn { margin-bottom: 0.5em; margin-top: 0.5em; }
        .amass-scan-btn { margin-bottom: 1em; }
        .amass-debug-output { background: #222; color: #b5ffb5; font-family: monospace; font-size: 0.98em; border: 1.5px solid #006600; border-radius: 6px; margin: 1em 0 2em 0; padding: 1em; max-width: 900px; overflow-x: auto; white-space: pre-wrap; }
    </style>
</head>
<body>
<?= sidebar($clients, $client['id']) ?>
<main>
    <div class="calendar-box" id="calendar-box"></div>
    <h1><?= htmlspecialchars($client['name']) ?>
        <a class="delete-client-btn" href="?route=client_delete&id=<?= $client['id'] ?>" onclick="return confirm('Supprimer ce client ? Cette action est irréversible.')">&#128465;</a>
    </h1>
    <div class="client-desc"><?= nl2br(htmlspecialchars($client['description'])) ?></div>
    <div class="client-assets">
        <strong>Assets :</strong>
        <ul>
            <?php foreach($assets as $a): ?>
                <li>
                    <?= htmlspecialchars($a['asset_type']) ?> : <?= htmlspecialchars($a['value']) ?>
                    <a class="remove-asset" href="?route=asset_delete&id=<?= $client['id'] ?>&asset_id=<?= $a['id'] ?>" onclick="return confirm('Supprimer cet asset ?')">&#10060;</a>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" action="?route=asset_add&id=<?= $client['id'] ?>" style="margin-top:0.5em;">
            <select name="asset_type" required>
                <option value="ip_range">Plage d'IP</option>
                <option value="ip">IP publique</option>
                <option value="fqdn">FQDN</option>
                <option value="domain">Nom de domaine</option>
            </select>
            <input type="text" name="value" required placeholder="ex: 192.168.1.1/24 ou pentwest.com">
            <button type="submit">Ajouter</button>
        </form>
    </div>

    <!-- Bouton lancer un scan Whatweb maintenant -->
    <form method="post" action="" class="whatweb-scan-btn">
        <button type="submit" name="launch_whatweb_now">Lancer un scan Whatweb maintenant</button>
    </form>

    <!-- Bouton lancer un scan Amass maintenant -->
    <form method="post" action="" class="amass-scan-btn">
        <button type="submit" name="launch_amass_now">Lancer un scan Amass maintenant (DEBUG, 150s max)</button>
    </form>
    <!-- Rafraîchir la sortie brute Amass -->
    <form method="get" action="" class="amass-scan-btn" style="display:inline;">
        <input type="hidden" name="route" value="client_details">
        <input type="hidden" name="id" value="<?= $client['id'] ?>">
        <button type="submit">Rafraîchir la sortie brute Amass</button>
    </form>
    <!-- Bouton pour supprimer tous les résultats Amass -->
    <form method="post" action="" onsubmit="return confirm('Supprimer tous les résultats Amass de ce client ?');" style="display:inline;">
        <button type="submit" name="delete_amass_results" style="background:#d33;color:white;">Supprimer tous les résultats Amass</button>
    </form>

    <?php if ($amass_debug_output !== null): ?>
        <div class="amass-debug-output">
            <b>Sortie brute Amass :</b><br>
            <?= nl2br(htmlspecialchars($amass_debug_output)) ?>
            <?php if (!empty($amass_debug_output) && empty($amass_scan_finished)): ?>
                <br><i>(Scan en cours ou incomplet...)</i>
            <?php elseif ($amass_scan_finished): ?>
                <br><i>(Scan terminé)</i>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Bouton supprimer le résultat whatweb du jour -->
    <form method="post" action="" onsubmit="return confirm('Supprimer tous les résultats Whatweb du jour ?');" style="display:inline;">
        <button type="submit" name="delete_whatweb_today" style="background:#e33;color:white;">Supprimer le résultat Whatweb d’aujourd’hui</button>
    </form>
    <?php if (!empty($whatweb_error)) : ?>
        <div style="color:red;"><?= htmlspecialchars($whatweb_error) ?></div>
    <?php endif; ?>

    <h2>
        Résultats Whatweb
        <span class="whatweb-info-btn" id="whatweb-global-info-btn" tabindex="0" title="Afficher/masquer la sortie brute pour tous les scans">i</span>
    </h2>
    <?php if ($selectedDay): ?>
        <h3>Résultats du <?= htmlspecialchars($selectedDay) ?></h3>
        <table border="1" cellpadding="4" cellspacing="0">
            <thead>
                <tr>
                    <th>Domaine</th>
                    <th>Technologie</th>
                    <th>Version</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $hasRow = false;
            foreach ($selectedDayResults as $result): ?>
                <?php
                $parsedRows = parseTechnoVersion($result['techno'], $result['version'], $blacklistTechnos);
                $first = true;
                foreach ($parsedRows as $row) {
                    $hasRow = true;
                    echo "<tr>";
                    if ($first) {
                        echo '<td>' . htmlspecialchars($result['domain_ip']) . '</td>';
                        $first = false;
                    } else {
                        echo '<td></td>';
                    }
                    echo '<td>' . htmlspecialchars($row[0]) . '</td>';
                    echo '<td>' . htmlspecialchars($row[1]) . '</td>';
                    echo "</tr>";
                }
                ?>
            <?php endforeach; ?>
            <?php if (!$hasRow): ?>
                <tr>
                    <td colspan="3" style="color:#888;">Aucune technologie détectée pour ce scan.<br>
                    <em>Consultez la sortie brute pour plus de détails.</em></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
        // Grouper les résultats du jour par scan (whatweb_id) pour n’afficher qu’une seule sortie brute par scan
        $dayByScan = [];
        foreach ($selectedDayResults as $result) {
            $wid = $result['whatweb_id'];
            if (!isset($dayByScan[$wid])) {
                $dayByScan[$wid] = [
                    'raw_output' => $result['raw_output'],
                ];
            }
        }
        foreach ($dayByScan as $wid => $scan) : ?>
            <div class="whatweb-raw-output" id="raw-day-<?= htmlspecialchars($wid) ?>">
                <?= nl2br(htmlspecialchars($scan['raw_output'])) ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <?php
        // Regroupe par scan id
        $scansById = [];
        foreach ($whatwebResults as $row) {
            $wid = $row['whatweb_id'];
            if (!isset($scansById[$wid])) {
                $scansById[$wid] = [
                    'domain'  => $row['domain_ip'],
                    'raw'     => $row['raw_output'],
                    'technos' => [],
                ];
            }
            $scansById[$wid]['technos'][] = [$row['techno'], $row['version']];
        }
        ?>
        <?php if (!empty($scansById)): ?>
            <table border="1" cellpadding="4" cellspacing="0">
                <thead>
                    <tr>
                        <th>Domaine</th>
                        <th>Technologie</th>
                        <th>Version</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($scansById as $scan): ?>
                    <?php
                    $first = true; $hasRow = false;
                    foreach ($scan['technos'] as $kv) {
                        $techno = $kv[0];
                        $version = $kv[1];
                        $parsedRows = parseTechnoVersion($techno, $version, $blacklistTechnos);
                        foreach ($parsedRows as $row) {
                            $hasRow = true;
                            echo "<tr>";
                            if ($first) {
                                echo '<td>' . htmlspecialchars($scan['domain']) . '</td>';
                                $first = false;
                            } else {
                                echo '<td></td>';
                            }
                            echo '<td>' . htmlspecialchars($row[0]) . '</td>';
                            echo '<td>' . htmlspecialchars($row[1]) . '</td>';
                            echo "</tr>";
                        }
                    }
                    if (!$hasRow) {
                        echo '<tr><td>' . htmlspecialchars($scan['domain']) . '</td><td colspan="2" style="color:#888;">Aucune technologie détectée</td></tr>';
                    }
                    ?>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php foreach ($scansById as $scanId => $scan): ?>
                <div class="whatweb-raw-output" id="raw-<?= $scanId ?>">
                    <?= nl2br(htmlspecialchars($scan['raw'])) ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <table border="1" cellpadding="4" cellspacing="0">
                <thead>
                    <tr>
                        <th>Domaine</th>
                        <th>Technologie</th>
                        <th>Version</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="3">Aucun résultat WhatWeb pour ce client.</td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</main>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var calendarResultsData = <?= json_encode($calendarResults) ?>;
    var selectedDay = <?= json_encode($selectedDay) ?>;
    var today = new Date();
    var calBox = document.getElementById('calendar-box');
    function renderCalendar(month, year) {
        var first = new Date(year, month, 1);
        var last = new Date(year, month + 1, 0);
        var firstDay = first.getDay() === 0 ? 7 : first.getDay();
        var daysInMonth = last.getDate();
        var prevMonthDays = firstDay - 1;
        var html = '';
        var monthNames = [
            "Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
            "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"
        ];
        html += '<div class="calendar-header">' +
            '<span class="calendar-arrow" id="calendar-prev">&lt;</span>' +
            monthNames[month] + " " + year +
            '<span class="calendar-arrow" id="calendar-next">&gt;</span></div>';
        html += '<table class="calendar-table"><thead><tr>';
        html += '<th>Lun</th><th>Mar</th><th>Mer</th><th>Jeu</th><th>Ven</th><th>Sam</th><th>Dim</th>';
        html += '</tr></thead><tbody><tr>';
        for (var i = 1; i <= prevMonthDays; ++i) html += '<td class="calendar-other-month"></td>';
        for (var d = 1; d <= daysInMonth; ++d) {
            var ymd = year + '-' + String(month + 1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            var cls = [];
            if (calendarResultsData[ymd]) cls.push('calendar-result');
            if (selectedDay === ymd) cls.push('calendar-selected');
            if (calendarResultsData[ymd] && today.getFullYear() === year && today.getMonth() === month && today.getDate() === d) cls.push('calendar-today');
            html += '<td class="' + cls.join(' ') + '" ' +
                (calendarResultsData[ymd] ?
                    'style="cursor:pointer;" onclick="window.location.href=\'?route=client_details&id=<?= $client['id'] ?>&calendar_day='+ymd+'\'"' :
                    '') + '>' + d + '</td>';
            if ((prevMonthDays + d) % 7 === 0) html += '</tr><tr>';
        }
        var totalCells = prevMonthDays + daysInMonth;
        var after = (7 - (totalCells % 7)) % 7;
        for (var i = 0; i < after; ++i) html += '<td class="calendar-other-month"></td>';
        html += '</tr></tbody></table>';
        calBox.innerHTML = html;
        document.getElementById('calendar-prev').onclick = function() {
            var m = month-1, y = year;
            if (m < 0) { m = 11; y--; }
            renderCalendar(m, y);
        };
        document.getElementById('calendar-next').onclick = function() {
            var m = month+1, y = year;
            if (m > 11) { m = 0; y++; }
            renderCalendar(m, y);
        };
    }
    var initialMonth = today.getMonth(), initialYear = today.getFullYear();
    if (selectedDay) {
        var sd = new Date(selectedDay);
        initialMonth = sd.getMonth();
        initialYear = sd.getFullYear();
    }
    renderCalendar(initialMonth, initialYear);

    var btn = document.getElementById('whatweb-global-info-btn');
    btn.addEventListener('click', function(e) {
        var all = document.querySelectorAll('.whatweb-raw-output');
        var oneActive = false;
        all.forEach(function(elt) {
            if (elt.classList.contains('active')) {
                oneActive = true;
            }
        });
        if (oneActive) {
            all.forEach(function(elt) { elt.classList.remove('active'); });
        } else {
            all.forEach(function(elt) { elt.classList.add('active'); });
        }
    });
    btn.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            btn.click();
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>
