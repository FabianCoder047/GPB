<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'enregistreurEntreeCamion') {
    header("Location: ../../login.php");
    exit();
}

// Initialisation des variables de filtres
$filters = [
    'etat' => $_GET['etat'] ?? '',
    'raison' => $_GET['raison'] ?? '',
    'date_debut' => $_GET['date_debut'] ?? '',
    'date_fin' => $_GET['date_fin'] ?? '',
    'immatriculation' => $_GET['immatriculation'] ?? '',
    'chauffeur' => $_GET['chauffeur'] ?? '',
    'port' => $_GET['port'] ?? ''
];

// Récupérer la liste des ports pour le filtre
$ports_list = [];
try {
    $result = $conn->query("SELECT * FROM port ORDER BY nom");
    $ports_list = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = "Erreur lors du chargement des ports: " . $e->getMessage();
}

// Construction de la requête SQL avec filtres
$where_conditions = [];
$params = [];
$types = '';

// Filtre par état
if (!empty($filters['etat'])) {
    $where_conditions[] = "ce.etat = ?";
    $params[] = $filters['etat'];
    $types .= 's';
}

// Filtre par raison
if (!empty($filters['raison'])) {
    $where_conditions[] = "ce.raison = ?";
    $params[] = $filters['raison'];
    $types .= 's';
}

// Filtre par période de date
if (!empty($filters['date_debut']) && !empty($filters['date_fin'])) {
    $where_conditions[] = "DATE(ce.date_entree) BETWEEN ? AND ?";
    $params[] = $filters['date_debut'];
    $params[] = $filters['date_fin'];
    $types .= 'ss';
} elseif (!empty($filters['date_debut'])) {
    $where_conditions[] = "DATE(ce.date_entree) >= ?";
    $params[] = $filters['date_debut'];
    $types .= 's';
} elseif (!empty($filters['date_fin'])) {
    $where_conditions[] = "DATE(ce.date_entree) <= ?";
    $params[] = $filters['date_fin'];
    $types .= 's';
}

// Filtre par immatriculation (recherche partielle)
if (!empty($filters['immatriculation'])) {
    $where_conditions[] = "ce.immatriculation LIKE ?";
    $params[] = '%' . $filters['immatriculation'] . '%';
    $types .= 's';
}

// Filtre par chauffeur (recherche partielle)
if (!empty($filters['chauffeur'])) {
    $where_conditions[] = "(ce.nom_chauffeur LIKE ? OR ce.prenom_chauffeur LIKE ?)";
    $params[] = '%' . $filters['chauffeur'] . '%';
    $params[] = '%' . $filters['chauffeur'] . '%';
    $types .= 'ss';
}

// Filtre par port
if (!empty($filters['port']) && $filters['port'] !== 'all') {
    $where_conditions[] = "ce.idPort = ?";
    $params[] = $filters['port'];
    $types .= 'i';
}

// Construction de la clause WHERE
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Pagination
$items_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

// Compter le nombre total de camions avec les filtres
$count_sql = "SELECT COUNT(*) as total 
              FROM camions_entrants ce
              LEFT JOIN port p ON ce.idPort = p.id
              $where_sql";

try {
    if (!empty($params)) {
        $stmt = $conn->prepare($count_sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $count_result = $stmt->get_result();
    } else {
        $count_result = $conn->query($count_sql);
    }
    
    $count_data = $count_result->fetch_assoc();
    $total_items = $count_data['total'];
    $total_pages = ceil($total_items / $items_per_page);
    
    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
    }
    
    $offset = ($current_page - 1) * $items_per_page;
    
    // Requête principale avec filtres et pagination
    $query = "SELECT ce.*, tc.nom as type_camion, p.nom as port 
              FROM camions_entrants ce
              LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
              LEFT JOIN port p ON ce.idPort = p.id
              $where_sql
              ORDER BY ce.date_entree DESC
              LIMIT ? OFFSET ?";
    
    $params[] = $items_per_page;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $conn->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $camions = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
    $camions = [];
    $total_items = 0;
    $total_pages = 1;
}

// Statistiques
$stats = [
    'total' => 0,
    'charge' => 0,
    'vide' => 0,
    'pesage' => 0,
    'chargement' => 0,
    'dechargement' => 0,
    'dechargement_chargement' => 0
];

