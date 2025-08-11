<?php
require __DIR__ . '/layout.php';
$clients = \App\Model\Client::all($config['db']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Créer un client | OSINTApp</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js"></script>
</head>
<body>
<?= sidebar($clients) ?>
<main>
    <h1>Créer un client</h1>
    <form method="post" id="form-create-client">
        <label>Nom du client <span class="required">*</span></label>
        <input type="text" name="name" required>
        <label>Description <span class="required">*</span></label>
        <textarea name="description" required placeholder="Type de commerce, ex: pharmacie, magasin de moto..."></textarea>
        <fieldset>
            <legend>Assets du client</legend>
            <div id="assets-fields">
                <div class="asset-row">
                    <select name="assets[0][type]">
                        <option value="ip_range">Plage d'IP</option>
                        <option value="ip">IP publique</option>
                        <option value="fqdn">FQDN</option>
                        <option value="domain">Nom de domaine</option>
                    </select>
                    <input type="text" name="assets[0][value]" placeholder="ex: 192.168.1.1/24 ou pentwest.com" required>
                    <button type="button" onclick="addAssetField()">+</button>
                </div>
            </div>
        </fieldset>
        <label>Type (optionnel)</label>
        <input type="text" name="type" placeholder="Ex: pharmacie, moto...">
        <button type="submit" class="btn-green">Créer</button>
    </form>
</main>
<script>
let assetCount = 1;
function addAssetField() {
    const assets = document.getElementById('assets-fields');
    const idx = assetCount++;
    const row = document.createElement('div');
    row.className = 'asset-row';
    row.innerHTML = `
        <select name="assets[${idx}][type]">
            <option value="ip_range">Plage d'IP</option>
            <option value="ip">IP publique</option>
            <option value="fqdn">FQDN</option>
            <option value="domain">Nom de domaine</option>
        </select>
        <input type="text" name="assets[${idx}][value]" required>
        <button type="button" onclick="this.parentNode.remove()">-</button>
    `;
    assets.appendChild(row);
}
</script>
</body>
</html>