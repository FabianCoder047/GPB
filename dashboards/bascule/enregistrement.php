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

// Variables pour stocker les données du camion sélectionné
$selected_camion = null;
$selected_camion_id = null;
$pesage_existant = null;
$marchandises = [];
$marchandises_chargement = []; // Pour les marchandises du chargement (sortie)

// Recherche par immatriculation
$search_immat = $_GET['search'] ?? '';

// Récupérer le mode (view ou edit) et l'onglet actif
$mode = $_GET['mode'] ?? null;
$onglet_actif = $_GET['onglet'] ?? 'entree'; // 'entree' ou 'sortie'

// Traitement de la sélection d'un camion
if (isset($_GET['select']) && is_numeric($_GET['select'])) {
    $selected_camion_id = $_GET['select'];
    
    try {
        if ($onglet_actif === 'sortie') {
            // Récupérer les informations du camion pour le pesage sortie
            $stmt = $conn->prepare("
                SELECT ce.*, tc.nom as type_camion, p.nom as port, 
                       cc.idChargement, cc.date_chargement,
                       EXISTS(SELECT 1 FROM marchandise_chargement_camion mcc 
                              WHERE mcc.idChargement = cc.idChargement AND mcc.poids = 0) as a_marchandises_non_pesees
                FROM chargement_camions cc
                LEFT JOIN camions_entrants ce ON cc.idEntree = ce.idEntree
                LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
                LEFT JOIN port p ON ce.idPort = p.id
                WHERE ce.idEntree = ?
                LIMIT 1
            ");
        } else {
            // Récupérer les informations du camion pour le pesage entrée
            $stmt = $conn->prepare("
                SELECT ce.*, tc.nom as type_camion, p.nom as port 
                FROM camions_entrants ce
                LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
                LEFT JOIN port p ON ce.idPort = p.id
                WHERE ce.idEntree = ?
            ");
        }
        
        $stmt->bind_param("i", $selected_camion_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $selected_camion = $result->fetch_assoc();
            
            // Vérifier s'il y a déjà un pesage pour ce camion
            $stmt = $conn->prepare("SELECT * FROM pesages WHERE idEntree = ?");
            $stmt->bind_param("i", $selected_camion_id);
            $stmt->execute();
            $pesage_result = $stmt->get_result();
            
            if ($pesage_result->num_rows > 0) {
                $pesage_existant = $pesage_result->fetch_assoc();
                
                // Récupérer les marchandises associées
                $stmt = $conn->prepare("
                    SELECT mp.*, tm.nom as type_marchandise, tm.id
                    FROM marchandises_pesage mp
                    LEFT JOIN type_marchandise tm ON mp.idTypeMarchandise = tm.id
                    WHERE mp.idPesage = ?
                    ORDER BY mp.date_ajout DESC
                ");
                $stmt->bind_param("i", $pesage_existant['idPesage']);
                $stmt->execute();
                $marchandises_result = $stmt->get_result();
                $marchandises = $marchandises_result->fetch_all(MYSQLI_ASSOC);
            }
            
            // Récupérer les marchandises du chargement pour le pesage sortie
            if ($onglet_actif === 'sortie' && isset($selected_camion['idChargement'])) {
                $stmt = $conn->prepare("
                    SELECT mcc.*, tm.nom as type_marchandise
                    FROM marchandise_chargement_camion mcc
                    LEFT JOIN type_marchandise tm ON mcc.idTypeMarchandise = tm.id
                    WHERE mcc.idChargement = ?
                    ORDER BY mcc.date_ajout DESC
                ");
                $stmt->bind_param("i", $selected_camion['idChargement']);
                $stmt->execute();
                $marchandises_chargement_result = $stmt->get_result();
                $marchandises_chargement = $marchandises_chargement_result->fetch_all(MYSQLI_ASSOC);
                
                // Si on a des marchandises de chargement mais pas encore de pesage,
                // on pré-remplit les marchandises avec celles du chargement
                if (empty($marchandises) && !empty($marchandises_chargement)) {
                    foreach ($marchandises_chargement as $marchandise) {
                        $marchandises[] = [
                            'idTypeMarchandise' => $marchandise['idTypeMarchandise'],
                            'type_marchandise' => $marchandise['type_marchandise'],
                            'poids' => $marchandise['poids'], // Initialement 0 pour les marchandises non pesées
                            'note' => $marchandise['note'] ?? '',
                            'from_chargement' => true
                        ];
                    }
                }
            }
            
            // Si le mode n'est pas spécifié, on regarde si le camion a un pesage
            // Si oui, on met 'view' par défaut, sinon 'edit'
            if ($mode === null) {
                if ($pesage_existant) {
                    $mode = 'view';
                } else {
                    $mode = 'edit';
                }
            }
        }
    } catch (Exception $e) {
        $error = "Erreur lors du chargement des données: " . $e->getMessage();
    }
}

// Traitement du formulaire de pesage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $selected_camion_id = $_POST['idEntree'] ?? null;
    $mode = $_POST['mode'] ?? 'edit';
    $onglet_actif = $_POST['onglet'] ?? 'entree';
    
    if ($selected_camion_id && $_POST['action'] === 'peser') {
        try {
            $ptav = $_POST['ptav'] ?? 0;
            $ptac = $_POST['ptac'] ?? 0;
            $ptra = $_POST['ptra'] ?? 0;
            $charge_essieu = $_POST['charge_essieu'] ?? 0;
            $note_surcharge = $_POST['note_surcharge'] ?? '';
            
            // Calculer le poids total des marchandises
            $poids_total_marchandises = 0;
            if (isset($_POST['marchandises']) && is_array($_POST['marchandises'])) {
                foreach ($_POST['marchandises'] as $marchandise) {
                    if (!empty($marchandise['poids']) && $marchandise['poids'] > 0) {
                        $poids_total_marchandises += floatval($marchandise['poids']);
                    }
                }
            }
            
            // Vérifier la surcharge
            $surcharge = false;
            $poids_total_camion = $ptav + $poids_total_marchandises;
            
            if ($poids_total_camion > $ptac) {
                $surcharge = true;
            }
            
            if ($poids_total_camion > $ptra) {
                $surcharge = true;
            }
            
            // Insérer ou mettre à jour le pesage
            if (isset($_POST['idPesage']) && !empty($_POST['idPesage'])) {
                // Mise à jour
                $stmt = $conn->prepare("
                    UPDATE pesages 
                    SET ptav = ?, ptac = ?, ptra = ?, charge_essieu = ?, 
                        poids_total_marchandises = ?, surcharge = ?, note_surcharge = ?, date_pesage = NOW()
                    WHERE idPesage = ?
                ");
                $stmt->bind_param("dddddssi", $ptav, $ptac, $ptra, $charge_essieu, 
                                 $poids_total_marchandises, $surcharge, $note_surcharge, $_POST['idPesage']);
                $stmt->execute();
                $idPesage = $_POST['idPesage'];
                
                // Supprimer les anciennes marchandises
                $stmt = $conn->prepare("DELETE FROM marchandises_pesage WHERE idPesage = ?");
                $stmt->bind_param("i", $idPesage);
                $stmt->execute();
            } else {
                // Nouveau pesage
                $agent_name = $user['prenom'] . ' ' . $user['nom'];
                $stmt = $conn->prepare("
                    INSERT INTO pesages (idEntree, ptav, ptac, ptra, charge_essieu, 
                                        poids_total_marchandises, surcharge, note_surcharge, agent_bascule)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("idddddiss", $selected_camion_id, $ptav, $ptac, $ptra, 
                                 $charge_essieu, $poids_total_marchandises, $surcharge, $note_surcharge, $agent_name);
                $stmt->execute();
                $idPesage = $stmt->insert_id;
            }
            
            // Insérer les marchandises seulement si le camion n'est pas vide
            if (isset($_POST['marchandises']) && is_array($_POST['marchandises'])) {
                foreach ($_POST['marchandises'] as $marchandise) {
                    if (!empty($marchandise['type']) && $marchandise['type'] > 0 && !empty($marchandise['poids']) && $marchandise['poids'] > 0) {
                        $type_id = intval($marchandise['type']);
                        $poids = floatval($marchandise['poids']);
                        $note = $marchandise['note'] ?? '';
                        
                        $stmt = $conn->prepare("
                            INSERT INTO marchandises_pesage (idPesage, idTypeMarchandise, poids, note)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->bind_param("iids", $idPesage, $type_id, $poids, $note);
                        $stmt->execute();
                        
                        // Si c'est un pesage de sortie, mettre à jour la table marchandise_chargement_camion
                        if ($onglet_actif === 'sortie' && isset($selected_camion['idChargement'])) {
                            // Mettre à jour le poids dans le chargement
                            $stmt_update = $conn->prepare("
                                UPDATE marchandise_chargement_camion 
                                SET poids = ?, date_ajout = NOW()
                                WHERE idChargement = ? AND idTypeMarchandise = ?
                            ");
                            $stmt_update->bind_param("dii", $poids, $selected_camion['idChargement'], $type_id);
                            $stmt_update->execute();
                        }
                    }
                }
            }
            
            $success = "Pesage enregistré avec succès!";
            
            // Rediriger en mode view après enregistrement
            if ($selected_camion_id) {
                header("Location: enregistrement.php?select=" . $selected_camion_id . "&mode=view&onglet=" . $onglet_actif);
                exit();
            }
            
        } catch (Exception $e) {
            $error = "Erreur lors de l'enregistrement du pesage: " . $e->getMessage();
        }
    }
}

// Récupérer la liste des camions avec filtre de recherche
$camions_entree = [];
$camions_sortie = [];
$types_marchandises = [];

// Récupérer les types de marchandises
$query_types_marchandises = "SELECT id, nom FROM type_marchandise ORDER BY nom";
$result_types = $conn->query($query_types_marchandises);
if ($result_types && $result_types->num_rows > 0) {
    while ($row = $result_types->fetch_assoc()) {
        $types_marchandises[] = $row;
    }
}

if ($onglet_actif === 'sortie') {
    // Récupérer les camions pour le pesage sortie (avec chargement mais non pesés)
    $query_camions_avec_chargement = '
    SELECT DISTINCT ce.*, tc.nom as type_camion, p.nom as port, 
           cc.idChargement, cc.date_chargement,
           ps.poids_total_marchandises, ps.surcharge, ps.date_pesage,
           EXISTS(SELECT 1 FROM marchandise_chargement_camion mcc 
                  WHERE mcc.idChargement = cc.idChargement AND mcc.poids = 0) as a_marchandises_non_pesees
    FROM chargement_camions cc
    LEFT JOIN camions_entrants ce ON cc.idEntree = ce.idEntree
    LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
    LEFT JOIN port p ON ce.idPort = p.id
    LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
    WHERE EXISTS (
        SELECT 1 FROM marchandise_chargement_camion mcc 
        WHERE mcc.idChargement = cc.idChargement
    )
    ';
    
    if (!empty($search_immat)) {
        $query_camions_avec_chargement .= " AND ce.immatriculation LIKE '%" . $conn->real_escape_string($search_immat) . "%'";
    }
    
    $query_camions_avec_chargement .= " ORDER BY cc.date_chargement DESC LIMIT 50";
    
    $result_chargement = $conn->query($query_camions_avec_chargement);
    if ($result_chargement && $result_chargement->num_rows > 0) {
        while ($row = $result_chargement->fetch_assoc()) {
            $camions_sortie[] = $row;
        }
    }
} else {
    // Récupérer les camions pour le pesage entrée
    $query_all = "
        SELECT ce.*, tc.nom as type_camion, p.nom as port, 
               ps.poids_total_marchandises, ps.surcharge, ps.date_pesage
        FROM camions_entrants ce
        LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
        LEFT JOIN port p ON ce.idPort = p.id
        LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
        WHERE 1=1
    ";
    
    $params = [];
    $types = '';
    
    if (!empty($search_immat)) {
        $query_all .= " AND ce.immatriculation LIKE ?";
        $params[] = '%' . $search_immat . '%';
        $types .= 's';
    }
    
    $query_all .= " ORDER BY ce.date_entree DESC LIMIT 50";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($query_all);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query_all);
    }
    
    $camions_entree = $result->fetch_all(MYSQLI_ASSOC);
}

// Déterminer si le camion sélectionné est vide
$isCamionVide = false;
if ($selected_camion && isset($selected_camion['etat'])) {
    $isCamionVide = (strtolower($selected_camion['etat']) === 'vide');
}

// Calculer les résultats pour l'affichage en mode view
$poids_total_camion_view = 0;
$surcharge_view = false;
$poids_total_marchandises_view = 0;

if ($mode == 'view' && $pesage_existant) {
    $poids_total_marchandises_view = $pesage_existant['poids_total_marchandises'] ?? 0;
    $poids_total_camion_view = ($pesage_existant['ptav'] ?? 0) + $poids_total_marchandises_view;
    $surcharge_view = $pesage_existant['surcharge'] ?? false;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Bascule - Pesage des Camions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .marchandise-item {
            transition: all 0.3s ease;
        }
        
        .marchandise-item:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .scrollable-table {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .scrollable-table-small {
            max-height: 300px;
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
        
        input:read-only, select:disabled, textarea:read-only {
            background-color: #f3f4f6;
            cursor: not-allowed;
        }
        
        .badge-surcharge {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-conforme {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-a-peser {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            background: none;
            cursor: pointer;
            position: relative;
        }
        
        .tab-button.active {
            color: #3b82f6;
        }
        
        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 3px;
            background-color: #3b82f6;
        }
        
        .tab-button:not(.active) {
            color: #6b7280;
        }
        
        .tab-button:not(.active):hover {
            color: #4b5563;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .info-bulle {
            position: relative;
            display: inline-block;
        }
        
        .info-bulle .info-text {
            visibility: hidden;
            width: 200px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .info-bulle:hover .info-text {
            visibility: visible;
            opacity: 1;
        }
        
        .poids-manquant {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { border-color: #fbbf24; }
            50% { border-color: #f87171; }
            100% { border-color: #fbbf24; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-gray-100">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo safe_html($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo safe_html($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Onglets -->
        <div class="bg-white shadow rounded-t-lg mb-2">
            <div class="flex border-b">
                <button type="button" 
                        data-tab="entree" 
                        class="tab-button <?php echo $onglet_actif === 'entree' ? 'active' : ''; ?>">
                    <i class="fas fa-sign-in-alt mr-2"></i>Pesage Entrée
                    <span class="ml-2 px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                        <?php echo count($camions_entree); ?>
                    </span>
                </button>
                <button type="button" 
                        data-tab="sortie" 
                        class="tab-button <?php echo $onglet_actif === 'sortie' ? 'active' : ''; ?>">
                    <i class="fas fa-sign-out-alt mr-2"></i>Pesage Sortie
                    <span class="ml-2 px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                        <?php echo count($camions_sortie); ?>
                    </span>
                    <?php if ($onglet_actif === 'sortie'): ?>
                        <span class="info-bulle ml-1">
                            <i class="fas fa-info-circle text-blue-500"></i>
                            <span class="info-text">Camions avec marchandises à peser (poids=0)</span>
                        </span>
                    <?php endif; ?>
                </button>
            </div>
        </div>
        
        <!-- Formulaire de recherche global -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-search mr-2"></i>Recherche de Camions
                </h2>
                
                <form method="GET" class="flex items-center space-x-2">
                    <input type="hidden" name="onglet" value="<?php echo $onglet_actif; ?>" id="onglet-input">
                    <div class="relative">
                        <input type="text" name="search" 
                               value="<?php echo safe_html($search_immat); ?>"
                               placeholder="Rechercher par immatriculation..."
                               class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-64">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">
                        Rechercher
                    </button>
                    <?php if (!empty($search_immat)): ?>
                        <a href="enregistrement.php?onglet=<?php echo $onglet_actif; ?>" class="text-gray-600 hover:text-gray-800 p-2">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Grille principale avec les deux sections côte à côte -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 grid-cols-1-2 mb-6">
            
            <!-- Section 1: Liste des camions -->
            <div class="bg-white shadow rounded-lg">
                <div class="p-4 border-b">
                    <h2 class="text-lg font-bold text-gray-800">
                        <i class="fas fa-truck mr-2"></i>
                        <?php echo $onglet_actif === 'entree' ? 'Camions Entrants' : 'Camions à Peser en Sortie'; ?>
                    </h2>
                    <p class="text-sm text-gray-600">
                        <?php echo $onglet_actif === 'entree' ? count($camions_entree) : count($camions_sortie); ?> camions trouvés
                        <?php if ($onglet_actif === 'sortie'): ?>
                            <span class="text-xs text-gray-500">(avec marchandises non pesées)</span>
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="scrollable-table">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="sticky-header bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chauffeur</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pesage</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            $camions_a_afficher = $onglet_actif === 'entree' ? $camions_entree : $camions_sortie;
                            foreach ($camions_a_afficher as $camion): 
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-150 
                                <?php echo $selected_camion_id == $camion['idEntree'] ? 'bg-blue-50 border-l-4 border-blue-500' : ''; ?>">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8 <?php echo $onglet_actif === 'entree' ? 'bg-blue-100' : 'bg-green-100'; ?> rounded-full flex items-center justify-center">
                                            <i class="fas fa-truck <?php echo $onglet_actif === 'entree' ? 'text-blue-600' : 'text-green-600'; ?> text-sm"></i>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo safe_html($camion['immatriculation']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php 
                                                if ($onglet_actif === 'entree') {
                                                    echo date('d/m/Y H:i', strtotime($camion['date_entree'] ?? '')); 
                                                } else {
                                                    echo date('d/m/Y H:i', strtotime($camion['date_chargement'] ?? '')); 
                                                }
                                                ?> - 
                                                <?php echo safe_html($camion['type_camion']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo safe_html(($camion['prenom_chauffeur'] ?? '') . ' ' . ($camion['nom_chauffeur'] ?? '')); ?>
                                </td>
                                
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if ($onglet_actif === 'sortie'): ?>
                                        <?php 
                                        // Déterminer l'état du pesage pour l'onglet sortie
                                        $hasNonPesee = isset($camion['a_marchandises_non_pesees']) && $camion['a_marchandises_non_pesees'];
                                        $hasPesage = isset($camion['date_pesage']) && $camion['date_pesage'] != '0000-00-00 00:00:00';
                                        
                                        if (!$hasPesage && $hasNonPesee): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                Non Pesé
                                            </span>
                                        <?php elseif ($hasPesage && $hasNonPesee): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-weight-scale mr-1"></i>
                                                Partiellement pesé
                                            </span>
                                        <?php elseif ($hasPesage && !$hasNonPesee): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo $camion['surcharge'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                <?php echo $camion['surcharge'] ? 'Surcharge' : 'Pesé'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                Inconnu
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Onglet entrée -->
                                        <?php if ($camion['date_pesage'] && $camion['date_pesage'] != '0000-00-00 00:00:00'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo $camion['surcharge'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                <i class="fas fa-weight-scale mr-1"></i>
                                                <?php echo $camion['surcharge'] ? 'Surcharge' : 'Pesé'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                À peser
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <?php if ($onglet_actif === 'sortie'): ?>
                                        <?php 
                                        $hasNonPesee = isset($camion['a_marchandises_non_pesees']) && $camion['a_marchandises_non_pesees'];
                                        $hasPesage = isset($camion['date_pesage']) && $camion['date_pesage'] != '0000-00-00 00:00:00';
                                        ?>
                                        <?php if (!$hasPesage && $hasNonPesee): ?>
                                            <!-- Pas de pesage et marchandises non pesées : bouton Peser -->
                                            <a href="enregistrement.php?select=<?php echo $camion['idEntree']; ?>&mode=edit&onglet=<?php echo $onglet_actif; ?><?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                               class="inline-flex items-center px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-full text-sm font-medium">
                                                <i class="fas fa-weight-scale mr-1"></i>Peser
                                            </a>
                                        <?php elseif ($hasPesage && $hasNonPesee): ?>
                                            <!-- Pesage existant mais encore des marchandises non pesées -->
                                            <a href="enregistrement.php?select=<?php echo $camion['idEntree']; ?>&mode=edit&onglet=<?php echo $onglet_actif; ?><?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                               class="inline-flex items-center px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-full text-sm font-medium">
                                                <i class="fas fa-weight-scale mr-1"></i>Peser
                                            </a>
                                        <?php elseif ($hasPesage && !$hasNonPesee): ?>
                                            <!-- Pesage complet : boutons Détails et Modifier -->
                                            <div class="flex flex-row space-x-2">
                                                <a href="enregistrement.php?select=<?php echo $camion['idEntree']; ?>&mode=view&onglet=<?php echo $onglet_actif; ?><?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                                   class="inline-flex items-center px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-full text-sm font-medium">
                                                    <i class="fas fa-eye mr-1"></i>Détails
                                                </a>
                                                <a href="enregistrement.php?select=<?php echo $camion['idEntree']; ?>&mode=edit&onglet=<?php echo $onglet_actif; ?><?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                                   class="inline-flex items-center px-3 py-1 bg-yellow-100 hover:bg-yellow-200 text-yellow-800 rounded-full text-sm font-medium">
                                                    <i class="fas fa-edit mr-1"></i>Modifier
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm">Aucune action</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Onglet entrée : code existant -->
                                        <?php if ($camion['date_pesage'] && $camion['date_pesage'] != '0000-00-00 00:00:00'): ?>
                                            <!-- Camion déjà pesé : deux boutons alignés horizontalement -->
                                            <div class="flex flex-row space-x-2">
                                                <a href="enregistrement.php?select=<?php echo $camion['idEntree']; ?>&mode=view&onglet=<?php echo $onglet_actif; ?><?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                                   class="inline-flex items-center px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-full text-sm font-medium">
                                                    <i class="fas fa-eye mr-1"></i>Détails
                                                </a>
                                                <a href="enregistrement.php?select=<?php echo $camion['idEntree']; ?>&mode=edit&onglet=<?php echo $onglet_actif; ?><?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                                   class="inline-flex items-center px-3 py-1 bg-yellow-100 hover:bg-yellow-200 text-yellow-800 rounded-full text-sm font-medium">
                                                    <i class="fas fa-edit mr-1"></i>Modifier
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <!-- Camion non pesé : un bouton -->
                                            <a href="enregistrement.php?select=<?php echo $camion['idEntree']; ?>&onglet=<?php echo $onglet_actif; ?><?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                               class="inline-flex items-center px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-full text-sm font-medium">
                                                <i class="fas fa-weight-scale mr-1"></i>Peser
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($camions_a_afficher)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                    <i class="fas fa-truck-loading text-3xl text-gray-300 mb-2 block"></i>
                                    Aucun camion à afficher
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Section 2: Zone de pesage -->
            <?php if ($selected_camion): ?>
            <div class="bg-white shadow rounded-lg">
                <div class="p-4 border-b">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-lg font-bold text-gray-800">
                                <i class="fas fa-weight mr-2"></i>
                                <?php echo ($mode == 'view') ? 'Consultation du Pesage' : 'Pesage du Camion'; ?>
                                <span class="ml-2 text-sm font-normal <?php echo $onglet_actif === 'entree' ? 'text-blue-600' : 'text-green-600'; ?>">
                                    (<?php echo $onglet_actif === 'entree' ? 'Entrée' : 'Sortie'; ?>)
                                </span>
                            </h2>
                            <div class="flex items-center space-x-2 mt-1">
                                <span class="text-sm font-medium text-gray-900">
                                    <?php echo safe_html($selected_camion['immatriculation']); ?>
                                </span>
                                <span class="text-gray-400">•</span>
                                <span class="text-sm text-gray-600">
                                    <?php echo safe_html($selected_camion['type_camion'] ?? ''); ?>
                                </span>
                                <?php if ($pesage_existant): ?>
                                    <span class="text-gray-400">•</span>
                                    <span class="text-xs <?php echo $pesage_existant['surcharge'] ? 'text-red-600' : 'text-green-600'; ?>">
                                        Dernier pesage: <?php echo date('d/m/Y H:i', strtotime($pesage_existant['date_pesage'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $onglet_actif === 'entree' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                <i class="fas <?php echo $onglet_actif === 'entree' ? 'fa-sign-in-alt' : 'fa-sign-out-alt'; ?> mr-1"></i>
                                <?php echo $onglet_actif === 'entree' ? 'Entrée' : 'Sortie'; ?>
                            </span>
                            <?php if ($mode == 'view'): ?>
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                                    <i class="fas fa-eye mr-1"></i>Mode consultation
                                </span>
                            <?php else: ?>
                                <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                                    <i class="fas fa-edit mr-1"></i>Mode édition
                                </span>
                            <?php endif; ?>
                            <a href="enregistrement.php?onglet=<?php echo $onglet_actif; ?><?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                               class="text-gray-400 hover:text-gray-600 p-2">
                                <i class="fas fa-times text-lg"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="p-4">
                    <form method="POST" id="pesageForm">
                        <input type="hidden" name="action" value="peser">
                        <input type="hidden" name="idEntree" value="<?php echo $selected_camion_id; ?>">
                        <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                        <input type="hidden" name="onglet" value="<?php echo $onglet_actif; ?>">
                        <?php if ($onglet_actif === 'sortie' && isset($selected_camion['idChargement'])): ?>
                            <input type="hidden" name="idChargement" value="<?php echo $selected_camion['idChargement']; ?>">
                        <?php endif; ?>
                        <input type="hidden" id="etat_camion" value="<?php echo safe_html($selected_camion['etat'] ?? ''); ?>">
                        <?php if ($pesage_existant): ?>
                            <input type="hidden" name="idPesage" value="<?php echo $pesage_existant['idPesage']; ?>">
                        <?php endif; ?>
                        
                        <!-- Informations rapides du camion -->
                        <div class="grid grid-cols-2 gap-3 mb-6">
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-xs text-gray-500">Chauffeur</p>
                                <p class="font-medium text-sm">
                                    <?php echo safe_html(($selected_camion['prenom_chauffeur'] ?? '') . ' ' . ($selected_camion['nom_chauffeur'] ?? '')); ?>
                                </p>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-xs text-gray-500">État / Raison</p>
                                <p class="font-medium text-sm">
                                    <?php echo safe_html($selected_camion['etat'] ?? ''); ?> / 
                                    <?php echo safe_html($selected_camion['raison'] ?? ''); ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Données de pesage -->
                        <div class="mb-6">
                            <h3 class="text-md font-bold text-gray-800 mb-3">Données de Pesage</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-gray-700 text-xs font-bold mb-1" for="ptav">
                                        PTAV (kg)
                                    </label>
                                    <input type="number" id="ptav" name="ptav" step="0.01" min="0"
                                           value="<?php echo $pesage_existant ? $pesage_existant['ptav'] : ''; ?>"
                                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           <?php echo ($mode == 'view' && $pesage_existant) ? 'readonly' : ''; ?>
                                           required>
                                    <p class="text-xs text-gray-400 mt-1">Poids Total à Vide</p>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-xs font-bold mb-1" for="ptac">
                                        PTAC (kg)
                                    </label>
                                    <input type="number" id="ptac" name="ptac" step="0.01" min="0"
                                           value="<?php echo $pesage_existant ? $pesage_existant['ptac'] : ''; ?>"
                                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           <?php echo ($mode == 'view' && $pesage_existant) ? 'readonly' : ''; ?>
                                           required>
                                    <p class="text-xs text-gray-400 mt-1">Poids Autorisé</p>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-xs font-bold mb-1" for="ptra">
                                        PTRA (kg)
                                    </label>
                                    <input type="number" id="ptra" name="ptra" step="0.01" min="0"
                                           value="<?php echo $pesage_existant ? $pesage_existant['ptra'] : ''; ?>"
                                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           <?php echo ($mode == 'view' && $pesage_existant) ? 'readonly' : ''; ?>
                                           required>
                                    <p class="text-xs text-gray-400 mt-1">Poids Roulant</p>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-xs font-bold mb-1" for="charge_essieu">
                                        Essieu (kg)
                                    </label>
                                    <input type="number" id="charge_essieu" name="charge_essieu" step="0.01" min="0"
                                           value="<?php echo $pesage_existant ? $pesage_existant['charge_essieu'] : ''; ?>"
                                           class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           <?php echo ($mode == 'view' && $pesage_existant) ? 'readonly' : ''; ?>>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Marchandises (toujours affiché pour la sortie, conditionnel pour l'entrée) -->
                        <?php if ($onglet_actif === 'entree' ? !$isCamionVide : true): ?>
                        <div class="mb-6" id="marchandisesSection">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-md font-bold text-gray-800">Marchandises</h3>
                                <?php if ($mode != 'view'): ?>
                                    <?php if ($onglet_actif === 'entree'): ?>
                                        <button type="button" id="addMarchandise" 
                                                class="bg-green-500 hover:bg-green-600 text-white text-sm font-bold py-2 px-3 rounded-lg">
                                            <i class="fas fa-plus mr-1"></i>Ajouter une marchandise
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($onglet_actif === 'sortie' && !empty($marchandises_chargement)): ?>
                                <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                        <div>
                                            <p class="text-sm font-medium text-blue-800 mb-1">
                                                Ce camion contient <?php echo count($marchandises_chargement); ?> marchandise(s) à peser.
                                            </p>
                                            <p class="text-xs text-blue-600">
                                                Veuillez saisir le poids réel de chaque marchandise dans les champs ci-dessous.
                                                <strong>Important :</strong> Ces poids seront enregistrés dans le système de chargement.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div id="marchandisesContainer" class="space-y-3 max-h-64 overflow-y-auto p-1">
                                <?php if (!empty($marchandises)): ?>
                                    <?php foreach ($marchandises as $index => $marchandise): ?>
                                    <div class="marchandise-item border rounded-lg p-3 <?php echo ($onglet_actif === 'sortie' && isset($marchandise['from_chargement'])) ? 'border-blue-300 bg-blue-50' : ''; ?>">
                                        <div class="flex justify-between items-center mb-2">
                                            <h4 class="font-medium text-gray-700 text-sm">
                                                Marchandise #<?php echo $index + 1; ?>
                                                <?php if ($onglet_actif === 'sortie' && isset($marchandise['from_chargement'])): ?>
                                                    <span class="ml-2 text-xs text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full">
                                                        <i class="fas fa-truck-loading mr-1"></i>Chargement
                                                    </span>
                                                <?php endif; ?>
                                            </h4>
                                            <?php if ($mode != 'view'): ?>
                                                <?php if ($onglet_actif === 'entree' || ($onglet_actif === 'sortie' && !isset($marchandise['from_chargement']))): ?>
                                                    <button type="button" class="remove-marchandise text-red-600 hover:text-red-800 text-sm">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <!-- En sortie, ne pas permettre de supprimer les marchandises du chargement -->
                                                    <span class="text-xs text-gray-400" title="Marchandise du chargement - non supprimable">
                                                        <i class="fas fa-lock"></i>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-gray-700 text-xs font-bold mb-1">
                                                    Type *
                                                </label>
                                                <select name="marchandises[<?php echo $index; ?>][type]" 
                                                        class="w-full px-2 py-1 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-blue-500" 
                                                        <?php echo ($mode == 'view') ? 'disabled' : ''; ?>
                                                        required>
                                                    <option value="">Sélectionner</option>
                                                    <?php foreach ($types_marchandises as $type): ?>
                                                        <option value="<?php echo $type['id']; ?>"
                                                            <?php echo isset($marchandise['idTypeMarchandise']) && $marchandise['idTypeMarchandise'] == $type['id'] ? 'selected' : ''; ?>>
                                                            <?php echo safe_html($type['nom']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-gray-700 text-xs font-bold mb-1">
                                                    Poids (kg) *
                                                </label>
                                                <input type="number" name="marchandises[<?php echo $index; ?>][poids]" 
                                                       step="0.01" min="0.01" value="<?php echo safe_html($marchandise['poids']); ?>"
                                                       class="marchandise-poids w-full px-2 py-1 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-blue-500 <?php echo ($onglet_actif === 'sortie' && empty($marchandise['poids'])) ? 'poids-manquant border-yellow-300 bg-yellow-50' : ''; ?>" 
                                                       <?php echo ($mode == 'view') ? 'readonly' : ''; ?>
                                                       placeholder="<?php echo $onglet_actif === 'sortie' && empty($marchandise['poids']) ? 'Saisir le poids réel (kg)' : 'Saisir le poids'; ?>"
                                                       required>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <input type="text" name="marchandises[<?php echo $index; ?>][note]" 
                                                   placeholder="Note (optionnel)" value="<?php echo safe_html($marchandise['note'] ?? ''); ?>"
                                                   class="w-full px-2 py-1 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                   <?php echo ($mode == 'view') ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div id="noMarchandises" class="text-center py-6 text-gray-400 border-2 border-dashed rounded-lg">
                                        <i class="fas fa-box-open text-xl mb-1"></i>
                                        <p class="text-sm">
                                            <?php if ($onglet_actif === 'entree'): ?>
                                                Cliquez sur "Ajouter" pour commencer
                                            <?php else: ?>
                                                Aucune marchandise à peser pour ce camion
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div id="marchandisesSummary" class="mt-3 p-2 bg-gray-50 rounded text-sm <?php echo empty($marchandises) ? 'hidden' : ''; ?>">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total marchandises:</span>
                                    <span id="totalWeight" class="font-bold">
                                        <?php 
                                        if (!empty($marchandises)) {
                                            $total = array_sum(array_column($marchandises, 'poids'));
                                            echo number_format($total, 2) . ' kg';
                                        } else {
                                            echo '0 kg';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="flex justify-between mt-1">
                                    <span class="text-gray-600">Nombre:</span>
                                    <span id="totalCount" class="font-bold"><?php echo count($marchandises); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Résultats et note -->
                        <div class="mb-6">
                            <h3 class="text-md font-bold text-gray-800 mb-3">Résultats</h3>
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div id="poidsTotalContainer" class="text-center p-3 border rounded-lg 
                                    <?php if ($mode == 'view' && $pesage_existant): ?>
                                        <?php echo $surcharge_view ? 'border-yellow-300 bg-yellow-50' : 'border-blue-300 bg-blue-50'; ?>
                                    <?php else: ?>
                                        border-gray-200 bg-gray-50
                                    <?php endif; ?>">
                                    <p class="text-xs text-gray-600 mb-1">Poids total</p>
                                    <p id="poidsTotalCamion" class="text-xl font-bold">
                                        <?php if ($mode == 'view' && $pesage_existant): ?>
                                            <?php echo number_format($poids_total_camion_view, 2); ?> kg
                                        <?php else: ?>
                                            -- kg
                                        <?php endif; ?>
                                    </p>
                                </div>
                                
                                <div id="surchargeContainer" class="text-center p-3 border rounded-lg
                                    <?php if ($mode == 'view' && $pesage_existant): ?>
                                        <?php echo $surcharge_view ? 'border-red-300 bg-red-50' : 'border-green-300 bg-green-50'; ?>
                                    <?php else: ?>
                                        border-gray-200 bg-gray-50
                                    <?php endif; ?>">
                                    <p class="text-xs text-gray-600 mb-1">État</p>
                                    <p id="surchargeStatus" class="text-xl font-bold 
                                        <?php if ($mode == 'view' && $pesage_existant): ?>
                                            <?php echo $surcharge_view ? 'text-red-600' : 'text-green-600'; ?>
                                        <?php else: ?>
                                            text-gray-400
                                        <?php endif; ?>">
                                        <?php if ($mode == 'view' && $pesage_existant): ?>
                                            <?php echo $surcharge_view ? 'SURCHARGE' : 'CONFORME'; ?>
                                        <?php else: ?>
                                            --
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-xs font-bold mb-1" for="note_surcharge">
                                    Note sur la surcharge (optionnel)
                                </label>
                                <textarea id="note_surcharge" name="note_surcharge" rows="2"
                                          class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                          <?php echo ($mode == 'view' && $pesage_existant) ? 'readonly' : ''; ?>
                                          placeholder="Détails sur la surcharge, observations..."><?php echo safe_html($pesage_existant['note_surcharge'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Boutons d'action -->
                        <div class="flex justify-end space-x-3">
                            <?php if ($mode == 'view' && $pesage_existant): ?>
                                <!-- Mode consultation : bouton Modifier -->
                                <a href="enregistrement.php?select=<?php echo $selected_camion_id; ?>&mode=edit&onglet=<?php echo $onglet_actif; ?><?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                   class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg text-sm">
                                    <i class="fas fa-edit mr-1"></i>Modifier le pesage
                                </a>
                            <?php else: ?>
                                <!-- Mode édition : boutons Calculer et Enregistrer -->
                                <button type="button" id="calculateBtn" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg text-sm">
                                    <i class="fas fa-calculator mr-1"></i>Calculer
                                </button>
                                <button type="submit" 
                                        class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg text-sm">
                                    <i class="fas fa-save mr-1"></i>Enregistrer le pesage
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Détails du pesage existant -->
                <?php if ($pesage_existant && !empty($marchandises) && ($onglet_actif === 'entree' ? !$isCamionVide : true)): ?>
                <div class="border-t p-4">
                    <h3 class="text-md font-bold text-gray-800 mb-3">
                        <i class="fas fa-list mr-1"></i>Détails des Marchandises
                    </h3>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        <?php foreach ($marchandises as $marchandise): ?>
                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                            <div>
                                <span class="font-medium text-sm"><?php echo safe_html($marchandise['type_marchandise']); ?></span>
                                <?php if (!empty($marchandise['note'])): ?>
                                    <p class="text-xs text-gray-500"><?php echo safe_html($marchandise['note']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="font-bold text-sm"><?php echo number_format($marchandise['poids'], 2); ?> kg</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <!-- Section vide quand aucun camion n'est sélectionné -->
            <div class="bg-white shadow rounded-lg p-8 text-center flex flex-col items-center justify-center h-full">
                <i class="fas fa-weight-scale text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-bold text-gray-700 mb-2">Aucun camion sélectionné</h3>
                <p class="text-gray-600">Sélectionnez un camion dans la liste pour effectuer le pesage.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Variables pour gérer les marchandises
        let marchandiseIndex = <?php echo !empty($marchandises) ? count($marchandises) : 0; ?>;
        const typesMarchandises = <?php echo json_encode($types_marchandises); ?>;
        const isViewMode = <?php echo ($mode == 'view') ? 'true' : 'false'; ?>;
        const isCamionVide = <?php echo $isCamionVide ? 'true' : 'false'; ?>;
        const ongletActif = '<?php echo $onglet_actif; ?>';
        
        // Données pour le mode view
        const viewPoidsTotal = <?php echo $poids_total_camion_view; ?>;
        const viewSurcharge = <?php echo $surcharge_view ? 'true' : 'false'; ?>;
        const viewPoidsMarchandises = <?php echo $poids_total_marchandises_view; ?>;
        
        // Fonction pour changer d'onglet
        function changerOnglet(onglet) {
            // Mettre à jour l'input caché
            document.getElementById('onglet-input').value = onglet;
            
            // Rediriger avec le nouvel onglet
            const searchParams = new URLSearchParams(window.location.search);
            searchParams.set('onglet', onglet);
            
            // Garder la recherche si elle existe
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && searchInput.value) {
                searchParams.set('search', searchInput.value);
            }
            
            window.location.href = 'enregistrement.php?' + searchParams.toString();
        }
        
        // Fonction pour ajouter un champ de marchandise
        function addMarchandiseField() {
            if (isViewMode) return;
            
            const container = document.getElementById('marchandisesContainer');
            const noMarchandises = document.getElementById('noMarchandises');
            
            // Masquer le message "aucune marchandise"
            if (noMarchandises) {
                noMarchandises.style.display = 'none';
            }
            
            // Déterminer si on est en sortie
            const isSortie = ongletActif === 'sortie';
            
            // Créer le nouvel élément
            const newField = document.createElement('div');
            newField.className = 'marchandise-item border rounded-lg p-3';
            if (isSortie) {
                newField.classList.add('border-purple-300', 'bg-purple-50');
            }
            
            newField.innerHTML = `
                <div class="flex justify-between items-center mb-2">
                    <h4 class="font-medium text-gray-700 text-sm">
                        Marchandise #${marchandiseIndex + 1}
                        ${isSortie ? '<span class="ml-2 text-xs text-purple-600 bg-purple-100 px-2 py-0.5 rounded-full"><i class="fas fa-plus-circle mr-1"></i>Supplémentaire</span>' : ''}
                    </h4>
                    <button type="button" class="remove-marchandise text-red-600 hover:text-red-800 text-sm">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 text-xs font-bold mb-1">
                            Type *
                        </label>
                        <select name="marchandises[${marchandiseIndex}][type]" 
                                class="w-full px-2 py-1 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-blue-500" required>
                            <option value="">Sélectionner</option>
                            ${typesMarchandises.map(type => 
                                `<option value="${escapeHtml(type.id)}">${escapeHtml(type.nom)}</option>`
                            ).join('')}
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-xs font-bold mb-1">
                            Poids (kg) *
                        </label>
                        <input type="number" name="marchandises[${marchandiseIndex}][poids]" 
                               step="0.01" min="0.01" value=""
                               class="marchandise-poids w-full px-2 py-1 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-blue-500" 
                               placeholder="Saisir le poids"
                               required>
                    </div>
                </div>
                <div class="mt-2">
                    <input type="text" name="marchandises[${marchandiseIndex}][note]" 
                           placeholder="Note (optionnel)"
                           class="w-full px-2 py-1 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
            `;
            
            container.appendChild(newField);
            marchandiseIndex++;
            
            // Faire défiler vers le nouveau champ
            newField.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            
            // Mettre à jour le résumé
            updateMarchandisesSummary();
            
            // Ajouter l'événement pour le nouveau champ de poids
            const poidsInput = newField.querySelector('.marchandise-poids');
            poidsInput.addEventListener('input', function() {
                updateMarchandisesSummary();
            });
        }
        
        // Fonction pour échapper le HTML
        function escapeHtml(str) {
            if (!str) return '';
            return str.toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
        
        // Fonction pour supprimer un champ de marchandise
        function removeMarchandiseField(button) {
            if (isViewMode) return;
            
            const item = button.closest('.marchandise-item');
            item.remove();
            
            // Renumérotter les marchandises
            const items = document.querySelectorAll('.marchandise-item');
            items.forEach((item, index) => {
                const title = item.querySelector('h4');
                title.textContent = `Marchandise #${index + 1}`;
            });
            
            // Mettre à jour le résumé
            updateMarchandisesSummary();
            
            // Si plus de marchandises, afficher le message
            if (items.length === 0 && document.getElementById('noMarchandises')) {
                document.getElementById('noMarchandises').style.display = 'block';
            }
        }
        
        // Fonction pour mettre à jour le résumé des marchandises
        function updateMarchandisesSummary() {
            const poidsInputs = document.querySelectorAll('.marchandise-poids');
            let totalWeight = 0;
            let validCount = 0;
            
            poidsInputs.forEach(input => {
                const poids = parseFloat(input.value) || 0;
                if (poids > 0) {
                    totalWeight += poids;
                    validCount++;
                }
            });
            
            const summary = document.getElementById('marchandisesSummary');
            if (summary) {
                const totalCount = document.getElementById('totalCount');
                const totalWeightElement = document.getElementById('totalWeight');
                
                if (validCount > 0) {
                    summary.classList.remove('hidden');
                    totalCount.textContent = validCount;
                    totalWeightElement.textContent = totalWeight.toFixed(2) + ' kg';
                } else {
                    summary.classList.add('hidden');
                }
            }
            
            return totalWeight;
        }
        
        // Fonction pour calculer les résultats du pesage
        function calculatePesage() {
            if (isViewMode) {
                // En mode view, utiliser les données calculées côté PHP
                document.getElementById('poidsTotalCamion').textContent = viewPoidsTotal.toFixed(2) + ' kg';
                document.getElementById('surchargeStatus').textContent = viewSurcharge ? 'SURCHARGE' : 'CONFORME';
                document.getElementById('surchargeStatus').className = `text-xl font-bold ${viewSurcharge ? 'text-red-600' : 'text-green-600'}`;
                
                const surchargeContainer = document.getElementById('surchargeContainer');
                const poidsTotalContainer = document.getElementById('poidsTotalContainer');
                
                if (viewSurcharge) {
                    surchargeContainer.className = 'text-center p-3 border-2 rounded-lg border-red-300 bg-red-50';
                    poidsTotalContainer.className = 'text-center p-3 border-2 rounded-lg border-yellow-300 bg-yellow-50';
                } else {
                    surchargeContainer.className = 'text-center p-3 border-2 rounded-lg border-green-300 bg-green-50';
                    poidsTotalContainer.className = 'text-center p-3 border-2 rounded-lg border-blue-300 bg-blue-50';
                }
                
                return { isSurcharge: viewSurcharge, poidsTotalCamion: viewPoidsTotal };
            }
            
            const ptav = parseFloat(document.getElementById('ptav').value) || 0;
            const ptac = parseFloat(document.getElementById('ptac').value) || 0;
            const ptra = parseFloat(document.getElementById('ptra').value) || 0;
            
            let totalMarchandises = 0;
            if ((ongletActif === 'entree' ? !isCamionVide : true)) {
                totalMarchandises = updateMarchandisesSummary();
            }
            
            const poidsTotalCamion = ptav + totalMarchandises;
            
            // Mettre à jour l'affichage
            document.getElementById('poidsTotalCamion').textContent = poidsTotalCamion.toFixed(2) + ' kg';
            
            const surchargeStatus = document.getElementById('surchargeStatus');
            const surchargeContainer = document.getElementById('surchargeContainer');
            const poidsTotalContainer = document.getElementById('poidsTotalContainer');
            
            // Vérifier la surcharge
            let isSurcharge = false;
            let message = 'CALCULER';
            let statusColor = 'text-gray-400';
            let containerColor = 'border-gray-200';
            let bgColor = 'bg-gray-50';
            
            if (ptav > 0 && ptac > 0 && ptra > 0) {
                if (poidsTotalCamion > ptac || poidsTotalCamion > ptra) {
                    isSurcharge = true;
                    message = 'SURCHARGE';
                    statusColor = 'text-red-600';
                    containerColor = 'border-red-300';
                    bgColor = 'bg-red-50';
                } else {
                    message = 'CONFORME';
                    statusColor = 'text-green-600';
                    containerColor = 'border-green-300';
                    bgColor = 'bg-green-50';
                }
                
                // Mettre à jour la couleur du container de poids total
                if (isSurcharge) {
                    poidsTotalContainer.className = 'text-center p-3 border-2 rounded-lg border-yellow-300 bg-yellow-50';
                } else {
                    poidsTotalContainer.className = 'text-center p-3 border-2 rounded-lg border-blue-300 bg-blue-50';
                }
            }
            
            surchargeStatus.textContent = message;
            surchargeStatus.className = `text-xl font-bold ${statusColor}`;
            surchargeContainer.className = `text-center p-3 border-2 rounded-lg ${containerColor} ${bgColor}`;
            
            return { isSurcharge, poidsTotalCamion, ptac, ptra };
        }
        
        // Fonction pour valider le formulaire
        function validateForm(e) {
            const ptav = document.getElementById('ptav').value;
            const ptac = document.getElementById('ptac').value;
            const ptra = document.getElementById('ptra').value;
            const etatCamion = document.getElementById('etat_camion')?.value?.toLowerCase() || '';
            
            if (!ptav || !ptac || !ptra) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires de pesage (PTAV, PTAC, PTRA)');
                return false;
            }
            
            // Si le camion n'est pas vide ou si c'est la sortie, vérifier les marchandises
            if ((ongletActif === 'entree' ? !isCamionVide : true)) {
                const poidsInputs = document.querySelectorAll('.marchandise-poids');
                let hasValidMarchandises = false;
                poidsInputs.forEach(input => {
                    if (input.value && parseFloat(input.value) > 0) {
                        hasValidMarchandises = true;
                    }
                });
                
                if (!hasValidMarchandises) {
                    if (ongletActif === 'sortie') {
                        e.preventDefault();
                        alert('Pour le pesage de sortie, vous devez saisir le poids d\'au moins une marchandise.');
                        return false;
                    } else {
                        if (!confirm('Aucune marchandise avec un poids valide n\'a été saisie. Voulez-vous continuer ?')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                }
            }
            
            return true;
        }
        
        // Événements
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion des onglets
            document.querySelectorAll('.tab-button').forEach(button => {
                button.addEventListener('click', function() {
                    const onglet = this.getAttribute('data-tab');
                    changerOnglet(onglet);
                });
            });
            
            // Configuration spécifique pour l'onglet sortie
            if (ongletActif === 'sortie' && !isViewMode) {
                // Pour la sortie, les marchandises sont pré-remplies depuis le chargement
                // On ne permet pas d'ajouter de nouvelles marchandises (sauf si besoin spécifique)
                const addBtn = document.getElementById('addExtraMarchandise');
                if (addBtn) {
                    // Le bouton est déjà configuré avec un message d'alerte
                }
                
                // Ajouter un message si aucune marchandise
                const noMarchandises = document.getElementById('noMarchandises');
                if (noMarchandises && marchandiseIndex === 0) {
                    noMarchandises.innerHTML = `
                        <i class="fas fa-box text-xl mb-1"></i>
                        <p class="text-sm">
                            Aucune marchandise à peser pour ce camion.
                            <br>
                            <span class="text-xs">Si le camion transporte des marchandises, contactez l'administration.</span>
                        </p>
                    `;
                }
                
                // Mettre en évidence les champs de poids à remplir
                const poidsInputs = document.querySelectorAll('.marchandise-poids');
                poidsInputs.forEach(input => {
                    if (parseFloat(input.value) === 0) {
                        input.classList.add('poids-manquant', 'border-yellow-300', 'bg-yellow-50');
                        input.placeholder = "Saisir le poids réel (kg)";
                    }
                });
                
                // Ajouter la validation spécifique pour la sortie
                const form = document.getElementById('pesageForm');
                if (form) {
                    const originalSubmit = form.onsubmit;
                    form.onsubmit = function(e) {
                        if (!validateForm(e)) {
                            return false;
                        }
                        
                        // Validation spécifique pour la sortie
                        if (ongletActif === 'sortie') {
                            const poidsInputs = document.querySelectorAll('.marchandise-poids');
                            let hasUnweighedItems = false;
                            let hasValidMarchandises = false;
                            
                            poidsInputs.forEach(input => {
                                const poids = parseFloat(input.value) || 0;
                                if (poids === 0) {
                                    hasUnweighedItems = true;
                                    input.classList.add('border-red-300', 'bg-red-50');
                                } else if (poids > 0) {
                                    hasValidMarchandises = true;
                                }
                            });
                            
                            if (hasUnweighedItems) {
                                if (!confirm('Certaines marchandises ont un poids de 0 kg. Voulez-vous continuer sans peser toutes les marchandises ?')) {
                                    e.preventDefault();
                                    return false;
                                }
                            }
                            
                            if (!hasValidMarchandises) {
                                e.preventDefault();
                                alert('Veuillez saisir le poids d\'au moins une marchandise pour le pesage de sortie.');
                                return false;
                            }
                        }
                        
                        return true;
                    };
                }
            }
            
            if (isViewMode) {
                // En mode view, afficher directement les résultats
                calculatePesage();
                
                // Désactiver le bouton de calcul
                const calculateBtn = document.getElementById('calculateBtn');
                if (calculateBtn) {
                    calculateBtn.disabled = true;
                    calculateBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
                
                // Désactiver l'ajout de marchandises
                const addMarchandiseBtn = document.getElementById('addMarchandise');
                if (addMarchandiseBtn) {
                    addMarchandiseBtn.disabled = true;
                    addMarchandiseBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
                
                const addExtraBtn = document.getElementById('addExtraMarchandise');
                if (addExtraBtn) {
                    addExtraBtn.disabled = true;
                    addExtraBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            } else {
                // Bouton pour ajouter une marchandise (entrée)
                const addBtn = document.getElementById('addMarchandise');
                if (addBtn) {
                    addBtn.addEventListener('click', addMarchandiseField);
                }
                
                // Bouton de calcul
                const calculateBtn = document.getElementById('calculateBtn');
                if (calculateBtn) {
                    calculateBtn.addEventListener('click', calculatePesage);
                }
                
                // Écouter les changements dans les champs de poids
                if ((ongletActif === 'entree' ? !isCamionVide : true)) {
                    document.addEventListener('input', function(e) {
                        if (e.target.classList.contains('marchandise-poids')) {
                            updateMarchandisesSummary();
                        }
                    });
                }
                
                // Écouter les changements dans les champs de pesage
                ['ptav', 'ptac', 'ptra', 'charge_essieu'].forEach(id => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.addEventListener('input', function() {
                            if ((ongletActif === 'entree' ? !isCamionVide : true)) {
                                updateMarchandisesSummary();
                            }
                        });
                    }
                });
                
                // Calcul initial si des données existent
                if (document.getElementById('ptav').value) {
                    calculatePesage();
                }
            }
            
            // Délégation d'événements pour les boutons de suppression
            if ((ongletActif === 'entree' ? !isCamionVide : true)) {
                document.addEventListener('click', function(e) {
                    if (e.target.closest('.remove-marchandise')) {
                        removeMarchandiseField(e.target.closest('.remove-marchandise'));
                    }
                });
            }
            
            // Mettre à jour le résumé initial
            if ((ongletActif === 'entree' ? !isCamionVide : true)) {
                updateMarchandisesSummary();
            }
            
            // Valider le formulaire avant soumission
            const form = document.getElementById('pesageForm');
            if (form && !isViewMode) {
                form.addEventListener('submit', validateForm);
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