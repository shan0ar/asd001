<?php
function sidebar($clients, $selectedId = null) {
?>
<aside class="sidebar">
    <div class="logo"><b>OSINT</b>App</div>
    <nav>
        <ul>
            <li><a href="?route=home"<?= !$selectedId ? ' class="active"' : '' ?>>Accueil</a></li>
            <li>
                <span class="menu-title">Clients</span>
                <input type="text" id="client-search" placeholder="Rechercher un client..." autocomplete="off">
                <button class="btn-green" id="btn-create-client" onclick="window.location='?route=client_create'">Créer un client</button>
                <ul id="client-list">
                <?php foreach ($clients as $c): ?>
                    <li>
                        <a href="?route=client_details&id=<?= $c['id'] ?>"<?= $selectedId == $c['id'] ? ' class="active"' : '' ?>><?= htmlspecialchars($c['name']) ?></a>
                    </li>
                <?php endforeach; ?>
                </ul>
            </li>
            <li><a href="?route=logout">Déconnexion</a></li>
        </ul>
    </nav>
</aside>
<?php
}
?>