try {
    // Statistique totale
    $stats_result = $conn->query("SELECT COUNT(*) as total FROM camions_entrants");
    $stats['total'] = $stats_result->fetch_assoc()['total'];
    
    // Par état
    $stats_result = $conn->query("SELECT etat, COUNT(*) as count FROM camions_entrants GROUP BY etat");
    while ($row = $stats_result->fetch_assoc()) {
        if ($row['etat'] == 'Chargé') {
            $stats['charge'] = $row['count'];
        } elseif ($row['etat'] == 'Vide') {
            $stats['vide'] = $row['count'];
        }
    }
    
    // Par raison
    $stats_result = $conn->query("SELECT raison, COUNT(*) as count FROM camions_entrants GROUP BY raison");
    while ($row = $stats_result->fetch_assoc()) {
        if ($row['raison'] == 'Pesage') {
            $stats['pesage'] = $row['count'];
        } elseif ($row['raison'] == 'Chargement') {
            $stats['chargement'] = $row['count'];
        } elseif ($row['raison'] == 'Déchargement') {
            $stats['dechargement'] = $row['count'];
        } elseif ($row['raison'] == 'Déchargement et chargement') {
            $stats['dechargement_chargement'] = $row['count'];
        }
    }
} catch (Exception $e) {
    $error_stats = "Erreur lors du chargement des statistiques";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique et Rapports - Camions Entrants</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        
        
        <!-- Formulaire de filtres -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-filter mr-2"></i>Filtres de Recherche
            </h2>
            
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Filtre par immatriculation -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="immatriculation">
                            Immatriculation
                        </label>
                        <input type="text" id="immatriculation" name="immatriculation"
                               value="<?php echo htmlspecialchars($filters['immatriculation']); ?>"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Rechercher par immatriculation">
                    </div>
                    
                    <!-- Filtre par chauffeur -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="chauffeur">
                            Chauffeur
                        </label>
                        <input type="text" id="chauffeur" name="chauffeur"
                               value="<?php echo htmlspecialchars($filters['chauffeur']); ?>"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Nom ou prénom du chauffeur">
                    </div>
                    
                    <!-- Filtre par état -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="etat">
                            État du Camion
                        </label>
                        <select id="etat" name="etat" 
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Tous les états</option>
                            <option value="Chargé" <?php echo $filters['etat'] == 'Chargé' ? 'selected' : ''; ?>>Chargé</option>
                            <option value="Vide" <?php echo $filters['etat'] == 'Vide' ? 'selected' : ''; ?>>Vide</option>
                        </select>
                    </div>
                    
                    <!-- Filtre par raison -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="raison">
                            Raison
                        </label>
                        <select id="raison" name="raison"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Toutes les raisons</option>
                            <option value="Pesage" <?php echo $filters['raison'] == 'Pesage' ? 'selected' : ''; ?>>Pesage</option>
                            <option value="Chargement" <?php echo $filters['raison'] == 'Chargement' ? 'selected' : ''; ?>>Chargement</option>
                            <option value="Déchargement" <?php echo $filters['raison'] == 'Déchargement' ? 'selected' : ''; ?>>Déchargement</option>
                            <option value="Déchargement et chargement" <?php echo $filters['raison'] == 'Déchargement et chargement' ? 'selected' : ''; ?>>Déchargement et chargement</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <!-- Filtre par port -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="port">
                            Provenance (Port)
                        </label>
                        <select id="port" name="port" 
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all">Tous les ports</option>
                            <?php foreach ($ports_list as $port): ?>
                                <option value="<?php echo $port['id']; ?>"
                                    <?php echo $filters['port'] == $port['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($port['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Filtre par date de début -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="date_debut">
                            Date de début
                        </label>
                        <input type="date" id="date_debut" name="date_debut"
                               value="<?php echo htmlspecialchars($filters['date_debut']); ?>"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Filtre par date de fin -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="date_fin">
                            Date de fin
                        </label>
                        <input type="date" id="date_fin" name="date_fin"
                               value="<?php echo htmlspecialchars($filters['date_fin']); ?>"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="flex items-end">
                        <div class="flex space-x-2 w-full">
                            <button type="submit" 
                                    class="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                                <i class="fas fa-search mr-2"></i>Filtrer
                            </button>
                            <a href="historiques.php" 
                               class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline text-center">
                                <i class="fas fa-redo mr-2"></i>Réinitialiser
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($where_conditions)): ?>
                    <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            Filtres actifs : 
                            <?php 
                            $active_filters = [];
                            if (!empty($filters['etat'])) $active_filters[] = "État : " . $filters['etat'];
                            if (!empty($filters['raison'])) $active_filters[] = "Raison : " . $filters['raison'];
                            if (!empty($filters['date_debut'])) $active_filters[] = "À partir du : " . $filters['date_debut'];
                            if (!empty($filters['date_fin'])) $active_filters[] = "Jusqu'au : " . $filters['date_fin'];
                            if (!empty($filters['immatriculation'])) $active_filters[] = "Immatriculation contenant : " . $filters['immatriculation'];
                            if (!empty($filters['chauffeur'])) $active_filters[] = "Chauffeur contenant : " . $filters['chauffeur'];
                            if (!empty($filters['port']) && $filters['port'] !== 'all') {
                                foreach ($ports_list as $port) {
                                    if ($port['id'] == $filters['port']) {
                                        $active_filters[] = "Port : " . $port['nom'];
                                        break;
                                    }
                                }
                            }
                            echo implode(' | ', $active_filters);
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Résultats -->
        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-list mr-2"></i>Résultats des Recherches
                </h2>
                
                <div class="flex items-center space-x-4">
                    
                    
                    <!-- Statistiques des résultats -->
                    <div class="text-sm text-gray-600 border-l border-gray-300 pl-4">
                        <?php if ($total_items > 0): ?>
                            Résultats : <span class="font-bold"><?php echo ($current_page - 1) * $items_per_page + 1; ?>-<?php echo min($current_page * $items_per_page, $total_items); ?></span> sur <span class="font-bold"><?php echo $total_items; ?></span> camions
                        <?php else: ?>
                            <span class="text-gray-500 italic">Aucun résultat trouvé</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Entrée</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marque</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chauffeur</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tel</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">État</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Raison</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poids</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provenance</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agence</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIF</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destinataire</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($camions as $camion): ?>
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('d/m/Y H:i', strtotime($camion['date_entree'])); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($camion['immatriculation']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($camion['marque'] ?? '-'); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($camion['type_camion'] ?? '-'); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars(($camion['prenom_chauffeur'] ?? '') . ' ' . ($camion['nom_chauffeur'] ?? '')); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($camion['telephone_chauffeur'] ?? '-'); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $camion['etat'] == 'Chargé' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo htmlspecialchars($camion['etat'] ?? '-'); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($camion['raison'] ?? '-'); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $camion['poids'] ? number_format($camion['poids'], 2) . ' kg' : '-'; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($camion['port'] ?? '-'); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($camion['agence'] ?? '-'); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($camion['nif'] ?? '-'); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($camion['destinataire'] ?? '-'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($camions)): ?>
                        <tr>
                            <td colspan="13" class="px-4 py-4 text-center text-sm text-gray-500">
                                <i class="fas fa-search mr-2"></i>Aucun camion ne correspond à vos critères de recherche
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex items-center justify-between border-t border-gray-200 px-4 py-3 mt-4">
                <div class="flex flex-1 justify-between sm:hidden">
                    <?php if ($current_page > 1): ?>
                    <a href="?<?php 
                        $query_params = $_GET;
                        $query_params['page'] = $current_page - 1;
                        echo http_build_query($query_params);
                    ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Précédent
                    </a>
                    <?php endif; ?>
                    <?php if ($current_page < $total_pages): ?>
                    <a href="?<?php 
                        $query_params = $_GET;
                        $query_params['page'] = $current_page + 1;
                        echo http_build_query($query_params);
                    ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Suivant
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Page <span class="font-medium"><?php echo $current_page; ?></span> sur <span class="font-medium"><?php echo $total_pages; ?></span>
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <?php if ($current_page > 1): ?>
                            <a href="?<?php 
                                $query_params = $_GET;
                                $query_params['page'] = $current_page - 1;
                                echo http_build_query($query_params);
                            ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                <span class="sr-only">Précédent</span>
                                <i class="fas fa-chevron-left h-5 w-5"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php
                            // Afficher les numéros de page
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $start_page + 4);
                            
                            if ($end_page - $start_page < 4) {
                                $start_page = max(1, $end_page - 4);
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <a href="?<?php 
                                $query_params = $_GET;
                                $query_params['page'] = $i;
                                echo http_build_query($query_params);
                            ?>" 
                               class="relative inline-flex items-center px-4 py-2 text-sm font-semibold 
                                      <?php echo $i == $current_page ? 'bg-blue-600 text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                            <a href="?<?php 
                                $query_params = $_GET;
                                $query_params['page'] = $current_page + 1;
                                echo http_build_query($query_params);
                            ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                <span class="sr-only">Suivant</span>
                                <i class="fas fa-chevron-right h-5 w-5"></i>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
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