<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>

</head>
<body>

<nav class="bg-white border-b shadow-sm">

    <!-- Logo -->
    <div class="flex justify-center py-3">
        <img src="../../images/logo.jpeg" alt="Logo" class="h-10">
    </div>

    <!-- Menu -->
    <div class="flex justify-center">
        <div class="flex items-center gap-2 overflow-x-auto px-4 py-2 text-sm">

            <!-- Tableau de bord -->
            <a href="index.php"
               class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-100">
                🏠 <span>Tableau de bord</span>
            </a>

            <?php if ($role === 'admin'): ?>
                <a href="users.php"
                class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-100">
                👥 <span>Utilisateurs</span>
            </a>

            <a href="types.php"
               class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-100">
                🏢 <span>Gestion des types</span>
            </a>
            
            <a href="rapports.php"
                   class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-100">
                    📄 <span>Rapports</span>
            </a>
            <?php endif; ?>

            <?php if ($role === 'autorite'): ?>
                <a href="rapports.php"
                   class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-100">
                    📄 <span>Rapports</span>
                </a>
            <?php endif; ?>

            <?php if ($role === 'enregistreurEntreeCamion' || $role === 'enregistreurSortieCamion' || $role === 'agentBascule' || $role === 'enregistreurEntreeBateau' || $role === 'enregistreurSortieBateau'): ?>
                <a href="enregistrement.php"
                   class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-100">
                    📄 <span>Nouvel enregistrement</span>
                </a>

                <a href="historiques.php"
                   class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-100">
                    🕒 <span>Historiques & Rapports</span>
                </a>
            <?php endif; ?>
            
            <?php if ($role === 'agentEntrepot'): ?>
                <a href="chargement.php"
                   class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-100">
                    📄 <span>Chargement </span>
                </a>

                <a href="dechargement.php"
                   class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-100">
                    📄 <span>Déchargement </span>
                </a>

                <a href="historiques.php"
                   class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-100">
                    📄 <span>Historiques des opérations</span>
                </a>
            <?php endif; ?>

            <?php if ($role === 'agentDouane'): ?>
                <a href="enregistrement.php"
                   class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-100">
                    📄 <span>Nouvel enregistrement </span>
                </a>
                <a href="historiques.php"
                   class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-100">
                    📄 <span>Historiques & Rapports</span>
                </a>
            <?php endif; ?>

            <!-- Déconnexion -->
            <a href="../../logout.php"
               class="flex items-center gap-2 px-3 py-2 rounded bg-red-100 text-red-600 hover:bg-red-200">
                🚪 <span>Déconnexion</span>
            </a>

        </div>
    </div>

</nav>

