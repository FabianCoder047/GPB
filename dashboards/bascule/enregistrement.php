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

// Recherche par immatriculation
$search_immat = $_GET['search'] ?? '';

// Récupérer le mode (view ou edit)
$mode = $_GET['mode'] ?? null;

// Traitement de la sélection d'un camion
if (isset($_GET['select']) && is_numeric($_GET['select'])) {
    $selected_camion_id = $_GET['select'];
    
    try {
        // Récupérer les informations du camion
        $stmt = $conn->prepare("
            SELECT ce.*, tc.nom as type_camion, p.nom as port 
            FROM camions_entrants ce
            LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
            LEFT JOIN port p ON ce.idPort = p.id
            WHERE ce.idEntree = ?
        ");
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
                    }
                }
            }
            
            $success = "Pesage enregistré avec succès!";
            
            // Rediriger en mode view après enregistrement
            if ($selected_camion_id) {
                header("Location: enregistrement.php?select=" . $selected_camion_id . "&mode=view");
                exit();
            }
            
        } catch (Exception $e) {
            $error = "Erreur lors de l'enregistrement du pesage: " . $e->getMessage();
        }
    }
}

// Récupérer la liste des camions avec filtre de recherche
$camions = [];
$types_marchandises = [];

// Récupérer les camions chargés (pour la nouvelle section)
$camions_charges_non_peses = [];
$camions_charges_peses = [];

