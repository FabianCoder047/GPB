<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier que l'utilisateur est admin
if ($role !== 'admin') {
    header('Location: ../../login.php');
    exit();
}

// Initialiser les variables
$message = '';
$error = '';
$users = [];
$editMode = false;
$editUserId = 0;

// Si on a cliqué sur un utilisateur pour éditer
if (isset($_GET['edit'])) {
    $editUserId = (int)$_GET['edit'];
    $editMode = true;
    
    // Récupérer les infos de l'utilisateur à éditer
    $stmt = $conn->prepare("SELECT nom, prenom, email, role FROM users WHERE idUser = ?");
    $stmt->bind_param("i", $editUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($userToEdit = $result->fetch_assoc()) {
        $_POST['nom'] = $userToEdit['nom'];
        $_POST['prenom'] = $userToEdit['prenom'];
        $_POST['email'] = $userToEdit['email'];
        $_POST['role'] = $userToEdit['role'];
    }
    $stmt->close();
}

// Traitement du formulaire d'ajout/mise à jour d'utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        
        // Validation
        if (empty($nom) || empty($prenom) || empty($email) || empty($role)) {
            $error = "Tous les champs sont obligatoires";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format d'email invalide";
        } else {
            // Vérifier si on est en mode édition
            if (isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
                $editUserId = (int)$_POST['edit_id'];
                
                // Vérifier si l'email existe déjà (sauf pour l'utilisateur en cours d'édition)
                $check_stmt = $conn->prepare("SELECT idUser FROM users WHERE email = ? AND idUser != ?");
                $check_stmt->bind_param("si", $email, $editUserId);
                $check_stmt->execute();
                $check_stmt->store_result();
                
                if ($check_stmt->num_rows > 0) {
                    $error = "Cet email est déjà utilisé";
                    $check_stmt->close();
                } else {
                    $check_stmt->close();
                    
                    // Mettre à jour l'utilisateur
                    $stmt = $conn->prepare("UPDATE users SET nom = ?, prenom = ?, email = ?, role = ? WHERE idUser = ?");
                    $stmt->bind_param("ssssi", $nom, $prenom, $email, $role, $editUserId);
                    
                    if ($stmt->execute()) {
                        $message = "Utilisateur mis à jour avec succès !";
                        $_POST = []; // Réinitialiser les champs
                        $editMode = false;
                    } else {
                        $error = "Erreur lors de la mise à jour de l'utilisateur : " . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                // Mode ajout
                // Générer le mot de passe (prenom.nom)
                $password = strtolower($prenom . '.' . $nom);
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Vérifier si l'email existe déjà
                $check_stmt = $conn->prepare("SELECT idUser FROM users WHERE email = ?");
                $check_stmt->bind_param("s", $email);
                $check_stmt->execute();
                $check_stmt->store_result();
                
                if ($check_stmt->num_rows > 0) {
                    $error = "Cet email est déjà utilisé";
                    $check_stmt->close();
                } else {
                    $check_stmt->close();
                    
                    // Insérer le nouvel utilisateur
                    $stmt = $conn->prepare("INSERT INTO users (nom, prenom, email, mdp, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $nom, $prenom, $email, $hashed_password, $role);
                    
                    if ($stmt->execute()) {
                        $message = "Utilisateur créé avec succès ! Le mot de passe est : $password";
                        $_POST = []; // Réinitialiser les champs
                    } else {
                        $error = "Erreur lors de la création de l'utilisateur : " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
    
    // Traitement de la suppression
    if (isset($_POST['delete_user'])) {
        $userId = (int)$_POST['user_id'];
        
        // Empêcher la suppression de soi-même
        if ($userId != $user['idUser']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE idUser = ?");
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                $message = "Utilisateur supprimé avec succès";
            } else {
                $error = "Erreur lors de la suppression : " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Vous ne pouvez pas supprimer votre propre compte";
        }
    }
}

// Récupérer la liste des utilisateurs
$result = $conn->query("SELECT idUser, nom, prenom, email, role FROM users");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
} else {
    $error = "Erreur lors du chargement des utilisateurs : " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html, body {
            height: 100%;
        }
        body {
            display: flex;
            flex-direction: column;
        }
        .clickable-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .clickable-row:hover {
            background-color: #f3f4f6;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>

    <div class="flex-1 overflow-hidden">
        <div class="container mx-auto px-4 py-4 h-full flex flex-col">
            <!-- Messages d'alerte -->
            <?php if (!empty($message)): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-3">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                    <span class="text-green-800 text-sm"><?php echo htmlspecialchars($message); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-3">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                    <span class="text-red-800 text-sm"><?php echo htmlspecialchars($error); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Bannière d'information -->
            <div class="bg-blue-50 border-l-4 border-blue-500 p-3 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Information :</strong> Le mot de passe par défaut est généré automatiquement sous le format <span class="font-mono bg-blue-100 px-1 py-0.5 rounded text-xs">prenom.nom</span>. Il est recommandé de changer ce mot de passe après la première connexion.
                            <?php if ($editMode): ?>
                            <span class="ml-2 bg-yellow-100 text-yellow-800 text-xs font-medium px-2 py-0.5 rounded">Mode édition activé</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Contenu principal -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 flex-1 min-h-0">
                <!-- Formulaire d'ajout/édition -->
                <div class="bg-white rounded-lg shadow-md p-4 flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <?php echo $editMode ? 'Modifier un utilisateur' : 'Ajouter un nouvel utilisateur'; ?>
                        </h2>
                        <?php if ($editMode): ?>
                        <a href="users.php" class="text-sm text-gray-600 hover:text-gray-800">
                            <i class="fas fa-times mr-1"></i> Annuler l'édition
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" action="">
                        <?php if ($editMode && $editUserId > 0): ?>
                        <input type="hidden" name="edit_id" value="<?php echo $editUserId; ?>">
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="nom" class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                                <input type="text" id="nom" name="nom" 
                                       value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                       required>
                            </div>
                            
                            <div>
                                <label for="prenom" class="block text-sm font-medium text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
                                <input type="text" id="prenom" name="prenom" 
                                       value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                       required>
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                       required>
                            </div>
                            
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Rôle <span class="text-red-500">*</span></label>
                                <select id="role" name="role" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                        required>
                                    <option value="">Sélectionner un rôle</option>
                                    <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                                    <option value="autorite" <?php echo ($_POST['role'] ?? '') === 'autorite' ? 'selected' : ''; ?>>Autorité</option>
                                    <option value="enregistreurEntreeCamion" <?php echo ($_POST['role'] ?? '') === 'enregistreurEntreeCamion' ? 'selected' : ''; ?>>Enregistreur Entrée Camion</option>
                                    <option value="enregistreurSortieCamion" <?php echo ($_POST['role'] ?? '') === 'enregistreurSortieCamion' ? 'selected' : ''; ?>>Enregistreur Sortie Camion</option>
                                    <option value="enregistreurEntreeBateau" <?php echo ($_POST['role'] ?? '') === 'enregistreurEntreeBateau' ? 'selected' : ''; ?>>Enregistreur Entrée Bateau</option>
                                    <option value="enregistreurSortieBateau" <?php echo ($_POST['role'] ?? '') === 'enregistreurSortieBateau' ? 'selected' : ''; ?>>Enregistreur Sortie Bateau</option>
                                    <option value="agentBascule" <?php echo ($_POST['role'] ?? '') === 'agentBascule' ? 'selected' : ''; ?>>Agent Bascule</option>
                                    <option value="agentEntrepot" <?php echo ($_POST['role'] ?? '') === 'agentEntrepot' ? 'selected' : ''; ?>>Agent Entrepôt</option>
                                    <option value="agentDouane" <?php echo ($_POST['role'] ?? '') === 'agentDouane' ? 'selected' : ''; ?>>Agent Douane</option>
                                </select>

                            </div>
                            
                            <div class="col-span-2 pt-2">
                                <button type="submit" name="add_user" 
                                        class="w-full <?php echo $editMode ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700'; ?> text-white font-medium py-2 px-4 rounded-lg transition duration-200 text-sm">
                                    <i class="fas <?php echo $editMode ? 'fa-user-edit' : 'fa-user-plus'; ?> mr-2"></i>
                                    <?php echo $editMode ? 'Mettre à jour' : 'Créer l\'utilisateur'; ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Liste des utilisateurs -->
                <div class="bg-white rounded-lg shadow-md p-4 flex flex-col min-h-0">
                    <div class="flex justify-between items-center mb-3">
                        <h2 class="text-lg font-semibold text-gray-800">Liste des utilisateurs</h2>
                        <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2 py-1 rounded-full">
                            <?php echo count($users); ?> utilisateur(s)
                        </span>
                    </div>
                    
                    <?php 
                    // Pagination
                    $usersPerPage = 2;
                    $totalUsers = count($users);
                    $totalPages = $totalUsers > 0 ? ceil($totalUsers / $usersPerPage) : 1;
                    
                    // Page courante
                    $currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
                    
                    // Calcul des indices
                    $startIndex = ($currentPage - 1) * $usersPerPage;
                    $endIndex = min($startIndex + $usersPerPage, $totalUsers);
                    
                    // Utilisateurs à afficher sur cette page
                    $usersToDisplay = array_slice($users, $startIndex, $usersPerPage);
                    ?>
                    
                    <?php if (empty($users)): ?>
                        <div class="text-center py-6 text-gray-500 flex-1 flex flex-col justify-center">
                            <i class="fas fa-users text-3xl mb-3 text-gray-300"></i>
                            <p class="text-sm">Aucun utilisateur n'a été créé pour le moment</p>
                        </div>
                    <?php else: ?>
                        <div class="flex-1 min-h-0 flex flex-col">
                            <!-- Tableau -->
                            <div class="overflow-y-auto flex-1 mb-3">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b border-gray-200 sticky top-0 bg-white">
                                            <th class="text-left py-2 px-2 text-xs font-medium text-gray-700">Nom & Prénom</th>
                                            <th class="text-left py-2 px-2 text-xs font-medium text-gray-700">Email</th>
                                            <th class="text-left py-2 px-2 text-xs font-medium text-gray-700">Rôle</th>
                                            <th class="text-left py-2 px-2 text-xs font-medium text-gray-700">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usersToDisplay as $userItem): ?>
                                        <tr class="border-b border-gray-100 hover:bg-gray-50 clickable-row" 
                                            onclick="window.location.href='?edit=<?php echo $userItem['idUser']; ?><?php echo isset($_GET['page']) ? '&page=' . $_GET['page'] : ''; ?>'">
                                            <td class="py-2 px-2">
                                                <div class="font-medium text-gray-800 text-sm">
                                                    <?php echo htmlspecialchars($userItem['prenom'] . ' ' . $userItem['nom']); ?>
                                                    <?php if ($userItem['idUser'] == ($user['idUser'] ?? 0)): ?>
                                                    <span class="ml-1 bg-blue-100 text-blue-800 text-xs font-medium px-1 py-0.5 rounded">Vous</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-2 px-2 text-gray-600 text-sm"><?php echo htmlspecialchars($userItem['email']); ?></td>
                                            <td class="py-2 px-2">
                                                <?php 
                                                $roleLabels = [
                                                    'admin' => 'Admin',
                                                    'autorite' => 'Autorité',
                                                    'enregistreurEntreeCamion' => 'Entrée Camion',
                                                    'enregistreurSortieCamion' => 'Sortie Camion',
                                                    'enregistreurEntreeBateau' => 'Entrée Bateau',
                                                    'enregistreurSortieBateau' => 'Sortie Bateau',
                                                    'agentBascule' => 'Agent Bascule',
                                                    'agentEntrepot' => 'Agent Entrepôt',
                                                    'agentDouane' => 'Agent Douane'
                                                ];
                                                $roleLabel = $roleLabels[$userItem['role']] ?? $userItem['role'];
                                                
                                                // Définir les couleurs pour chaque rôle
                                                $roleColors = [
                                                    'admin' => 'bg-purple-100 text-purple-800',
                                                    'autorite' => 'bg-yellow-100 text-yellow-800',
                                                    'enregistreurEntreeCamion' => 'bg-green-100 text-green-800',
                                                    'enregistreurSortieCamion' => 'bg-blue-100 text-blue-800',
                                                    'enregistreurEntreeBateau' => 'bg-green-100 text-green-800',
                                                    'enregistreurSortieBateau' => 'bg-blue-100 text-blue-800',
                                                    'agentBascule' => 'bg-indigo-100 text-indigo-800',
                                                    'agentEntrepot' => 'bg-pink-100 text-pink-800',
                                                    'agentDouane' => 'bg-red-100 text-red-800'
                                                ];
                                                $roleColor = $roleColors[$userItem['role']] ?? 'bg-gray-100 text-gray-800';
                                                ?>
                                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $roleColor; ?>">
                                                    <?php echo $roleLabel; ?>
                                                </span>
                                            </td>
                                            <td class="py-2 px-2" onclick="event.stopPropagation();">
                                                <?php if ($userItem['idUser'] != ($user['idUser'] ?? 0)): ?>
                                                <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $userItem['idUser']; ?>">
                                                    <button type="submit" name="delete_user" 
                                                            class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50 text-sm">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <span class="text-gray-400 text-xs">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                            <div class="mt-2 pt-3 border-t border-gray-200">
                                <div class="flex justify-between items-center">
                                    <div class="text-xs text-gray-500">
                                        Affichage <?php echo $startIndex + 1; ?>-<?php echo $endIndex; ?> sur <?php echo $totalUsers; ?>
                                    </div>
                                    <div class="flex space-x-1">
                                        <?php if ($currentPage > 1): ?>
                                            <a href="?page=<?php echo $currentPage - 1; ?><?php echo $editMode ? '&edit=' . $editUserId : ''; ?>" class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                                <i class="fas fa-chevron-left mr-1"></i>Précédent
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <?php if ($i == $currentPage): ?>
                                                <span class="px-2 py-1 text-xs bg-blue-600 text-white rounded"><?php echo $i; ?></span>
                                            <?php else: ?>
                                                <a href="?page=<?php echo $i; ?><?php echo $editMode ? '&edit=' . $editUserId : ''; ?>" class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200"><?php echo $i; ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <?php if ($currentPage < $totalPages): ?>
                                            <a href="?page=<?php echo $currentPage + 1; ?><?php echo $editMode ? '&edit=' . $editUserId : ''; ?>" class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                                Suivant<i class="fas fa-chevron-right ml-1"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Légende des rôles -->
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <h3 class="text-xs font-medium text-gray-700 mb-2">Légende des rôles :</h3>
                                <div class="grid grid-cols-3 gap-1">
                                    <span class="inline-flex items-center text-xs">
                                        <span class="w-2 h-2 bg-purple-500 rounded-full mr-1"></span>
                                        Admin
                                    </span>
                                    <span class="inline-flex items-center text-xs">
                                        <span class="w-2 h-2 bg-yellow-500 rounded-full mr-1"></span>
                                        Autorité
                                    </span>
                                    <span class="inline-flex items-center text-xs">
                                        <span class="w-2 h-2 bg-green-500 rounded-full mr-1"></span>
                                        Entrée Camion
                                    </span>
                                    <span class="inline-flex items-center text-xs">
                                        <span class="w-2 h-2 bg-blue-500 rounded-full mr-1"></span>
                                        Sortie Camion
                                    </span>
                                    <span class="inline-flex items-center text-xs">
                                        <span class="w-2 h-2 bg-green-500 rounded-full mr-1"></span>
                                        Entrée Bateau
                                    </span>
                                    <span class="inline-flex items-center text-xs">
                                        <span class="w-2 h-2 bg-blue-500 rounded-full mr-1"></span>
                                        Sortie Bateau
                                    </span>
                                    <span class="inline-flex items-center text-xs">
                                        <span class="w-2 h-2 bg-indigo-500 rounded-full mr-1"></span>
                                        Agent Bascule
                                    </span>
                                    <span class="inline-flex items-center text-xs">
                                        <span class="w-2 h-2 bg-pink-500 rounded-full mr-1"></span>
                                        Agent Entrepôt
                                    </span>
                                    <span class="inline-flex items-center text-xs">
                                        <span class="w-2 h-2 bg-red-500 rounded-full mr-1"></span>
                                        Agent Douane    
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-sélection du texte dans le message de succès
        document.addEventListener('DOMContentLoaded', function() {
            const successMessages = document.querySelectorAll('.bg-green-50');
            successMessages.forEach(message => {
                if (message.textContent.includes('Le mot de passe est :')) {
                    setTimeout(() => {
                        message.style.opacity = '0.9';
                        setTimeout(() => {
                            message.style.transition = 'opacity 0.5s';
                            message.style.opacity = '0';
                            setTimeout(() => message.remove(), 500);
                        }, 5000);
                    }, 3000);
                }
            });
            
            // Empêcher le clic sur les boutons de suppression de déclencher le clic sur la ligne
            const deleteButtons = document.querySelectorAll('form button[name="delete_user"]');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
        });
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('nav a');
    
    navLinks.forEach(link => {
        const linkPath = link.getAttribute('href');
        // Vérifier si le lien correspond à la page actuelle
        if (currentPath.includes(linkPath) && linkPath !== '../../logout.php') {
            link.classList.add('bg-blue-100', 'text-blue-600', 'font-semibold');
            link.classList.remove('hover:bg-gray-100');
        }
    });
});
</script>
</body>
</html>