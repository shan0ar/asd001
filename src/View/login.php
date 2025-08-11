<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Login | OSINTApp</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-container">
    <h1>Connexion</h1>
    <?php if (isset($error)): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" action="?route=auth">
        <label>Identifiant:</label>
        <input type="text" name="username" required autofocus>
        <label>Mot de passe:</label>
        <input type="password" name="password" required>
        <button type="submit">Connexion</button>
    </form>
</div>
</body>
</html>