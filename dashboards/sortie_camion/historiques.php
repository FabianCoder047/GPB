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

// Fonction utilitaire
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Fonction pour vérifier si toutes les marchandises d'un chargement ont été pesées (version corrigée)
function checkPesageComplet($conn, $chargement_id) {
    $result = [
        'complet' => false,
        'total_marchandises' => 0,
        'marchandises_pesees' => 0,
        'details' => []
    ];
    
    try {
        // Récupérer toutes les marchandises du chargement avec leur poids
        $stmt = $conn->prepare("
            SELECT 
                mcc.idTypeMarchandise,
                tm.nom as nom_marchandise,
                mcc.poids
            FROM marchandise_chargement_camion mcc
            INNER JOIN type_marchandise tm ON mcc.idTypeMarchandise = tm.id
            WHERE mcc.idChargement = ?
        ");
        $stmt->bind_param("i", $chargement_id);
        $stmt->execute();
        $marchandises_result = $stmt->get_result();
        $marchandises = $marchandises_result->fetch_all(MYSQLI_ASSOC);
        
        $result['total_marchandises'] = count($marchandises);
        
        if (empty($marchandises)) {
            $result['complet'] = false;
            return $result;
        }
        
        // Vérifier le poids de chaque marchandise
        $marchandises_pesees = 0;
        foreach ($marchandises as $march) {
            $pese = (!empty($march['poids']) && floatval($march['poids']) > 0);
            $result['details'][] = [
                'idTypeMarchandise' => $march['idTypeMarchandise'],
                'nom_marchandise' => $march['nom_marchandise'],
                'pese' => $pese,
                'poids' => $pese ? floatval($march['poids']) : 0
            ];
            
            if ($pese) {
                $marchandises_pesees++;
            }
        }
        
        $result['marchandises_pesees'] = $marchandises_pesees;
        $result['complet'] = ($marchandises_pesees === $result['total_marchandises']);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Erreur vérification pesage: " . $e->getMessage());
        $result['complet'] = false;
        return $result;
    }
}

// Fonction pour récupérer les informations de pesage d'un camion (depuis la table pesages)
function getInfosPesage($conn, $camion_id) {
    $result = [
        'ptav' => 0,
        'ptac' => 0,
        'ptra' => 0,
        'charge_essieu' => 0,
        'poids_total_marchandises' => 0,
        'surcharge' => false,
        'date_pesage' => null
    ];
    
    try {
        $stmt = $conn->prepare("
            SELECT ptav, ptac, ptra, charge_essieu, poids_total_marchandises, surcharge, date_pesage
            FROM pesages 
            WHERE idEntree = ?
            ORDER BY date_pesage DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $camion_id);
        $stmt->execute();
        $pesage_result = $stmt->get_result();
        
        if ($pesage_result->num_rows > 0) {
            $pesage = $pesage_result->fetch_assoc();
            $result = [
                'ptav' => $pesage['ptav'] ?? 0,
                'ptac' => $pesage['ptac'] ?? 0,
                'ptra' => $pesage['ptra'] ?? 0,
                'charge_essieu' => $pesage['charge_essieu'] ?? 0,
                'poids_total_marchandises' => $pesage['poids_total_marchandises'] ?? 0,
                'surcharge' => $pesage['surcharge'] ?? false,
                'date_pesage' => $pesage['date_pesage'] ?? null
            ];
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Erreur récupération infos pesage: " . $e->getMessage());
        return $result;
    }
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
    // MODIFIÉ : Suppression de la jointure avec pesage_chargement_camion qui n'est pas utilisée
    $query = "SELECT cs.idSortie, cs.idEntree, cs.idChargement, cs.idDechargement, 
                     cs.date_sortie, cs.type_sortie,
                     ce.immatriculation, ce.etat, ce.date_entree, ce.idPort, 
                     ce.poids as poids_camion, ce.idTypeCamion, ce.raison,
                     ce.prenom_chauffeur, ce.nom_chauffeur,
                     tc.nom as type_camion, p.nom as port
              FROM camions_sortants cs
              LEFT JOIN camions_entrants ce ON cs.idEntree = ce.idEntree
              LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
              LEFT JOIN port p ON ce.idPort = p.id
              $where_sql
              ORDER BY cs.date_sortie DESC
              LIMIT ? OFFSET ?";
    
    // Préparation de la requête avec pagination
    $params_pagination = $params;
    $types_pagination = $types;
    $params_pagination[] = $items_per_page;
    $params_pagination[] = $offset;
    $types_pagination .= 'ii';
    
    $stmt = $conn->prepare($query);
    if (!empty($types_pagination)) {
        $stmt->bind_param($types_pagination, ...$params_pagination);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $sorties = $result->fetch_all(MYSQLI_ASSOC);
    
    // Récupérer les détails supplémentaires pour chaque sortie
    foreach ($sorties as &$sortie) {
        // Récupérer les informations de pesage
        $pesage_infos = getInfosPesage($conn, $sortie['idEntree']);
        $sortie['ptav'] = $pesage_infos['ptav'];
        $sortie['ptac'] = $pesage_infos['ptac'];
        $sortie['ptra'] = $pesage_infos['ptra'];
        $sortie['charge_essieu'] = $pesage_infos['charge_essieu'];
        $sortie['poids_total_marchandises'] = $pesage_infos['poids_total_marchandises'];
        $sortie['surcharge'] = $pesage_infos['surcharge'];
        $sortie['date_pesage'] = $pesage_infos['date_pesage'];
        
        // Calculer le poids total du camion
        $sortie['poids_total_camion'] = $sortie['ptav'] + $sortie['poids_total_marchandises'];
        
        // Récupérer les informations de pesage si chargement
        if ($sortie['type_sortie'] === 'charge' && !empty($sortie['idChargement'])) {
            $pesage_status = checkPesageComplet($conn, $sortie['idChargement']);
            $sortie['pesage_complet'] = $pesage_status['complet'];
            $sortie['nb_marchandises'] = $pesage_status['total_marchandises'];
            $sortie['nb_marchandises_pesees'] = $pesage_status['marchandises_pesees'];
            $sortie['details_pesage'] = $pesage_status['details'];
        }
    }
    unset($sortie);
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
    error_log("Erreur chargement historiques: " . $e->getMessage());
    $sorties = [];
    $total_items = 0;
    $total_pages = 1;
}

// Récupérer les marchandises pour chaque chargement et déchargement
$marchandises_par_chargement = [];
$marchandises_par_dechargement = [];

try {
    // Récupérer les IDs de chargements et déchargements
    $chargement_ids = array_filter(array_column($sorties, 'idChargement'));
    $dechargement_ids = array_filter(array_column($sorties, 'idDechargement'));
    
    // Récupérer les marchandises des chargements
    if (!empty($chargement_ids)) {
        $placeholders = str_repeat('?,', count($chargement_ids) - 1) . '?';
        
        $stmt_march = $conn->prepare("
            SELECT 
                mcc.idChargement,
                mcc.idTypeMarchandise,
                tm.nom as nom_marchandise,
                mcc.poids,
                mcc.note,
                mcc.date_ajout
            FROM marchandise_chargement_camion mcc
            INNER JOIN type_marchandise tm ON mcc.idTypeMarchandise = tm.id
            WHERE mcc.idChargement IN ($placeholders)
            ORDER BY mcc.date_ajout
        ");
        
        $stmt_march->bind_param(str_repeat('i', count($chargement_ids)), ...$chargement_ids);
        $stmt_march->execute();
        $result_march = $stmt_march->get_result();
        
        while ($row = $result_march->fetch_assoc()) {
            $marchandises_par_chargement[$row['idChargement']][] = $row;
        }
    }
    
    // Récupérer les marchandises des déchargements
    if (!empty($dechargement_ids)) {
        $placeholders = str_repeat('?,', count($dechargement_ids) - 1) . '?';
        
        $stmt_march = $conn->prepare("
            SELECT 
                mdc.idDechargement,
                mdc.idTypeMarchandise,
                tm.nom as nom_marchandise,
                mdc.note,
                mdc.date_ajout
            FROM marchandise_dechargement_camion mdc
            INNER JOIN type_marchandise tm ON mdc.idTypeMarchandise = tm.id
            WHERE mdc.idDechargement IN ($placeholders)
            ORDER BY mdc.date_ajout
        ");
        
        $stmt_march->bind_param(str_repeat('i', count($dechargement_ids)), ...$dechargement_ids);
        $stmt_march->execute();
        $result_march = $stmt_march->get_result();
        
        while ($row = $result_march->fetch_assoc()) {
            $marchandises_par_dechargement[$row['idDechargement']][] = $row;
        }
    }
    
} catch (Exception $e) {
    error_log("Erreur chargement marchandises: " . $e->getMessage());
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
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .scrollable-section {
            max-height: calc(100vh - 300px);
            overflow-y: auto;
        }
        
        .marchandise-item {
            border-left: 3px solid #3b82f6;
            background-color: #f8fafc;
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 4px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            width: 80%;
            max-width: 900px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .marchandise-pesee {
            border-left-color: #10b981;
        }
        
        .marchandise-non-pesee {
            border-left-color: #ef4444;
        }
        
        .info-card {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        
        .pesage-info-card {
            background-color: #f0f9ff;
            border-left: 4px solid #3b82f6;
        }
        
        .surcharge-badge {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .conforme-badge {
            background-color: #d1fae5;
            color: #065f46;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Formulaire de filtres -->
        <div class="glass-card p-6 mb-6">
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
                               value="<?php echo safe_html($filters['immatriculation']); ?>"
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
                                    <?php echo safe_html($port['nom']); ?>
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
                               value="<?php echo safe_html($filters['date_debut']); ?>"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Filtre par date de fin -->
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="date_fin">
                            Date fin
                        </label>
                        <input type="date" id="date_fin" name="date_fin"
                               value="<?php echo safe_html($filters['date_fin']); ?>"
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
        <div class="glass-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-list mr-2"></i>Résultats des Recherches
                </h2>
                
                <div class="flex items-center space-x-4">
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chauffeur</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type Sortie</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($sorties as $sortie): ?>
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?php echo !empty($sortie['date_sortie']) ? date('d/m/Y H:i', strtotime($sortie['date_sortie'])) : 'N/A'; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo safe_html($sortie['immatriculation']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo safe_html($sortie['type_camion'] ?? '-'); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo safe_html($sortie['port'] ?? '-'); ?>
                            </td>
                            
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo safe_html(($sortie['prenom_chauffeur'] ?? '') . ' ' . ($sortie['nom_chauffeur'] ?? '')); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $sortie['type_sortie'] == 'charge' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                    <?php echo $sortie['type_sortie'] == 'charge' ? 'Chargé' : 'Déchargé'; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <button type="button" 
                                        onclick="showDetails(<?php echo $sortie['idSortie']; ?>, '<?php echo $sortie['type_sortie']; ?>', <?php echo $sortie['idEntree']; ?>, <?php echo $sortie['idChargement'] ?? 0; ?>, <?php echo $sortie['idDechargement'] ?? 0; ?>)"
                                        class="bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold py-1 px-3 rounded-lg">
                                    <i class="fas fa-eye mr-1"></i> Détails
                                </button>
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
    
    <!-- Modal pour afficher les détails -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle" class="text-lg font-bold text-gray-800"></h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="modalContent"></div>
            </div>
        </div>
    </div>
    
    <script>
        // Données des marchandises (pré-chargées depuis PHP)
        const marchandisesCharge = <?php echo json_encode($marchandises_par_chargement); ?>;
        const marchandisesDecharge = <?php echo json_encode($marchandises_par_dechargement); ?>;
        
        // Données des sorties (pré-chargées depuis PHP)
        const sortiesData = <?php echo json_encode($sorties); ?>;
        
        // Fonction pour afficher les détails dans une modal
        function showDetails(sortieId, type, camionId, chargementId, dechargementId) {
            const sortie = sortiesData.find(s => s.idSortie == sortieId);
            if (!sortie) return;
            
            let marchandises = [];
            let detailsPesage = [];
            let titre = `Détails de la sortie - ${sortie.immatriculation}`;
            
            if (type === 'charge') {
                marchandises = marchandisesCharge[chargementId] || [];
                detailsPesage = sortie.details_pesage || [];
            } else {
                marchandises = marchandisesDecharge[dechargementId] || [];
            }
            
            // Construire le contenu HTML
            let content = `
                <div class="mb-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <div class="info-card">
                            <p class="text-xs text-gray-500">Immatriculation</p>
                            <p class="font-bold text-lg">${sortie.immatriculation}</p>
                        </div>
                        <div class="info-card">
                            <p class="text-xs text-gray-500">Type / Port</p>
                            <p class="font-medium">${sortie.type_camion || '-'} / ${sortie.port || '-'}</p>
                        </div>
                        <div class="info-card">
                            <p class="text-xs text-gray-500">Chauffeur</p>
                            <p class="font-medium">${sortie.prenom_chauffeur || ''} ${sortie.nom_chauffeur || ''}</p>
                        </div>
                        <div class="info-card">
                            <p class="text-xs text-gray-500">Raison</p>
                            <p class="font-medium">${sortie.raison || '-'}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <div class="info-card">
                            <p class="text-xs text-gray-500">Date entrée</p>
                            <p class="font-medium">${formatDate(sortie.date_entree)}</p>
                        </div>
                        <div class="info-card">
                            <p class="text-xs text-gray-500">Date sortie</p>
                            <p class="font-medium">${formatDate(sortie.date_sortie)}</p>
                        </div>
                        <div class="info-card">
                            <p class="text-xs text-gray-500">Type de sortie</p>
                            <p class="font-bold ${type === 'charge' ? 'text-blue-600' : 'text-green-600'}">${type === 'charge' ? 'CHARGÉ' : 'DÉCHARGÉ'}</p>
                        </div>
                        
                    </div>
                </div>
            `;
            
            // Section des informations de pesage (disponible pour tous les camions sortis)
            if (sortie.date_pesage || sortie.ptav > 0) {
                const surchargeStatus = sortie.surcharge ? 'SURCHARGE' : 'CONFORME';
                const surchargeClass = sortie.surcharge ? 'surcharge-badge' : 'conforme-badge';
                
                content += `
                    <div class="mb-6 pesage-info-card p-4">
                        <h4 class="font-bold text-gray-800 mb-3">
                            <i class="fas fa-weight-scale mr-2"></i>Informations de Pesage
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                            <div>
                                <p class="text-xs text-gray-500">PTAV</p>
                                <p class="font-bold">${formatNumber(sortie.ptav)} kg</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">PTAC</p>
                                <p class="font-bold">${formatNumber(sortie.ptac)} kg</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">PTRA</p>
                                <p class="font-bold">${formatNumber(sortie.ptra)} kg</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Charge Essieu</p>
                                <p class="font-bold">${formatNumber(sortie.charge_essieu)} kg</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                            <div>
                                <p class="text-xs text-gray-500">Poids marchandises</p>
                                <p class="font-bold">${formatNumber(sortie.poids_total_marchandises)} kg</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Poids total camion</p>
                                <p class="font-bold">${formatNumber(sortie.poids_total_camion)} kg</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">État</p>
                                <span class="${surchargeClass}">${surchargeStatus}</span>
                            </div>
                        </div>
                        
                        ${sortie.date_pesage ? `
                            <div class="mt-2">
                                <p class="text-xs text-gray-500">Date du pesage</p>
                                <p class="text-sm font-medium">${formatDate(sortie.date_pesage)}</p>
                            </div>
                        ` : ''}
                    </div>
                `;
            }
            
            // Ajouter les informations spécifiques selon le type
            if (type === 'charge') {
                // Section état du pesage pour les chargements
                if (sortie.pesage_complet !== undefined) {
                    content += `
                        <div class="mb-6">
                            <div class="${sortie.pesage_complet ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'} rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <i class="fas fa-weight-scale ${sortie.pesage_complet ? 'text-green-600' : 'text-yellow-600'} mr-3 text-lg"></i>
                                        <div>
                                            <p class="text-xs text-gray-600">État du pesage au moment de la sortie</p>
                                            <p class="text-sm font-bold ${sortie.pesage_complet ? 'text-green-700' : 'text-yellow-700'}">
                                                ${sortie.pesage_complet ? 'COMPLET' : 'INCOMPLET'}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-600">Marchandises pesées</p>
                                        <p class="text-sm font-bold ${sortie.pesage_complet ? 'text-green-700' : 'text-yellow-700'}">
                                            ${sortie.nb_marchandises_pesees || 0} / ${sortie.nb_marchandises || 0}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                // Informations de chargement
                content += `
                    <div class="mb-6">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-upload text-blue-600 mr-3"></i>
                                <div>
                                    <p class="text-xs text-gray-600">Type de sortie</p>
                                    <p class="text-sm font-bold text-gray-800">CHARGÉ</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                content += `
                    <div class="mb-6">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-download text-green-600 mr-3"></i>
                                <div>
                                    <p class="text-xs text-gray-600">Type de sortie</p>
                                    <p class="text-sm font-bold text-gray-800">DÉCHARGÉ</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Ajouter la section des marchandises
            if (type === 'charge' && detailsPesage.length > 0) {
                content += `
                    <div class="mb-6">
                        <h4 class="font-bold text-gray-800 mb-4">
                            <i class="fas fa-boxes mr-2"></i>Marchandises chargées et état du pesage
                        </h4>
                        <div class="space-y-3">
                `;
                
                detailsPesage.forEach((detail, index) => {
                    const isPese = detail.pese;
                    const poids = isPese ? formatNumber(detail.poids) + ' kg' : 'Non pesé';
                    const peseeClass = isPese ? 'marchandise-pesee' : 'marchandise-non-pesee';
                    const statusBadge = isPese ? 
                        '<span class="bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded ml-2">PESÉ</span>' : 
                        '<span class="bg-red-100 text-red-800 text-xs font-bold px-2 py-1 rounded ml-2">NON PESÉ</span>';
                    
                    content += `
                        <div class="marchandise-item ${peseeClass}">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-gray-800">
                                        ${detail.nom_marchandise}
                                        ${statusBadge}
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <span class="font-medium">Poids:</span> ${poids}
                                    </p>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                content += `
                        </div>
                    </div>
                `;
            } else if (marchandises.length > 0) {
                content += `
                    <div class="mb-6">
                        <h4 class="font-bold text-gray-800 mb-4">
                            <i class="fas fa-boxes mr-2"></i>Marchandises ${type === 'charge' ? 'chargées' : 'déchargées'}
                        </h4>
                        <div class="space-y-3">
                `;
                
                marchandises.forEach((march, index) => {
                    const poidsDisplay = march.poids && parseFloat(march.poids) > 0 ? 
                        `<p class="text-sm text-gray-600 mt-1"><span class="font-medium">Poids:</span> ${formatNumber(march.poids)} kg</p>` : '';
                    
                    content += `
                        <div class="marchandise-item">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-gray-800">${march.nom_marchandise}</p>
                                    ${poidsDisplay}
                                    ${march.note ? `<p class="text-sm text-gray-600 mt-1">${march.note}</p>` : ''}
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500">Ajouté le</p>
                                    <p class="text-xs text-gray-700">${formatDate(march.date_ajout)}</p>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                content += `
                        </div>
                    </div>
                `;
            } else {
                content += `
                    <div class="text-center py-4 text-gray-500">
                        <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                        <p>Aucune marchandise enregistrée</p>
                    </div>
                `;
            }
            
            // Mettre à jour la modal et l'afficher
            document.getElementById('modalTitle').textContent = titre;
            document.getElementById('modalContent').innerHTML = content;
            document.getElementById('detailsModal').style.display = 'block';
        }
        
        // Fonction pour fermer la modal
        function closeModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }
        
        // Fermer la modal en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('detailsModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Fonctions utilitaires
        function formatNumber(num) {
            if (!num || isNaN(num)) return '0.00';
            return parseFloat(num).toLocaleString('fr-FR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        function formatDate(dateString) {
            if (!dateString || dateString === '0000-00-00 00:00:00') return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
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