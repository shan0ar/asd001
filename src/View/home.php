<?php
require __DIR__ . '/layout.php';
$clients = \App\Model\Client::all($config['db']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Accueil | OSINTApp</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js"></script>
</head>
<body>
<?= sidebar($clients) ?>
<main>
    <h1>Bienvenue</h1>
    <p>Sélectionnez un client à gauche pour voir les détails et les scans.</p>
</main>
</body>
</html>