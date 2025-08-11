<?php
require __DIR__ . '/layout.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Clients | OSINTApp</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js"></script>
</head>
<body>
<?= sidebar($clients) ?>
<main>
    <h1>Clients</h1>
    <a href="?route=client_create" class="btn-green">Créer un client</a>
    <ul>
        <?php foreach ($clients as $c): ?>
            <li><a href="?route=client_details&id=<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a></li>
        <?php endforeach; ?>
    </ul>
</main>
</body>
</html>