try {
    // Récupérer les types de marchandises
    $result = $conn->query("SELECT * FROM type_marchandise ORDER BY nom");
    $types_marchandises = $result->fetch_all(MYSQLI_ASSOC);
    
    // REQUÊTE CORRIGÉE : Récupérer les camions chargés séparément
    $query_charges = "
        SELECT ce.*, tc.nom as type_camion, p.nom as port, 
               ps.poids_total_marchandises, ps.surcharge, ps.date_pesage,
               ps.idPesage
        FROM camions_entrants ce
        LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
        LEFT JOIN port p ON ce.idPort = p.id
        LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
        WHERE LOWER(ce.etat) = 'chargé'
    ";
    
    if (!empty($search_immat)) {
        $query_charges .= " AND ce.immatriculation LIKE ?";
    }
    
    $query_charges .= " ORDER BY ce.date_entree DESC LIMIT 20";
    
    if (!empty($search_immat)) {
        $stmt = $conn->prepare($query_charges);
        $stmt->bind_param("s", '%' . $search_immat . '%');
        $stmt->execute();
        $result_charges = $stmt->get_result();
    } else {
        $result_charges = $conn->query($query_charges);
    }
    
    $camions_charges = $result_charges->fetch_all(MYSQLI_ASSOC);
    
    // Séparer les camions pesés et non pesés
    foreach ($camions_charges as $camion) {
        // Vérifier si un pesage existe (idPesage n'est pas NULL et date_pesage est valide)
        if (!empty($camion['idPesage']) && !empty($camion['date_pesage']) && $camion['date_pesage'] != '0000-00-00 00:00:00') {
            $camions_charges_peses[] = $camion;
        } else {
            $camions_charges_non_peses[] = $camion;
        }
    }
    
    // Récupérer aussi tous les camions pour la première liste (tous les états)
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
    
    $camions = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
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
        
        <!-- Formulaire de recherche global -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-search mr-2"></i>Recherche de Camions
                </h2>
                
                <form method="GET" class="flex items-center space-x-2">
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
                        <a href="enregistrement.php" class="text-gray-600 hover:text-gray-800 p-2">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Grille principale avec les deux sections côte à côte -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 grid-cols-1-2 mb-6">
            
            <!-- Section 1: Liste des camions entrants -->
            <div class="bg-white shadow rounded-lg">
                <div class="p-4 border-b">
                    <h2 class="text-lg font-bold text-gray-800">
                        <i class="fas fa-truck mr-2"></i>Camions Entrants
                    </h2>
                    <p class="text-sm text-gray-600"><?php echo count($camions); ?> camions trouvés</p>
                </div>
                
                <div class="scrollable-table">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="sticky-header bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chauffeur</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">État</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pesage</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($camions as $camion): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-150 
                                <?php echo $selected_camion_id == $camion['idEntree'] ? 'bg-blue-50 border-l-4 border-blue-500' : ''; ?>">
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
                                                <?php echo date('d/m/Y H:i', strtotime($camion['date_entree'] ?? '')); ?> - 
                                                <?php echo safe_html($camion['type_camion']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo safe_html(($camion['prenom_chauffeur'] ?? '') . ' ' . ($camion['nom_chauffeur'] ?? '')); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo ($camion['etat'] ?? '') == 'Chargé' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo safe_html($camion['etat'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
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
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <?php if ($camion['date_pesage'] && $camion['date_pesage'] != '0000-00-00 00:00:00'): ?>
                                        <!-- Camion déjà pesé : deux boutons alignés horizontalement -->
                                        <div class="flex flex-row space-x-2">
                                            <a href="enregistrement.php?select=<?php echo $camion['idEntree']; ?>&mode=view<?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                               class="inline-flex items-center px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-full text-sm font-medium">
                                                <i class="fas fa-eye mr-1"></i>Détails
                                            </a>
                                            <a href="enregistrement.php?select=<?php echo $camion['idEntree']; ?>&mode=edit<?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                               class="inline-flex items-center px-3 py-1 bg-yellow-100 hover:bg-yellow-200 text-yellow-800 rounded-full text-sm font-medium">
                                                <i class="fas fa-edit mr-1"></i>Modifier
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <!-- Camion non pesé : un bouton -->
                                        <a href="enregistrement.php?select=<?php echo $camion['idEntree']; ?><?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                           class="inline-flex items-center px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-full text-sm font-medium">
                                            <i class="fas fa-weight-scale mr-1"></i>Peser
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($camions)): ?>
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
                            <?php if ($mode == 'view'): ?>
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                                    <i class="fas fa-eye mr-1"></i>Mode consultation
                                </span>
                            <?php else: ?>
                                <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                                    <i class="fas fa-edit mr-1"></i>Mode édition
                                </span>
                            <?php endif; ?>
                            <a href="enregistrement.php<?php echo !empty($search_immat) ? '?search=' . urlencode($search_immat) : ''; ?>" 
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
                        
                        <!-- Marchandises (caché si camion vide) -->
                        <?php if (!$isCamionVide): ?>
                        <div class="mb-6" id="marchandisesSection">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-md font-bold text-gray-800">Marchandises</h3>
                                <?php if ($mode != 'view'): ?>
                                    <button type="button" id="addMarchandise" 
                                            class="bg-green-500 hover:bg-green-600 text-white text-sm font-bold py-2 px-3 rounded-lg">
                                        <i class="fas fa-plus mr-1"></i>Ajouter
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <div id="marchandisesContainer" class="space-y-3 max-h-64 overflow-y-auto p-1">
                                <?php if (!empty($marchandises)): ?>
                                    <?php foreach ($marchandises as $index => $marchandise): ?>
                                    <div class="marchandise-item border rounded-lg p-3">
                                        <div class="flex justify-between items-center mb-2">
                                            <h4 class="font-medium text-gray-700 text-sm">Marchandise #<?php echo $index + 1; ?></h4>
                                            <?php if ($mode != 'view'): ?>
                                                <button type="button" class="remove-marchandise text-red-600 hover:text-red-800 text-sm">
                                                    <i class="fas fa-times"></i>
                                                </button>
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
                                                       class="marchandise-poids w-full px-2 py-1 text-sm border rounded focus:outline-none focus:ring-1 focus:ring-blue-500" 
                                                       <?php echo ($mode == 'view') ? 'readonly' : ''; ?>
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
                                        <p class="text-sm">Cliquez sur "Ajouter" pour commencer</p>
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
                                <a href="enregistrement.php?select=<?php echo $selected_camion_id; ?>&mode=edit<?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
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
                                    <i class="fas fa-save mr-1"></i>Enregistrer
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Détails du pesage existant -->
                <?php if ($pesage_existant && !empty($marchandises) && !$isCamionVide): ?>
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
        
        <!-- NOUVELLE SECTION : Camions récemment chargés -->
        <div class="mt-8">
            <h2 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-truck-loading mr-2"></i>Camions Récemment Chargés
            </h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Section 1: Camions chargés à peser -->
                <div class="bg-white shadow rounded-lg">
                    <div class="p-4 border-b">
                        <h3 class="text-md font-bold text-gray-800">
                            <i class="fas fa-clock mr-2"></i>À Peser (Chargés)
                        </h3>
                        <p class="text-sm text-gray-600">Camions chargés en attente de pesage</p>
                    </div>
                    <div class="scrollable-table-small">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="sticky-header bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chauffeur</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Entrée</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($camions_charges_non_peses)): ?>
                                    <?php foreach ($camions_charges_non_peses as $camion): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-150">
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
                                                        <?php echo safe_html($camion['type_camion'] ?? ''); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo safe_html(($camion['prenom_chauffeur'] ?? '') . ' ' . ($camion['nom_chauffeur'] ?? '')); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('d/m/Y H:i', strtotime($camion['date_entree'] ?? '')); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            <a href="enregistrement.php?select=<?php echo $camion['idEntree']; ?><?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                               class="inline-flex items-center px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-full text-sm font-medium">
                                                <i class="fas fa-weight-scale mr-1"></i>Peser
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">
                                        <i class="fas fa-check-circle text-2xl text-gray-300 mb-2 block"></i>
                                        Aucun camion chargé à peser
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-t text-center">
                        <span class="text-xs text-gray-500">
                            <?php echo count($camions_charges_non_peses); ?> camion(s) à peser
                            <?php if (!empty($search_immat)): ?>
                                <br><span class="text-blue-500">(filtre: <?php echo safe_html($search_immat); ?>)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Section 2: Camions chargés déjà pesés -->
                <div class="bg-white shadow rounded-lg">
                    <div class="p-4 border-b">
                        <h3 class="text-md font-bold text-gray-800">
                            <i class="fas fa-check-circle mr-2"></i>Déjà Pesés (Chargés)
                        </h3>
                        <p class="text-sm text-gray-600">Camions chargés avec pesage effectué</p>
                    </div>
                    <div class="scrollable-table-small">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="sticky-header bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Pesage</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">État</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($camions_charges_peses)): ?>
                                    <?php foreach ($camions_charges_peses as $camion): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-150">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-8 w-8 <?php echo $camion['surcharge'] ? 'bg-red-100' : 'bg-green-100'; ?> rounded-full flex items-center justify-center">
                                                    <i class="fas fa-truck <?php echo $camion['surcharge'] ? 'text-red-600' : 'text-green-600'; ?> text-sm"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo safe_html($camion['immatriculation']); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo safe_html($camion['type_camion'] ?? ''); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('d/m/Y H:i', strtotime($camion['date_pesage'] ?? '')); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo $camion['surcharge'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                <?php echo $camion['surcharge'] ? 'Surcharge' : 'Conforme'; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            <div class="flex flex-row space-x-2">
                                                <a href="enregistrement.php?select=<?php echo $camion['idEntree']; ?>&mode=view<?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                                   class="inline-flex items-center px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-full text-sm font-medium">
                                                    <i class="fas fa-eye mr-1"></i>Détails
                                                </a>
                                                <a href="enregistrement.php?select=<?php echo $camion['idEntree']; ?>&mode=edit<?php echo !empty($search_immat) ? '&search=' . urlencode($search_immat) : ''; ?>" 
                                                   class="inline-flex items-center px-3 py-1 bg-yellow-100 hover:bg-yellow-200 text-yellow-800 rounded-full text-sm font-medium">
                                                    <i class="fas fa-edit mr-1"></i>Modifier
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">
                                        <i class="fas fa-truck-loading text-2xl text-gray-300 mb-2 block"></i>
                                        Aucun camion chargé pesé
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-t text-center">
                        <span class="text-xs text-gray-500">
                            <?php echo count($camions_charges_peses); ?> camion(s) pesé(s)
                            <?php if (!empty($search_immat)): ?>
                                <br><span class="text-blue-500">(filtre: <?php echo safe_html($search_immat); ?>)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
            </div>
        </div>
        
        <!-- Debug section (optionnel - à supprimer en production) -->
        <?php if (false): // Mettre à true pour activer le debug ?>
        <div class="mt-8 bg-yellow-50 p-4 rounded-lg">
            <h3 class="font-bold text-yellow-800">Debug info:</h3>
            <div class="grid grid-cols-2 gap-4 mt-2">
                <div>
                    <h4>Camions chargés non pesés:</h4>
                    <pre class="text-xs"><?php echo count($camions_charges_non_peses); ?> camions</pre>
                    <?php foreach ($camions_charges_non_peses as $cam): ?>
                        <div class="text-xs"><?php echo $cam['immatriculation'] . ' - ' . $cam['etat'] . ' - date_pesage: ' . ($cam['date_pesage'] ?? 'NULL'); ?></div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <h4>Camions chargés pesés:</h4>
                    <pre class="text-xs"><?php echo count($camions_charges_peses); ?> camions</pre>
                    <?php foreach ($camions_charges_peses as $cam): ?>
                        <div class="text-xs"><?php echo $cam['immatriculation'] . ' - ' . $cam['etat'] . ' - date_pesage: ' . ($cam['date_pesage'] ?? 'NULL'); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
    
    <script>
        // Variables pour gérer les marchandises
        let marchandiseIndex = <?php echo !empty($marchandises) ? count($marchandises) : 0; ?>;
        const typesMarchandises = <?php echo json_encode($types_marchandises); ?>;
        const isViewMode = <?php echo ($mode == 'view') ? 'true' : 'false'; ?>;
        const isCamionVide = <?php echo $isCamionVide ? 'true' : 'false'; ?>;
        
        // Données pour le mode view
        const viewPoidsTotal = <?php echo $poids_total_camion_view; ?>;
        const viewSurcharge = <?php echo $surcharge_view ? 'true' : 'false'; ?>;
        const viewPoidsMarchandises = <?php echo $poids_total_marchandises_view; ?>;
        
        // Fonction pour ajouter un champ de marchandise
        function addMarchandiseField() {
            if (isViewMode) return;
            
            const container = document.getElementById('marchandisesContainer');
            const noMarchandises = document.getElementById('noMarchandises');
            
            // Masquer le message "aucune marchandise"
            if (noMarchandises) {
                noMarchandises.style.display = 'none';
            }
            
            // Créer le nouvel élément
            const newField = document.createElement('div');
            newField.className = 'marchandise-item border rounded-lg p-3';
            newField.innerHTML = `
                <div class="flex justify-between items-center mb-2">
                    <h4 class="font-medium text-gray-700 text-sm">Marchandise #${marchandiseIndex + 1}</h4>
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
                                `<option value="${type.id}">${escapeHtml(type.nom)}</option>`
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
            if (!isCamionVide) {
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
        
        // Événements
        document.addEventListener('DOMContentLoaded', function() {
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
            } else {
                // Bouton pour ajouter une marchandise (seulement si camion n'est pas vide)
                const addBtn = document.getElementById('addMarchandise');
                if (addBtn && !isCamionVide) {
                    addBtn.addEventListener('click', addMarchandiseField);
                }
                
                // Bouton de calcul
                const calculateBtn = document.getElementById('calculateBtn');
                if (calculateBtn) {
                    calculateBtn.addEventListener('click', calculatePesage);
                }
                
                // Écouter les changements dans les champs de poids (seulement si camion n'est pas vide)
                if (!isCamionVide) {
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
                            if (!isCamionVide) {
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
            
            // Délégation d'événements pour les boutons de suppression (seulement si camion n'est pas vide)
            if (!isCamionVide) {
                document.addEventListener('click', function(e) {
                    if (e.target.closest('.remove-marchandise')) {
                        removeMarchandiseField(e.target.closest('.remove-marchandise'));
                    }
                });
            }
            
            // Mettre à jour le résumé initial (seulement si camion n'est pas vide)
            if (!isCamionVide) {
                updateMarchandisesSummary();
            }
            
            // Valider le formulaire avant soumission
            const form = document.getElementById('pesageForm');
            if (form && !isViewMode) {
                form.addEventListener('submit', function(e) {
                    const ptav = document.getElementById('ptav').value;
                    const ptac = document.getElementById('ptac').value;
                    const ptra = document.getElementById('ptra').value;
                    const etatCamion = document.getElementById('etat_camion')?.value?.toLowerCase() || '';
                    
                    if (!ptav || !ptac || !ptra) {
                        e.preventDefault();
                        alert('Veuillez remplir tous les champs obligatoires de pesage (PTAV, PTAC, PTRA)');
                        return false;
                    }
                    
                    // Si le camion n'est pas vide, vérifier les marchandises
                    if (!isCamionVide && etatCamion !== 'vide') {
                        const poidsInputs = document.querySelectorAll('.marchandise-poids');
                        let hasMarchandises = false;
                        poidsInputs.forEach(input => {
                            if (input.value && parseFloat(input.value) > 0) {
                                hasMarchandises = true;
                            }
                        });
                        
                        if (!hasMarchandises) {
                            if (!confirm('Aucune marchandise n\'a été ajoutée. Voulez-vous continuer ?')) {
                                e.preventDefault();
                                return false;
                            }
                        }
                    }
                    
                    return true;
                });
            }
        });
    </script>
</body>
</html>