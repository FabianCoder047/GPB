<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier que l'utilisateur est admin
if ($role !== 'admin') {
    header('Location: index.php');
    exit();
}

// Initialiser les variables
$message = '';
$error = '';
$editMode = false;
$editId = 0;

// Définir les types disponibles
$tabs = [
    'marchandises' => [
        'table' => 'type_marchandise',
        'columns' => ['nom'],
        'singular' => 'type de marchandise',
        'plural' => 'types de marchandises'
    ],
    'camions' => [
        'table' => 'type_camion', 
        'columns' => ['nom'],
        'singular' => 'type de camion',
        'plural' => 'types de camions'
    ],
    'bateaux' => [
        'table' => 'type_bateau',
        'columns' => ['nom'],
        'singular' => 'type de bateau', 
        'plural' => 'types de bateaux'
    ],
    'ports' => [
        'table' => 'port',
        'columns' => ['nom', 'pays'],
        'singular' => 'port',
        'plural' => 'ports'
    ]
];

// Onglet actif par défaut
$activeTab = $_GET['tab'] ?? 'marchandises';
if (!array_key_exists($activeTab, $tabs)) {
    $activeTab = 'marchandises';
}

$currentType = $tabs[$activeTab];

// Si on a cliqué sur un élément pour éditer
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editMode = true;
    
    // Récupérer les infos de l'élément à éditer
    $stmt = $conn->prepare("SELECT * FROM {$currentType['table']} WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($itemToEdit = $result->fetch_assoc()) {
        foreach ($currentType['columns'] as $col) {
            $_POST[$col] = $itemToEdit[$col] ?? '';
        }
    }
    $stmt->close();
}

