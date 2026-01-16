<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'enregistreurSortieCamion') {
    header("Location: ../../login.php");
    exit();
}

// Initialisation des variables de filtres
$filters = [
    'type_sortie' => $_GET['type_sortie'] ?? '',
    'date_debut' => $_GET['date_debut'] ?? '',
    'date_fin' => $_GET['date_fin'] ?? '',
    'immatriculation' => $_GET['immatriculation'] ?? '',
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

// Filtre par type de sortie
if (!empty($filters['type_sortie'])) {
    $where_conditions[] = "cs.type_sortie = ?";
    $params[] = $filters['type_sortie'];
    $types .= 's';
}

// Filtre par période de date
if (!empty($filters['date_debut']) && !empty($filters['date_fin'])) {
    $where_conditions[] = "DATE(cs.date_sortie) BETWEEN ? AND ?";
    $params[] = $filters['date_debut'];
    $params[] = $filters['date_fin'];
    $types .= 'ss';
} elseif (!empty($filters['date_debut'])) {
    $where_conditions[] = "DATE(cs.date_sortie) >= ?";
    $params[] = $filters['date_debut'];
    $types .= 's';
} elseif (!empty($filters['date_fin'])) {
    $where_conditions[] = "DATE(cs.date_sortie) <= ?";
    $params[] = $filters['date_fin'];
    $types .= 's';
}

// Filtre par immatriculation (recherche partielle)
if (!empty($filters['immatriculation'])) {
    $where_conditions[] = "ce.immatriculation LIKE ?";
    $params[] = '%' . $filters['immatriculation'] . '%';
    $types .= 's';
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

// Compter le nombre total de sorties avec les filtres
$count_sql = "SELECT COUNT(*) as total 
              FROM camions_sortants cs
              LEFT JOIN camions_entrants ce ON cs.idEntree = ce.idEntree
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
    $query = "SELECT cs.idSortie, cs.idEntree, cs.idChargement, cs.idDechargement, 
                     cs.date_sortie, cs.type_sortie,
                     ce.immatriculation, ce.etat, ce.date_entree, ce.idPort, 
                     ce.poids, ce.idTypeCamion,
                     tc.nom as type_camion, p.nom as port,
                     COALESCE(SUM(mpc.poids), 0) as poids_total_marchandises
              FROM camions_sortants cs
              LEFT JOIN camions_entrants ce ON cs.idEntree = ce.idEntree
              LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
              LEFT JOIN port p ON ce.idPort = p.id
              LEFT JOIN pesage_chargement_camion pcc ON cs.idChargement = pcc.idChargement
              LEFT JOIN marchandises_pesage_camion mpc ON pcc.idPesageChargement = mpc.idPesageChargement
              $where_sql
              GROUP BY cs.idSortie, cs.idEntree, cs.idChargement, cs.idDechargement, 
                       cs.date_sortie, cs.type_sortie,
                       ce.immatriculation, ce.etat, ce.date_entree, ce.idPort, 
                       ce.poids, ce.idTypeCamion,
                       tc.nom, p.nom
              ORDER BY cs.date_sortie DESC
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
    $sorties = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
    $sorties = [];
    $total_items = 0;
    $total_pages = 1;
}

// Statistiques
$stats = [
    'total' => 0,
    'charge' => 0,
    'decharge' => 0
];

try {
    // Statistique totale
    $stats_result = $conn->query("SELECT COUNT(*) as total FROM camions_sortants");
    $stats['total'] = $stats_result->fetch_assoc()['total'];
    
    // Par type de sortie
    $stats_result = $conn->query("SELECT type_sortie, COUNT(*) as count FROM camions_sortants GROUP BY type_sortie");
    while ($row = $stats_result->fetch_assoc()) {
        if ($row['type_sortie'] == 'charge') {
            $stats['charge'] = $row['count'];
        } elseif ($row['type_sortie'] == 'decharge') {
            $stats['decharge'] = $row['count'];
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
    <title>Historique et Rapports - Sorties Camions</title>
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
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                    <!-- Filtre par immatriculation -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="immatriculation">
                            Immatriculation
                        </label>
                        <input type="text" id="immatriculation" name="immatriculation"
                               value="<?php echo htmlspecialchars($filters['immatriculation']); ?>"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Rechercher...">
                    </div>
                    
                    <!-- Filtre par type de sortie -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="type_sortie">
                            Type de Sortie
                        </label>
                        <select id="type_sortie" name="type_sortie" 
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Tous les types</option>
                            <option value="charge" <?php echo $filters['type_sortie'] == 'charge' ? 'selected' : ''; ?>>Camion Chargé</option>
                            <option value="decharge" <?php echo $filters['type_sortie'] == 'decharge' ? 'selected' : ''; ?>>Camion Déchargé</option>
                        </select>
                    </div>
                    
                    <!-- Filtre par port -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="port">
                            Port
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
                            Date début
                        </label>
                        <input type="date" id="date_debut" name="date_debut"
                               value="<?php echo htmlspecialchars($filters['date_debut']); ?>"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Filtre par date de fin -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="date_fin">
                            Date fin
                        </label>
                        <input type="date" id="date_fin" name="date_fin"
                               value="<?php echo htmlspecialchars($filters['date_fin']); ?>"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="flex items-end gap-2">
                        <button type="submit" 
                                class="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline whitespace-nowrap">
                            <i class="fas fa-search mr-2"></i>Filtrer
                        </button>
                        <a href="historiques.php" 
                           class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline text-center whitespace-nowrap">
                            <i class="fas fa-redo mr-2"></i>
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($where_conditions)): ?>
                    <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            Filtres actifs : 
                            <?php 
                            $active_filters = [];
                            if (!empty($filters['type_sortie'])) $active_filters[] = "Type : " . ($filters['type_sortie'] == 'charge' ? 'Chargé' : 'Déchargé');
                            if (!empty($filters['date_debut'])) $active_filters[] = "À partir du : " . $filters['date_debut'];
                            if (!empty($filters['date_fin'])) $active_filters[] = "Jusqu'au : " . $filters['date_fin'];
                            if (!empty($filters['immatriculation'])) $active_filters[] = "Immatriculation contenant : " . $filters['immatriculation'];
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
                    <h2 class="text-sm font-bold text-gray-600">Exportez selon les filtres</h2>
                    <!-- Boutons d'export -->
                    <div class="flex space-x-2">
                        <a href="export_pdf.php?<?php echo http_build_query($_GET); ?>" 
                           class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-3 rounded-lg focus:outline-none focus:shadow-outline text-sm">
                            <i class="fas fa-file-pdf mr-1"></i>PDF
                        </a>
                        <a href="export_excel.php?<?php echo http_build_query($_GET); ?>" 
                           class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-3 rounded-lg focus:outline-none focus:shadow-outline text-sm">
                            <i class="fas fa-file-excel mr-1"></i>Excel
                        </a>
                    </div>
                    
                    <!-- Statistiques des résultats -->
                    <div class="text-sm text-gray-600 border-l border-gray-300 pl-4">
                        <?php if ($total_items > 0): ?>
                            Résultats : <span class="font-bold"><?php echo ($current_page - 1) * $items_per_page + 1; ?>-<?php echo min($current_page * $items_per_page, $total_items); ?></span> sur <span class="font-bold"><?php echo $total_items; ?></span> sorties
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Sortie</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type Camion</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Port</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Entrée</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type Sortie</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($sorties as $sortie): ?>
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?php echo !empty($sortie['date_sortie']) ? date('d/m/Y H:i', strtotime($sortie['date_sortie'])) : 'N/A'; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($sortie['immatriculation']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($sortie['type_camion'] ?? '-'); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($sortie['port'] ?? '-'); ?>
                            </td>
                            
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo !empty($sortie['date_entree']) ? date('d/m/Y H:i', strtotime($sortie['date_entree'])) : 'N/A'; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $sortie['type_sortie'] == 'charge' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                    <?php echo $sortie['type_sortie'] == 'charge' ? 'Chargé' : 'Déchargé'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($sorties)): ?>
                        <tr>
                            <td colspan="8" class="px-4 py-4 text-center text-sm text-gray-500">
                                <i class="fas fa-search mr-2"></i>Aucune sortie ne correspond à vos critères de recherche
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
</body>
</html>
