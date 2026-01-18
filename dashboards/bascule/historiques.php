<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'agentBascule') {
    header("Location: ../../login.php");
    exit();
}

// Fonction utilitaire pour éviter les erreurs de dépréciation
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Variables pour les filtres
$search_immat = $_GET['search'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$type_camion_filter = $_GET['type_camion'] ?? '';
$etat_filter = $_GET['etat'] ?? '';

// Variables pour les détails sélectionnés
$selected_camion = null;
$selected_camion_id = null;
$pesage_details = null;
$marchandises = [];

// Traitement de la sélection d'un camion
if (isset($_GET['select']) && is_numeric($_GET['select'])) {
    $selected_camion_id = $_GET['select'];
    
    try {
        // Récupérer les informations du camion avec le pesage
        $stmt = $conn->prepare("
            SELECT ce.*, tc.nom as type_camion, p.nom as port, 
                   ps.*, u.prenom as agent_prenom, u.nom as agent_nom
            FROM camions_entrants ce
            LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
            LEFT JOIN port p ON ce.idPort = p.id
            LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
            LEFT JOIN users u ON ps.agent_bascule = CONCAT(u.prenom, ' ', u.nom)
            WHERE ce.idEntree = ?
        ");
        $stmt->bind_param("i", $selected_camion_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $selected_camion = $result->fetch_assoc();
            $pesage_details = $selected_camion;
            
            // Récupérer les marchandises associées
            if ($pesage_details['idPesage']) {
                $stmt = $conn->prepare("
                    SELECT mp.*, tm.nom as type_marchandise, tm.id
                    FROM marchandises_pesage mp
                    LEFT JOIN type_marchandise tm ON mp.idTypeMarchandise = tm.id
                    WHERE mp.idPesage = ?
                    ORDER BY mp.date_ajout DESC
                ");
                $stmt->bind_param("i", $pesage_details['idPesage']);
                $stmt->execute();
                $marchandises_result = $stmt->get_result();
                $marchandises = $marchandises_result->fetch_all(MYSQLI_ASSOC);
            }
        }
    } catch (Exception $e) {
        $error = "Erreur lors du chargement des données: " . $e->getMessage();
    }
}

// Récupérer les types de camions pour le filtre
$types_camion = [];
try {
    $result = $conn->query("SELECT id, nom FROM type_camion ORDER BY nom");
    $types_camion = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = "Erreur lors du chargement des types de camion: " . $e->getMessage();
}

// Récupérer la liste des camions avec filtres
$camions = [];

try {
    // Construire la requête pour les camions avec pesage
    $query = "
        SELECT ce.*, tc.nom as type_camion, p.nom as port, 
               ps.poids_total_marchandises, ps.surcharge, ps.date_pesage,
               ps.agent_bascule, ps.ptav, ps.ptac, ps.ptra
        FROM camions_entrants ce
        LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
        LEFT JOIN port p ON ce.idPort = p.id
        INNER JOIN pesages ps ON ce.idEntree = ps.idEntree
        WHERE ps.idPesage IS NOT NULL
    ";
    
    $params = [];
    $types = '';
    
    if (!empty($search_immat)) {
        $query .= " AND ce.immatriculation LIKE ?";
        $params[] = '%' . $search_immat . '%';
        $types .= 's';
    }
    
    if (!empty($date_debut)) {
        $query .= " AND DATE(ps.date_pesage) >= ?";
        $params[] = $date_debut;
        $types .= 's';
    }
    
    if (!empty($date_fin)) {
        $query .= " AND DATE(ps.date_pesage) <= ?";
        $params[] = $date_fin;
        $types .= 's';
    }
    
    if (!empty($type_camion_filter)) {
        $query .= " AND ce.idTypeCamion = ?";
        $params[] = $type_camion_filter;
        $types .= 'i';
    }
    
    if (!empty($etat_filter)) {
        $query .= " AND ce.etat = ?";
        $params[] = $etat_filter;
        $types .= 's';
    }
    
    $query .= " ORDER BY ps.date_pesage DESC LIMIT 100";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }
    
    $camions = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
}

// Statistiques
$stats = [
    'total' => count($camions),
    'surcharge' => 0,
    'conforme' => 0,
    'poids_total' => 0
];

foreach ($camions as $camion) {
    if ($camion['surcharge']) {
        $stats['surcharge']++;
    } else {
        $stats['conforme']++;
    }
    if ($camion['ptav'] && $camion['poids_total_marchandises']) {
        $stats['poids_total'] += ($camion['ptav'] + $camion['poids_total_marchandises']);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Bascule - Historique des Pesages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bibliothèques pour l'export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.5.28/dist/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        .scrollable-table {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .sticky-header {
            position: sticky;
            top: 0;
            background-color: #f9fafb;
            z-index: 10;
        }
        
        @media (max-width: 1024px) {
            .grid-cols-1-2 {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .export-btn-group {
            display: flex;
            gap: 8px;
        }
        
        .export-btn {
            transition: all 0.3s ease;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-gray-100">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo safe_html($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Cartes de statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card bg-white shadow rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-truck text-blue-600 text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Total pesages</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white shadow rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Conformes</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $stats['conforme']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white shadow rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Surcharges</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo $stats['surcharge']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white shadow rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-weight-hanging text-yellow-600 text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Poids total</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['poids_total'], 0); ?> kg</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Formulaire de filtres -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-filter mr-2"></i>Filtres de Recherche
                </h2>
                
                <a href="historiques.php" class="text-sm text-gray-600 hover:text-gray-800">
                    <i class="fas fa-redo mr-1"></i>Réinitialiser
                </a>
            </div>
            
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-gray-700 text-xs font-bold mb-1" for="search">
                            Immatriculation
                        </label>
                        <input type="text" id="search" name="search" 
                               value="<?php echo safe_html($search_immat); ?>"
                               placeholder="Rechercher..."
                               class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-xs font-bold mb-1" for="date_debut">
                            Date début
                        </label>
                        <input type="date" id="date_debut" name="date_debut" 
                               value="<?php echo safe_html($date_debut); ?>"
                               class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-xs font-bold mb-1" for="date_fin">
                            Date fin
                        </label>
                        <input type="date" id="date_fin" name="date_fin" 
                               value="<?php echo safe_html($date_fin); ?>"
                               class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-xs font-bold mb-1" for="type_camion">
                            Type camion
                        </label>
                        <select id="type_camion" name="type_camion"
                                class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Tous</option>
                            <?php foreach ($types_camion as $type): ?>
                                <option value="<?php echo $type['id']; ?>"
                                    <?php echo $type_camion_filter == $type['id'] ? 'selected' : ''; ?>>
                                    <?php echo safe_html($type['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-xs font-bold mb-1" for="etat">
                            État
                        </label>
                        <select id="etat" name="etat"
                                class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Tous</option>
                            <option value="Vide" <?php echo $etat_filter == 'Vide' ? 'selected' : ''; ?>>Vide</option>
                            <option value="Chargé" <?php echo $etat_filter == 'Chargé' ? 'selected' : ''; ?>>Chargé</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded-lg text-sm">
                        <i class="fas fa-search mr-2"></i>Appliquer les filtres
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Grille principale avec les deux sections côte à côte -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 grid-cols-1-2">
            
            <!-- Section 1: Liste des camions pesés -->
            <div class="bg-white shadow rounded-lg">
                <div class="p-4 border-b">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-lg font-bold text-gray-800">
                                <i class="fas fa-list mr-2"></i>Historique des Pesages
                            </h2>
                            <p class="text-sm text-gray-600"><?php echo count($camions); ?> résultats</p>
                        </div>
                        
                    </div>
                </div>
                
                <div class="scrollable-table">
                    <table id="historiqueTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="sticky-header bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date pesage</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poids total</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Surcharge</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($camions as $camion): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-150 cursor-pointer 
                                <?php echo $selected_camion_id == $camion['idEntree'] ? 'bg-blue-50 border-l-4 border-blue-500' : ''; ?>"
                                onclick="selectCamion(<?php echo $camion['idEntree']; ?>)">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo date('d/m/Y', strtotime($camion['date_pesage'])); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo date('H:i', strtotime($camion['date_pesage'])); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-truck text-blue-600 text-sm"></i>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo safe_html($camion['immatriculation']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo safe_html($camion['type_camion']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php 
                                    $poids_total = ($camion['ptav'] ?? 0) + ($camion['poids_total_marchandises'] ?? 0);
                                    echo number_format($poids_total, 2) . ' kg';
                                    ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $camion['surcharge'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                        <i class="fas fa-weight-scale mr-1"></i>
                                        <?php echo $camion['surcharge'] ? 'Oui' : 'Non'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($camions)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                    <i class="fas fa-inbox text-3xl text-gray-300 mb-2 block"></i>
                                    Aucun pesage trouvé
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Section 2: Détails du pesage -->
            <?php if ($selected_camion && $pesage_details): ?>
            <div class="bg-white shadow rounded-lg">
                <div class="p-4 border-b">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-lg font-bold text-gray-800">
                                <i class="fas fa-info-circle mr-2"></i>Détails du Pesage
                            </h2>
                            <div class="flex items-center space-x-2 mt-1">
                                <span class="text-sm font-medium text-gray-900">
                                    <?php echo safe_html($selected_camion['immatriculation']); ?>
                                </span>
                                <span class="text-gray-400">•</span>
                                <span class="text-sm text-gray-600">
                                    <?php echo safe_html($selected_camion['type_camion'] ?? ''); ?>
                                </span>
                                <span class="text-gray-400">•</span>
                                <span class="text-xs <?php echo $pesage_details['surcharge'] ? 'text-red-600' : 'text-green-600'; ?>">
                                    <?php echo date('d/m/Y H:i', strtotime($pesage_details['date_pesage'])); ?>
                                </span>
                            </div>
                        </div>
                        <a href="historiques.php<?php 
                            echo !empty($search_immat) ? '?search=' . urlencode($search_immat) : '';
                            echo !empty($date_debut) ? '&date_debut=' . urlencode($date_debut) : '';
                            echo !empty($date_fin) ? '&date_fin=' . urlencode($date_fin) : '';
                            echo !empty($type_camion_filter) ? '&type_camion=' . urlencode($type_camion_filter) : '';
                            echo !empty($etat_filter) ? '&etat=' . urlencode($etat_filter) : '';
                        ?>" 
                           class="text-gray-400 hover:text-gray-600 p-2">
                            <i class="fas fa-times text-lg"></i>
                        </a>
                    </div>
                </div>
                
                <div class="p-4">
                    <!-- Informations générales -->
                    <div class="mb-6">
                        <h3 class="text-md font-bold text-gray-800 mb-3">Informations Générales</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-xs text-gray-500">Chauffeur</p>
                                <p class="font-medium text-sm">
                                    <?php echo safe_html(($selected_camion['prenom_chauffeur'] ?? '') . ' ' . ($selected_camion['nom_chauffeur'] ?? '')); ?>
                                </p>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-xs text-gray-500">État / Raison</p>
                                <p class="font-medium text-sm">
                                    Chargé / 
                                    <?php echo safe_html($selected_camion['raison'] ?? ''); ?>
                                </p>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-xs text-gray-500">Port</p>
                                <p class="font-medium text-sm">
                                    <?php echo safe_html($selected_camion['port'] ?? '-'); ?>
                                </p>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-xs text-gray-500">Agent bascule</p>
                                <p class="font-medium text-sm">
                                    <?php echo safe_html($pesage_details['agent_bascule'] ?? '-'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Données de pesage -->
                    <div class="mb-6">
                        <h3 class="text-md font-bold text-gray-800 mb-3">Données de Pesage</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                                <p class="text-xs text-gray-600 mb-1">PTAV (kg)</p>
                                <p class="text-lg font-bold text-blue-800">
                                    <?php echo number_format($pesage_details['ptav'] ?? 0, 2); ?>
                                </p>
                            </div>
                            
                            <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                                <p class="text-xs text-gray-600 mb-1">PTAC (kg)</p>
                                <p class="text-lg font-bold text-blue-800">
                                    <?php echo number_format($pesage_details['ptac'] ?? 0, 2); ?>
                                </p>
                            </div>
                            
                            <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                                <p class="text-xs text-gray-600 mb-1">PTRA (kg)</p>
                                <p class="text-lg font-bold text-blue-800">
                                    <?php echo number_format($pesage_details['ptra'] ?? 0, 2); ?>
                                </p>
                            </div>
                            
                            <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                                <p class="text-xs text-gray-600 mb-1">Essieu (kg)</p>
                                <p class="text-lg font-bold text-blue-800">
                                    <?php echo number_format($pesage_details['charge_essieu'] ?? 0, 2); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Marchandises (si camion chargé) -->
                    <?php if (strtolower($selected_camion['etat'] ?? '') !== 'vide' && !empty($marchandises)): ?>
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-md font-bold text-gray-800">Marchandises</h3>
                            <span class="text-sm text-gray-600">
                                Total: <?php 
                                $total_marchandises = array_sum(array_column($marchandises, 'poids'));
                                echo number_format($total_marchandises, 2) . ' kg';
                                ?>
                            </span>
                        </div>
                        
                        <div class="space-y-2 max-h-48 overflow-y-auto p-1">
                            <?php foreach ($marchandises as $marchandise): ?>
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <span class="font-medium text-sm"><?php echo safe_html($marchandise['type_marchandise']); ?></span>
                                    <?php if (!empty($marchandise['note'])): ?>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo safe_html($marchandise['note']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-sm"><?php echo number_format($marchandise['poids'], 2); ?> kg</span>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php echo date('H:i', strtotime($marchandise['date_ajout'])); ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Résultats -->
                    <div class="mb-6">
                        <h3 class="text-md font-bold text-gray-800 mb-3">Résultats</h3>
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <?php
                            $poids_total_marchandises = $pesage_details['poids_total_marchandises'] ?? 0;
                            $poids_total_camion = ($pesage_details['ptav'] ?? 0) + $poids_total_marchandises;
                            $is_surcharge = $pesage_details['surcharge'] ?? false;
                            ?>
                            <div class="text-center p-3 border-2 rounded-lg 
                                <?php echo $is_surcharge ? 'border-yellow-300 bg-yellow-50' : 'border-blue-300 bg-blue-50'; ?>">
                                <p class="text-xs text-gray-600 mb-1">Poids total camion</p>
                                <p class="text-2xl font-bold text-gray-800">
                                    <?php echo number_format($poids_total_camion, 2); ?> kg
                                </p>
                            </div>
                            
                            <div class="text-center p-3 border-2 rounded-lg 
                                <?php echo $is_surcharge ? 'border-red-300 bg-red-50' : 'border-green-300 bg-green-50'; ?>">
                                <p class="text-xs text-gray-600 mb-1">État</p>
                                <p class="text-2xl font-bold <?php echo $is_surcharge ? 'text-red-600' : 'text-green-600'; ?>">
                                    <?php echo $is_surcharge ? 'SURCHARGE' : 'CONFORME'; ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if (!empty($pesage_details['note_surcharge'])): ?>
                        <div class="mt-3">
                            <label class="block text-gray-700 text-xs font-bold mb-1">
                                Note sur la surcharge
                            </label>
                            <div class="w-full px-3 py-2 text-sm bg-gray-50 border rounded-lg">
                                <?php echo nl2br(safe_html($pesage_details['note_surcharge'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Section vide quand aucun camion n'est sélectionné -->
            <div class="bg-white shadow rounded-lg p-8 text-center flex flex-col items-center justify-center h-full">
                <i class="fas fa-file-alt text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-bold text-gray-700 mb-2">Aucun pesage sélectionné</h3>
                <p class="text-gray-600">Sélectionnez un pesage dans la liste pour voir les détails.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Fonction pour sélectionner un camion
        function selectCamion(camionId) {
            // Construire l'URL avec les paramètres de filtres
            let url = 'historiques.php?select=' + camionId;
            
            // Ajouter les filtres actuels
            const params = new URLSearchParams(window.location.search);
            params.forEach((value, key) => {
                if (key !== 'select') {
                    url += '&' + key + '=' + encodeURIComponent(value);
                }
            });
            
            window.location.href = url;
        }
        
        
        
        
       
        
        // Initialisation de la date d'aujourd'hui pour le filtre date_fin
        document.addEventListener('DOMContentLoaded', function() {
            const dateFinInput = document.getElementById('date_fin');
            if (dateFinInput && !dateFinInput.value) {
                const today = new Date().toISOString().split('T')[0];
                dateFinInput.value = today;
            }
            
            // Si date_debut est vide, mettre il y a 30 jours
            const dateDebutInput = document.getElementById('date_debut');
            if (dateDebutInput && !dateDebutInput.value) {
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                dateDebutInput.value = thirtyDaysAgo.toISOString().split('T')[0];
            }
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