// Traitement du formulaire d'ajout/mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $allFieldsFilled = true;
        $fieldValues = [];
        
        // Vérifier que tous les champs sont remplis
        foreach ($currentType['columns'] as $col) {
            $value = trim($_POST[$col] ?? '');
            if (empty($value)) {
                $allFieldsFilled = false;
                break;
            }
            $fieldValues[$col] = $value;
        }
        
        if (!$allFieldsFilled) {
            $error = "Tous les champs sont obligatoires";
        } else {
            // Vérifier si on est en mode édition
            if (isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
                $editId = (int)$_POST['edit_id'];
                
                // Vérifier les doublons (uniquement pour les champs uniques)
                // Pour les ports, vérifier la combinaison nom+pays
                if ($activeTab === 'ports') {
                    $checkQuery = "SELECT id FROM {$currentType['table']} WHERE nom = ? AND pays = ? AND id != ?";
                    $check_stmt = $conn->prepare($checkQuery);
                    $check_stmt->bind_param("ssi", $fieldValues['nom'], $fieldValues['pays'], $editId);
                } else {
                    $checkQuery = "SELECT id FROM {$currentType['table']} WHERE nom = ? AND id != ?";
                    $check_stmt = $conn->prepare($checkQuery);
                    $check_stmt->bind_param("si", $fieldValues['nom'], $editId);
                }
                
                $check_stmt->execute();
                $check_stmt->store_result();
                
                if ($check_stmt->num_rows > 0) {
                    $error = $activeTab === 'ports' ? "Ce port existe déjà avec ce pays" : "Ce nom existe déjà";
                    $check_stmt->close();
                } else {
                    $check_stmt->close();
                    
                    // Construire la requête UPDATE dynamiquement
                    $setClause = implode(' = ?, ', $currentType['columns']) . ' = ?';
                    $params = array_merge(array_values($fieldValues), [$editId]);
                    $types = str_repeat('s', count($currentType['columns'])) . 'i';
                    
                    $stmt = $conn->prepare("UPDATE {$currentType['table']} SET {$setClause} WHERE id = ?");
                    $stmt->bind_param($types, ...$params);
                    
                    if ($stmt->execute()) {
                        $message = ucfirst($currentType['singular']) . " mis à jour avec succès !";
                        $_POST = [];
                        $editMode = false;
                    } else {
                        $error = "Erreur lors de la mise à jour : " . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                // Mode ajout
                // Vérifier les doublons
                if ($activeTab === 'ports') {
                    $checkQuery = "SELECT id FROM {$currentType['table']} WHERE nom = ? AND pays = ?";
                    $check_stmt = $conn->prepare($checkQuery);
                    $check_stmt->bind_param("ss", $fieldValues['nom'], $fieldValues['pays']);
                } else {
                    $checkQuery = "SELECT id FROM {$currentType['table']} WHERE nom = ?";
                    $check_stmt = $conn->prepare($checkQuery);
                    $check_stmt->bind_param("s", $fieldValues['nom']);
                }
                
                $check_stmt->execute();
                $check_stmt->store_result();
                
                if ($check_stmt->num_rows > 0) {
                    $error = $activeTab === 'ports' ? "Ce port existe déjà avec ce pays" : "Ce nom existe déjà";
                    $check_stmt->close();
                } else {
                    $check_stmt->close();
                    
                    // Construire la requête INSERT dynamiquement
                    $columns = implode(', ', $currentType['columns']);
                    $placeholders = str_repeat('?, ', count($currentType['columns']) - 1) . '?';
                    $types = str_repeat('s', count($currentType['columns']));
                    
                    $stmt = $conn->prepare("INSERT INTO {$currentType['table']} ({$columns}) VALUES ({$placeholders})");
                    $stmt->bind_param($types, ...array_values($fieldValues));
                    
                    if ($stmt->execute()) {
                        $message = ucfirst($currentType['singular']) . " créé avec succès !";
                        $_POST = [];
                    } else {
                        $error = "Erreur lors de la création : " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
    
    // Traitement de la suppression
    if (isset($_POST['delete_item'])) {
        $itemId = (int)$_POST['item_id'];
        
        $stmt = $conn->prepare("DELETE FROM {$currentType['table']} WHERE id = ?");
        $stmt->bind_param("i", $itemId);
        
        if ($stmt->execute()) {
            $message = ucfirst($currentType['singular']) . " supprimé avec succès";
        } else {
            $error = "Erreur lors de la suppression : " . $stmt->error;
        }
        $stmt->close();
    }
}

// Récupérer la liste des éléments pour l'onglet actif
$items = [];
$query = "SELECT * FROM {$currentType['table']} ORDER BY id DESC";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $result->free();
} else {
    $error = "Erreur lors du chargement des données : " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Types</title>
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
        .tab-active {
            border-bottom: 3px solid #3b82f6;
            color: #3b82f6;
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

            <!-- Onglets -->
            <div class="border-b border-gray-200 mb-6">
                <div class="flex space-x-1 overflow-x-auto">
                    <?php foreach ($tabs as $tabKey => $tabInfo): ?>
                    <a href="?tab=<?php echo $tabKey; ?><?php echo isset($_GET['page']) ? '&page=' . $_GET['page'] : ''; ?><?php echo $editMode ? '&edit=' . $editId : ''; ?>"
                       class="px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors duration-200
                              <?php echo $activeTab === $tabKey ? 'tab-active text-blue-600' : 'text-gray-500 hover:text-gray-700'; ?>">
                        <i class="fas 
                            <?php echo $tabKey === 'marchandises' ? 'fa-box' : 
                                   ($tabKey === 'camions' ? 'fa-truck' : 
                                   ($tabKey === 'bateaux' ? 'fa-ship' : 'fa-anchor')); ?> 
                            mr-2"></i>
                        <?php echo ucfirst($tabInfo['plural']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Contenu principal -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 flex-1 min-h-0">
                <!-- Formulaire d'ajout/édition -->
                <div class="bg-white rounded-lg shadow-md p-4 flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <?php echo $editMode ? 'Modifier un ' . $currentType['singular'] : 'Ajouter un ' . $currentType['singular']; ?>
                        </h2>
                        <?php if ($editMode): ?>
                        <a href="?tab=<?php echo $activeTab; ?>" class="text-sm text-gray-600 hover:text-gray-800">
                            <i class="fas fa-times mr-1"></i> Annuler
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" action="">
                        <?php if ($editMode && $editId > 0): ?>
                        <input type="hidden" name="edit_id" value="<?php echo $editId; ?>">
                        <?php endif; ?>
                        
                        <div class="space-y-4">
                            <?php foreach ($currentType['columns'] as $index => $column): ?>
                            <div>
                                <label for="<?php echo $column; ?>" class="block text-sm font-medium text-gray-700 mb-1">
                                    <?php 
                                    $labels = [
                                        'nom' => 'Nom',
                                        'pays' => 'Pays'
                                    ];
                                    echo $labels[$column] ?? ucfirst($column);
                                    ?> 
                                    <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="<?php echo $column; ?>" name="<?php echo $column; ?>" 
                                       value="<?php echo htmlspecialchars($_POST[$column] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                       placeholder="<?php echo $column === 'pays' ? 'Entrez le nom du pays' : 'Entrez le nom'; ?>"
                                       required>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="pt-2">
                                <button type="submit" name="add_item" 
                                        class="w-full <?php echo $editMode ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700'; ?> text-white font-medium py-2 px-4 rounded-lg transition duration-200 text-sm">
                                    <i class="fas <?php echo $editMode ? 'fa-edit' : 'fa-plus'; ?> mr-2"></i>
                                    <?php echo $editMode ? 'Mettre à jour' : 'Ajouter'; ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Liste des éléments -->
                <div class="bg-white rounded-lg shadow-md p-4 flex flex-col min-h-0">
                    <div class="flex justify-between items-center mb-3">
                        <h2 class="text-lg font-semibold text-gray-800">Liste des <?php echo $currentType['plural']; ?></h2>
                        <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2 py-1 rounded-full">
                            <?php echo count($items); ?> <?php echo count($items) <= 1 ? $currentType['singular'] : $currentType['plural']; ?>
                        </span>
                    </div>
                    
                    <?php 
                    // Pagination
                    $itemsPerPage = 2;
                    $totalItems = count($items);
                    $totalPages = $totalItems > 0 ? ceil($totalItems / $itemsPerPage) : 1;
                    
                    // Page courante
                    $currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
                    
                    // Calcul des indices
                    $startIndex = ($currentPage - 1) * $itemsPerPage;
                    $endIndex = min($startIndex + $itemsPerPage, $totalItems);
                    
                    // Éléments à afficher sur cette page
                    $itemsToDisplay = array_slice($items, $startIndex, $itemsPerPage);
                    ?>
                    
                    <?php if (empty($items)): ?>
                        <div class="text-center py-6 text-gray-500 flex-1 flex flex-col justify-center">
                            <i class="fas 
                                <?php echo $activeTab === 'marchandises' ? 'fa-box' : 
                                       ($activeTab === 'camions' ? 'fa-truck' : 
                                       ($activeTab === 'bateaux' ? 'fa-ship' : 'fa-anchor')); ?> 
                                text-3xl mb-3 text-gray-300"></i>
                            <p class="text-sm">Aucun <?php echo $currentType['singular']; ?> n'a été créé pour le moment</p>
                        </div>
                    <?php else: ?>
                        <div class="flex-1 min-h-0 flex flex-col">
                            <!-- Tableau -->
                            <div class="overflow-y-auto flex-1 mb-3">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b border-gray-200 sticky top-0 bg-white">
                                            <th class="text-left py-2 px-2 text-xs font-medium text-gray-700">Nom</th>
                                            <?php if ($activeTab === 'ports'): ?>
                                            <th class="text-left py-2 px-2 text-xs font-medium text-gray-700">Pays</th>
                                            <?php endif; ?>
                                            <th class="text-left py-2 px-2 text-xs font-medium text-gray-700">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($itemsToDisplay as $item): ?>
                                        <tr class="border-b border-gray-100 hover:bg-gray-50 clickable-row" 
                                            onclick="window.location.href='?tab=<?php echo $activeTab; ?>&edit=<?php echo $item['id']; ?><?php echo isset($_GET['page']) ? '&page=' . $_GET['page'] : ''; ?>'">
                                            <td class="py-2 px-2">
                                                <div class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($item['nom']); ?></div>
                                            </td>
                                            <?php if ($activeTab === 'ports'): ?>
                                            <td class="py-2 px-2">
                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                                    <i class="fas fa-flag mr-1"></i>
                                                    <?php echo htmlspecialchars($item['pays']); ?>
                                                </span>
                                            </td>
                                            <?php endif; ?>
                                            <td class="py-2 px-2" onclick="event.stopPropagation();">
                                                <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet élément ?');">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" name="delete_item" 
                                                            class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50 text-sm">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
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
                                        Affichage <?php echo $startIndex + 1; ?>-<?php echo $endIndex; ?> sur <?php echo $totalItems; ?>
                                    </div>
                                    <div class="flex space-x-1">
                                        <?php if ($currentPage > 1): ?>
                                            <a href="?tab=<?php echo $activeTab; ?>&page=<?php echo $currentPage - 1; ?><?php echo $editMode ? '&edit=' . $editId : ''; ?>" class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                                <i class="fas fa-chevron-left mr-1"></i>Précédent
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <?php if ($i == $currentPage): ?>
                                                <span class="px-2 py-1 text-xs bg-blue-600 text-white rounded"><?php echo $i; ?></span>
                                            <?php else: ?>
                                                <a href="?tab=<?php echo $activeTab; ?>&page=<?php echo $i; ?><?php echo $editMode ? '&edit=' . $editId : ''; ?>" class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200"><?php echo $i; ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <?php if ($currentPage < $totalPages): ?>
                                            <a href="?tab=<?php echo $activeTab; ?>&page=<?php echo $currentPage + 1; ?><?php echo $editMode ? '&edit=' . $editId : ''; ?>" class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                                Suivant<i class="fas fa-chevron-right ml-1"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Information sur l'onglet actif -->
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 text-blue-400">
                                        <i class="fas 
                                            <?php echo $activeTab === 'marchandises' ? 'fa-box' : 
                                                   ($activeTab === 'camions' ? 'fa-truck' : 
                                                   ($activeTab === 'bateaux' ? 'fa-ship' : 'fa-anchor')); ?> 
                                            text-lg"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-xs text-gray-700">
                                            <strong>Information :</strong> 
                                            <?php if ($activeTab === 'ports'): ?>
                                            Les ports sont utilisés pour localiser les opérations maritimes. Pour les ports, la combinaison "Nom + Pays" doit être unique.
                                            <?php elseif ($activeTab === 'marchandises'): ?>
                                            Les types de marchandises définissent la nature des produits transportés. Chaque nom doit être unique.
                                            <?php elseif ($activeTab === 'camions'): ?>
                                            Les types de camions spécifient les caractéristiques des véhicules terrestres. Chaque nom doit être unique.
                                            <?php else: ?>
                                            Les types de bateaux définissent les caractéristiques des navires maritimes. Chaque nom doit être unique.
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Empêcher le clic sur les boutons de suppression de déclencher le clic sur la ligne
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('form button[name="delete_item"]');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
            
            // Auto-dismiss des messages de succès
            const successMessages = document.querySelectorAll('.bg-green-50');
            successMessages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0.9';
                    setTimeout(() => {
                        message.style.transition = 'opacity 0.5s';
                        message.style.opacity = '0';
                        setTimeout(() => message.remove(), 500);
                    }, 3000);
                }, 2000);
            });
        });
    </script>
</body>
</html>