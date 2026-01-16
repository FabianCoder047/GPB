<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'enregistreurSortieBateau') {
    header("Location: ../../login.php");
    exit();
}

// Fonction utilitaire pour éviter les erreurs de dépréciation
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Variables pour stocker les données du bateau sélectionné
$selected_bateau = null;
$selected_bateau_id = null;
$marchandises = [];

// Récupérer le mode (view ou edit)
$mode = $_GET['mode'] ?? null;

// Traitement de la sélection d'un bateau
if (isset($_GET['select']) && is_numeric($_GET['select'])) {
    $selected_bateau_id = $_GET['select'];
    
    try {
        // Récupérer les informations du bateau avec les noms des types et ports de destination
        $stmt = $conn->prepare("
            SELECT 
                bs.*, 
                tb.nom as type_bateau, 
                p.nom as destination_port_nom, 
                p.id as destination_port_id
            FROM bateau_sortant bs
            LEFT JOIN type_bateau tb ON bs.id_type_bateau = tb.id
            LEFT JOIN port p ON bs.id_destination_port = p.id
            WHERE bs.id = ?
        ");
        $stmt->bind_param("i", $selected_bateau_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $selected_bateau = $result->fetch_assoc();
            
            // Récupérer les marchandises associées
            $stmt = $conn->prepare("
                SELECT mbs.*, tm.nom as type_marchandise, tm.id as id_type_marchandise
                FROM marchandise_bateau_sortant mbs
                LEFT JOIN type_marchandise tm ON mbs.id_type_marchandise = tm.id
                WHERE mbs.id_bateau_sortant = ?
                ORDER BY mbs.date_ajout DESC
            ");
            $stmt->bind_param("i", $selected_bateau_id);
            $stmt->execute();
            $marchandises_result = $stmt->get_result();
            if ($marchandises_result->num_rows > 0) {
                $marchandises = $marchandises_result->fetch_all(MYSQLI_ASSOC);
            }
            
            // Si le mode n'est pas spécifié, mettre 'view' par défaut
            if ($mode === null) {
                $mode = 'view';
            }
        }
    } catch (Exception $e) {
        $error = "Erreur lors du chargement des données: " . $e->getMessage();
    }
}

// Traitement du formulaire de sortie de bateau
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $selected_bateau_id = $_POST['id_bateau'] ?? null;
    $mode = $_POST['mode'] ?? 'edit';
    
    if ($_POST['action'] === 'enregistrer') {
        try {
            $id_type_bateau = $_POST['id_type_bateau'] ?? null;
            $nom_navire = $_POST['nom_navire'] ?? '';
            $immatriculation = $_POST['immatriculation'] ?? '';
            $nom_capitaine = $_POST['nom_capitaine'] ?? '';
            $prenom_capitaine = $_POST['prenom_capitaine'] ?? '';
            $tel_capitaine = $_POST['tel_capitaine'] ?? '';
            $agence = $_POST['agence'] ?? '';
            $hauteur = $_POST['hauteur'] ?? 0;
            $longueur = $_POST['longueur'] ?? 0;
            $largeur = $_POST['largeur'] ?? 0;
            $id_destination_port = $_POST['id_destination_port'] ?? null;
            $etat = trim($_POST['etat'] ?? 'vide');
            if ($etat === '') {
                $etat = 'vide';
            }
            $note = $_POST['note'] ?? '';
            $date_sortie = date('Y-m-d H:i:s');
            
            $conn->set_charset('utf8mb4');
            
            if ($selected_bateau_id && isset($_POST['id_sortie']) && !empty($_POST['id_sortie'])) {
                // Mise à jour de la sortie existante
                $stmt = $conn->prepare("
                    UPDATE bateau_sortant 
                    SET id_type_bateau = ?, nom_navire = ?, immatriculation = ?, 
                        nom_capitaine = ?, prenom_capitaine = ?, tel_capitaine = ?, 
                        agence = ?, hauteur = ?, longueur = ?, largeur = ?, 
                        id_destination_port = ?, etat = ?, note = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("issssssdddissi", 
                    $id_type_bateau, $nom_navire, $immatriculation,
                    $nom_capitaine, $prenom_capitaine, $tel_capitaine,
                    $agence, $hauteur, $longueur, $largeur,
                    $id_destination_port, $etat, $note, $selected_bateau_id
                );
                if (!$stmt->execute()) {
                    throw new Exception("Erreur lors de la mise à jour: " . $stmt->error);
                }
                $idSortie = $selected_bateau_id;
                
                // Supprimer les anciennes marchandises si le bateau passe à vide
                if ($etat == 'vide') {
                    $stmt = $conn->prepare("DELETE FROM marchandise_bateau_sortant WHERE id_bateau_sortant = ?");
                    $stmt->bind_param("i", $selected_bateau_id);
                    $stmt->execute();
                }
            } else {
                // Nouvelle sortie de bateau
                $agent_name = $user['prenom'] . ' ' . $user['nom'];
                $stmt = $conn->prepare("
                    INSERT INTO bateau_sortant 
                    (id_type_bateau, nom_navire, immatriculation, nom_capitaine, 
                     prenom_capitaine, tel_capitaine, agence, hauteur, longueur, 
                     largeur, id_destination_port, etat, note, date_sortie, agent_enregistrement)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param("issssssdddissss", 
                    $id_type_bateau, $nom_navire, $immatriculation,
                    $nom_capitaine, $prenom_capitaine, $tel_capitaine,
                    $agence, $hauteur, $longueur, $largeur,
                    $id_destination_port, $etat, $note, $date_sortie, $agent_name
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Erreur lors de l'insertion du bateau: " . $stmt->error);
                }
                $idSortie = $stmt->insert_id;
            }
            
            // Insérer les marchandises seulement si le bateau est chargé
            if ($etat == 'chargé' && isset($_POST['marchandises']) && is_array($_POST['marchandises'])) {
                // Supprimer d'abord les anciennes marchandises
                $stmt = $conn->prepare("DELETE FROM marchandise_bateau_sortant WHERE id_bateau_sortant = ?");
                $stmt->bind_param("i", $idSortie);
                $stmt->execute();
                
                foreach ($_POST['marchandises'] as $marchandise) {
                    if (!empty($marchandise['type']) && $marchandise['type'] > 0) {
                        $type_id = intval($marchandise['type']);
                        $poids = !empty($marchandise['poids']) ? floatval($marchandise['poids']) : null;
                        $note_marchandise = $marchandise['note'] ?? '';
                        
                        $stmt = $conn->prepare("
                            INSERT INTO marchandise_bateau_sortant 
                            (id_bateau_sortant, id_type_marchandise, poids, note)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->bind_param("iids", $idSortie, $type_id, $poids, $note_marchandise);
                        $stmt->execute();
                    }
                }
            }
            
            $success = "Sortie de bateau enregistrée avec succès!";
            
            // Rediriger en mode view après enregistrement
            if ($idSortie) {
                header("Location: enregistrement.php?select=" . $idSortie . "&mode=view");
                exit();
            }
            
        } catch (Exception $e) {
            $error = "Erreur lors de l'enregistrement: " . $e->getMessage();
        }
    }
}

// Récupérer les types de marchandises, types de bateaux et ports (pour destination)
$types_marchandises = [];
$types_bateaux = [];
$ports = [];

// Récupérer les enregistrements récents
$recent_bateaux = [];
$search = $_GET['search'] ?? '';

try {
    // Récupérer les types de marchandises
    $result = $conn->query("SELECT * FROM type_marchandise ORDER BY nom");
    $types_marchandises = $result->fetch_all(MYSQLI_ASSOC);
    
    // Récupérer les types de bateaux
    $result = $conn->query("SELECT * FROM type_bateau ORDER BY nom");
    $types_bateaux = $result->fetch_all(MYSQLI_ASSOC);
    
    // Récupérer les ports (pour destination)
    $result = $conn->query("SELECT * FROM port ORDER BY nom");
    $ports = $result->fetch_all(MYSQLI_ASSOC);
    
    // Récupérer les enregistrements récents avec les noms des ports de destination
    $sql = "SELECT bs.*, tb.nom as type_bateau, p.nom as destination_port_nom
            FROM bateau_sortant bs 
            LEFT JOIN type_bateau tb ON bs.id_type_bateau = tb.id
            LEFT JOIN port p ON bs.id_destination_port = p.id";
    
    if (!empty($search)) {
        $sql .= " WHERE bs.nom_navire LIKE ? OR bs.immatriculation LIKE ?";
        $stmt = $conn->prepare($sql);
        $search_param = "%$search%";
        $stmt->bind_param("ss", $search_param, $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql .= " ORDER BY bs.date_sortie DESC LIMIT 10";
        $result = $conn->query($sql);
    }
    
    $recent_bateaux = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
}

// Déterminer si le bateau sélectionné est vide
$isBateauVide = false;
if ($selected_bateau && isset($selected_bateau['etat'])) {
    $isBateauVide = ($selected_bateau['etat'] == 'vide');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enregistrement des Sorties de Bateaux</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .marchandise-item {
            transition: all 0.3s ease;
        }
        
        .marchandise-item:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        input:read-only, select:disabled, textarea:read-only {
            background-color: #f3f4f6;
            cursor: not-allowed;
        }
        
        .recent-bateau-row:hover {
            background-color: #f8fafc;
            cursor: pointer;
        }
        
        .step {
            display: none;
        }
        
        .step.active {
            display: block;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            flex: 1;
        }
        
        .step-item:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 12px;
            right: -50%;
            width: 100%;
            height: 2px;
            background-color: #e5e7eb;
            z-index: 0;
        }
        
        .step-number {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e5e7eb;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
            z-index: 1;
            position: relative;
        }
        
        .step-item.active .step-number {
            background-color: #3b82f6;
            color: white;
        }
        
        .step-item.completed .step-number {
            background-color: #10b981;
            color: white;
        }
        
        .step-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: #6b7280;
        }
        
        .step-item.active .step-label {
            color: #3b82f6;
            font-weight: 600;
        }
        
        .step-item.completed .step-label {
            color: #10b981;
        }
        
        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .marchandises-container {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .compact-section {
            margin-bottom: 1rem !important;
        }
        
        .compact-label {
            margin-bottom: 0.25rem !important;
            font-size: 0.75rem !important;
        }
        
        .compact-input {
            padding-top: 0.375rem !important;
            padding-bottom: 0.375rem !important;
            font-size: 0.875rem !important;
        }
        
        .compact-title {
            font-size: 0.875rem !important;
            margin-bottom: 0.5rem !important;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-gray-100 min-h-screen">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-2">
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-3 py-2 rounded mb-2 text-sm">
                <?php echo safe_html($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-3 py-2 rounded mb-2 text-sm">
                <?php echo safe_html($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Grille principale avec deux sections -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            
            <!-- SECTION GAUCHE : Formulaire d'enregistrement -->
            <div class="bg-white shadow rounded-lg">
                <div class="p-3 border-b">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-base font-bold text-gray-800">
                                <i class="fas fa-ship mr-1"></i>
                                <?php echo ($selected_bateau && $mode == 'view') ? 'Consultation Sortie Bateau' : ($selected_bateau ? 'Modification Sortie Bateau' : 'Nouvelle Sortie Bateau'); ?>
                            </h2>
                            <?php if ($selected_bateau): ?>
                                <div class="flex items-center space-x-1 mt-0.5">
                                    <span class="text-xs font-medium text-gray-900">
                                        <?php echo safe_html($selected_bateau['nom_navire']); ?>
                                    </span>
                                    <span class="text-gray-400">•</span>
                                    <span class="text-xs text-gray-600">
                                        <?php echo safe_html($selected_bateau['immatriculation']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center space-x-1">
                            <?php if ($mode == 'view'): ?>
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-0.5 rounded">
                                    <i class="fas fa-eye mr-0.5"></i>Consultation
                                </span>
                            <?php else: ?>
                                <span class="bg-green-100 text-green-800 text-xs font-semibold px-2 py-0.5 rounded">
                                    <i class="fas fa-edit mr-0.5"></i>Édition
                                </span>
                            <?php endif; ?>
                            <?php if ($selected_bateau): ?>
                                <a href="enregistrement.php" 
                                   class="text-gray-400 hover:text-gray-600 p-1">
                                    <i class="fas fa-times text-sm"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Indicateur d'étapes -->
                <?php if ($mode != 'view'): ?>
                <div class="px-3 pt-2">
                    <div class="step-indicator">
                        <div class="step-item active" data-step="1">
                            <div class="step-number">1</div>
                            <div class="step-label">Infos</div>
                        </div>
                        <div class="step-item" data-step="2">
                            <div class="step-number">2</div>
                            <div class="step-label">Marchandises</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="p-3">
                    <form method="POST" id="bateauForm">
                        <input type="hidden" name="action" value="enregistrer">
                        <?php if ($selected_bateau): ?>
                            <input type="hidden" name="id_bateau" value="<?php echo $selected_bateau_id; ?>">
                            <input type="hidden" name="id_sortie" value="<?php echo $selected_bateau['id']; ?>">
                        <?php endif; ?>
                        <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                        
                        <!-- ÉTAPE 1 : Informations générales -->
                        <div id="step1" class="step <?php echo ($mode != 'view') ? 'active' : ''; ?>">
                            <!-- Informations du bateau -->
                            <div class="compact-section">
                                <h3 class="compact-title font-bold text-gray-800">Informations du Bateau</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <div>
                                        <label class="compact-label block text-gray-700 font-bold" for="nom_navire">
                                            Nom du navire *
                                        </label>
                                        <input type="text" id="nom_navire" name="nom_navire"
                                               value="<?php echo safe_html($selected_bateau['nom_navire'] ?? ''); ?>"
                                               class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                               <?php echo ($mode == 'view') ? 'readonly' : ''; ?>
                                               required>
                                    </div>
                                    
                                    <div>
                                        <label class="compact-label block text-gray-700 font-bold" for="immatriculation">
                                            Immatriculation
                                        </label>
                                        <input type="text" id="immatriculation" name="immatriculation"
                                               value="<?php echo safe_html($selected_bateau['immatriculation'] ?? ''); ?>"
                                               class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                               <?php echo ($mode == 'view') ? 'readonly' : ''; ?>>
                                    </div>
                                    
                                    <div>
                                        <label class="compact-label block text-gray-700 font-bold" for="id_type_bateau">
                                            Type de bateau *
                                        </label>
                                        <select id="id_type_bateau" name="id_type_bateau"
                                                class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                <?php echo ($mode == 'view') ? 'disabled' : ''; ?>
                                                required>
                                            <option value="">Sélectionner un type</option>
                                            <?php foreach ($types_bateaux as $type): ?>
                                                <option value="<?php echo $type['id']; ?>"
                                                    <?php echo isset($selected_bateau['id_type_bateau']) && $selected_bateau['id_type_bateau'] == $type['id'] ? 'selected' : ''; ?>>
                                                    <?php echo safe_html($type['nom']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="compact-label block text-gray-700 font-bold" for="etat">
                                            État *
                                        </label>
                                        <select id="etat" name="etat"
                                                class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                <?php echo ($mode == 'view') ? 'disabled' : ''; ?>
                                                required>
                                            <option value="">Sélectionner un état</option>
                                            <option value="vide" <?php echo ($selected_bateau['etat'] ?? '') == 'vide' ? 'selected' : ''; ?>>Vide</option>
                                            <option value="chargé" <?php echo ($selected_bateau['etat'] ?? '') == 'chargé' ? 'selected' : ''; ?>>Chargé</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="compact-label block text-gray-700 font-bold" for="id_destination_port">
                                            Port de destination *
                                        </label>
                                        <select id="id_destination_port" name="id_destination_port"
                                                class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                <?php echo ($mode == 'view') ? 'disabled' : ''; ?>
                                                required>
                                            <option value="">Sélectionner un port</option>
                                            <?php foreach ($ports as $port): ?>
                                                <option value="<?php echo $port['id']; ?>"
                                                    <?php echo isset($selected_bateau['id_destination_port']) && $selected_bateau['id_destination_port'] == $port['id'] ? 'selected' : ''; ?>>
                                                    <?php echo safe_html($port['nom']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="compact-label block text-gray-700 font-bold" for="agence">
                                            Agence
                                        </label>
                                        <input type="text" id="agence" name="agence"
                                               value="<?php echo safe_html($selected_bateau['agence'] ?? ''); ?>"
                                               class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                               <?php echo ($mode == 'view') ? 'readonly' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Dimensions du bateau -->
                            <div class="compact-section">
                                <h3 class="compact-title font-bold text-gray-800">Dimensions (mètres)</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                    <div>
                                        <label class="compact-label block text-gray-700 font-bold" for="longueur">
                                            Longueur
                                        </label>
                                        <input type="number" id="longueur" name="longueur" step="0.01" min="0"
                                               value="<?php echo safe_html($selected_bateau['longueur'] ?? ''); ?>"
                                               class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                               <?php echo ($mode == 'view') ? 'readonly' : ''; ?>>
                                    </div>
                                    
                                    <div>
                                        <label class="compact-label block text-gray-700 font-bold" for="largeur">
                                            Largeur
                                        </label>
                                        <input type="number" id="largeur" name="largeur" step="0.01" min="0"
                                               value="<?php echo safe_html($selected_bateau['largeur'] ?? ''); ?>"
                                               class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                               <?php echo ($mode == 'view') ? 'readonly' : ''; ?>>
                                    </div>
                                    
                                    <div>
                                        <label class="compact-label block text-gray-700 font-bold" for="hauteur">
                                            Hauteur
                                        </label>
                                        <input type="number" id="hauteur" name="hauteur" step="0.01" min="0"
                                               value="<?php echo safe_html($selected_bateau['hauteur'] ?? ''); ?>"
                                               class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                               <?php echo ($mode == 'view') ? 'readonly' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Informations du capitaine -->
                            <div class="compact-section">
                                <h3 class="compact-title font-bold text-gray-800">Capitaine</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                    <div>
                                        <label class="compact-label block text-gray-700 font-bold" for="nom_capitaine">
                                            Nom *
                                        </label>
                                        <input type="text" id="nom_capitaine" name="nom_capitaine"
                                               value="<?php echo safe_html($selected_bateau['nom_capitaine'] ?? ''); ?>"
                                               class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                               <?php echo ($mode == 'view') ? 'readonly' : ''; ?>
                                               required>
                                    </div>
                                    
                                    <div>
                                        <label class="compact-label block text-gray-700 font-bold" for="prenom_capitaine">
                                            Prénom *
                                        </label>
                                        <input type="text" id="prenom_capitaine" name="prenom_capitaine"
                                               value="<?php echo safe_html($selected_bateau['prenom_capitaine'] ?? ''); ?>"
                                               class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                               <?php echo ($mode == 'view') ? 'readonly' : ''; ?>
                                               required>
                                    </div>
                                    
                                    <div>
                                        <label class="compact-label block text-gray-700 font-bold" for="tel_capitaine">
                                            Téléphone
                                        </label>
                                        <input type="text" id="tel_capitaine" name="tel_capitaine"
                                               value="<?php echo safe_html($selected_bateau['tel_capitaine'] ?? ''); ?>"
                                               class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                               <?php echo ($mode == 'view') ? 'readonly' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Boutons de navigation étape 1 -->
                            <?php if ($mode != 'view'): ?>
                            <div class="flex justify-end space-x-2 mt-4">
                                <a href="enregistrement.php" 
                                   class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-1.5 px-3 rounded text-xs">
                                    Annuler
                                </a>
                                <button type="button" id="nextStep" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-1.5 px-3 rounded text-xs">
                                    Suivant <i class="fas fa-arrow-right ml-0.5"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- ÉTAPE 2 : Marchandises et notes -->
                        <div id="step2" class="step">
                            <!-- Marchandises (caché si bateau vide) -->
                            <div class="compact-section" id="marchandisesSection">
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="compact-title font-bold text-gray-800">Marchandises</h3>
                                    <?php if ($mode != 'view'): ?>
                                        <button type="button" id="addMarchandise" 
                                                class="bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-1 px-2 rounded">
                                            <i class="fas fa-plus mr-0.5"></i>Ajouter
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div id="marchandisesContainer" class="marchandises-container space-y-2 p-1">
                                    <?php if (!empty($marchandises)): ?>
                                        <?php foreach ($marchandises as $index => $marchandise): ?>
                                        <div class="marchandise-item border rounded p-2">
                                            <div class="flex justify-between items-center mb-1">
                                                <h4 class="font-medium text-gray-700 text-xs">#<?php echo $index + 1; ?></h4>
                                                <?php if ($mode != 'view'): ?>
                                                    <button type="button" class="remove-marchandise text-red-600 hover:text-red-800 text-xs">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                                <div>
                                                    <label class="compact-label block text-gray-700 font-bold">
                                                        Type *
                                                    </label>
                                                    <select name="marchandises[<?php echo $index; ?>][type]" 
                                                            class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500" 
                                                            <?php echo ($mode == 'view') ? 'disabled' : ''; ?>
                                                            required>
                                                        <option value="">Sélectionner</option>
                                                        <?php foreach ($types_marchandises as $type): ?>
                                                            <option value="<?php echo $type['id']; ?>"
                                                                <?php echo isset($marchandise['id_type_marchandise']) && $marchandise['id_type_marchandise'] == $type['id'] ? 'selected' : ''; ?>>
                                                                <?php echo safe_html($type['nom']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="compact-label block text-gray-700 font-bold">
                                                        Poids (t) *
                                                    </label>
                                                    <input type="number" name="marchandises[<?php echo $index; ?>][poids]" 
                                                           step="0.01" min="0" value="<?php echo safe_html($marchandise['poids'] ?? ''); ?>"
                                                           class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500" 
                                                           <?php echo ($mode == 'view') ? 'readonly' : ''; ?>
                                                           required>
                                                </div>
                                            </div>
                                            <div class="mt-1">
                                                <label class="compact-label block text-gray-700 font-bold">
                                                    Note
                                                </label>
                                                <input type="text" name="marchandises[<?php echo $index; ?>][note]" 
                                                       placeholder="Note (optionnel)" value="<?php echo safe_html($marchandise['note'] ?? ''); ?>"
                                                       class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                       <?php echo ($mode == 'view') ? 'readonly' : ''; ?>>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div id="noMarchandises" class="text-center py-4 text-gray-400 border-2 border-dashed rounded">
                                            <i class="fas fa-box-open text-lg mb-1"></i>
                                            <p class="text-xs">Aucune marchandise</p>
                                            <?php if ($mode != 'view'): ?>
                                                <p class="text-xs mt-0.5">Cliquez sur "Ajouter"</p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Note générale -->
                            <div class="compact-section">
                                <label class="compact-label block text-gray-700 font-bold" for="note">
                                    Notes et observations
                                </label>
                                <textarea id="note" name="note" rows="2"
                                          class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                                          <?php echo ($mode == 'view') ? 'readonly' : ''; ?>
                                          placeholder="Notes supplémentaires..."><?php echo safe_html($selected_bateau['note'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Boutons d'action étape 2 -->
                            <?php if ($mode == 'view' && $selected_bateau): ?>
                                <!-- Mode consultation : bouton Modifier -->
                                <div class="flex justify-end space-x-2">
                                    <a href="enregistrement.php?select=<?php echo $selected_bateau_id; ?>&mode=edit" 
                                       class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1.5 px-3 rounded text-xs">
                                        <i class="fas fa-edit mr-0.5"></i>Modifier
                                    </a>
                                </div>
                            <?php elseif ($mode != 'view'): ?>
                                <!-- Mode édition : boutons Précédent et Enregistrer -->
                                <div class="flex justify-between space-x-2">
                                    <button type="button" id="prevStep" 
                                            class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-1.5 px-3 rounded text-xs">
                                        <i class="fas fa-arrow-left mr-0.5"></i>Précédent
                                    </button>
                                    <div class="flex space-x-2">
                                        <a href="enregistrement.php" 
                                           class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-1.5 px-3 rounded text-xs">
                                            Annuler
                                        </a>
                                        <button type="submit" 
                                                class="bg-green-500 hover:bg-green-600 text-white font-bold py-1.5 px-3 rounded text-xs">
                                            <i class="fas fa-save mr-0.5"></i>Enregistrer
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Détails des marchandises existantes -->
<?php if ($selected_bateau): ?>
<div class="border-t p-3">
    <h3 class="compact-title font-bold text-gray-800">
        <i class="fas fa-list mr-1"></i>Résumé Marchandises
    </h3>
    <div class="space-y-1 max-h-32 overflow-y-auto">
        <?php if (!empty($marchandises)): ?>
            <?php foreach ($marchandises as $marchandise): ?>
            <div class="flex justify-between items-center p-1.5 bg-gray-50 rounded text-xs">
                <div>
                    <span class="font-medium"><?php echo safe_html($marchandise['type_marchandise'] ?? 'Type inconnu'); ?></span>
                    <?php if (!empty($marchandise['note'])): ?>
                        <p class="text-gray-500"><?php echo safe_html($marchandise['note']); ?></p>
                    <?php endif; ?>
                </div>
                <span class="font-bold">
                    <?php echo !empty($marchandise['poids']) ? number_format($marchandise['poids'], 2) . ' t' : '-'; ?>
                </span>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-4 text-gray-400 text-xs">
                <i class="fas fa-box-open text-lg mb-1 block"></i>
                <p>Aucune marchandise</p>
                <?php if ($selected_bateau['etat'] == 'vide'): ?>
                    <p class="text-xs mt-0.5 text-gray-400">Bateau vide</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
            </div>
            
            <!-- SECTION DROITE : Liste des enregistrements récents -->
            <div class="bg-white shadow rounded-lg">
                <div class="p-3 border-b">
                    <div class="flex justify-between items-center">
                        <h2 class="text-base font-bold text-gray-800">
                            <i class="fas fa-list mr-1"></i>Sorties Récents
                        </h2>
                        <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-0.5 rounded">
                            <?php echo count($recent_bateaux); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Barre de recherche -->
                <div class="p-3 border-b">
                    <form method="GET" action="" class="flex space-x-1">
                        <div class="flex-1">
                            <input type="text" name="search" 
                                   value="<?php echo safe_html($search); ?>"
                                   placeholder="Rechercher nom ou immatriculation..."
                                   class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>
                        <button type="submit" 
                                class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-2 rounded text-xs">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="enregistrement.php" 
                               class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-1 px-2 rounded text-xs">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Tableau des enregistrements récents -->
                <div class="p-2">
                    <div class="table-container">
                        <table class="w-full text-xs text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th class="py-2 px-2">Navire</th>
                                    <th class="py-2 px-2">Immat.</th>
                                    <th class="py-2 px-2">Type</th>
                                    <th class="py-2 px-2">Destination</th>
                                    <th class="py-2 px-2">État</th>
                                    <th class="py-2 px-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_bateaux)): ?>
                                    <?php foreach ($recent_bateaux as $bateau): ?>
                                        <tr class="border-b recent-bateau-row <?php echo $selected_bateau_id == $bateau['id'] ? 'bg-blue-50' : ''; ?>">
                                            <td class="py-2 px-2 font-medium text-gray-900">
                                                <?php echo safe_html($bateau['nom_navire']); ?>
                                            </td>
                                            <td class="py-2 px-2">
                                                <?php echo safe_html($bateau['immatriculation']); ?>
                                            </td>
                                            <td class="py-2 px-2">
                                                <?php echo safe_html($bateau['type_bateau'] ?? '-'); ?>
                                            </td>
                                            <td class="py-2 px-2">
                                                <?php echo safe_html($bateau['destination_port_nom'] ?? '-'); ?>
                                            </td>
                                            <td class="py-2 px-2">
                                                <span class="px-1.5 py-0.5 text-xs font-semibold uppercase rounded-full 
                                                    <?php echo $bateau['etat'] == 'chargé' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                    <?php echo safe_html($bateau['etat']); ?>
                                                </span>
                                            </td>
                                            <td class="py-2 px-2">
                                                <div class="flex space-x-1">
                                                    <a href="enregistrement.php?select=<?php echo $bateau['id']; ?>&mode=view" 
                                                       class="text-green-600 hover:text-green-800 text-xs"
                                                       title="Consulter">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="enregistrement.php?select=<?php echo $bateau['id']; ?>&mode=edit" 
                                                       class="text-yellow-600 hover:text-yellow-800 text-xs"
                                                       title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-gray-500 text-xs">
                                            <i class="fas fa-ship text-xl mb-1 block text-gray-300"></i>
                                            <p>Aucun bateau trouvé</p>
                                            <?php if (!empty($search)): ?>
                                                <p class="text-xs mt-0.5">Essayez d'autres termes</p>
                                            <?php else: ?>
                                                <p class="text-xs mt-0.5">Enregistrez une nouvelle sortie</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Variables pour gérer les marchandises
        let marchandiseIndex = <?php echo !empty($marchandises) ? count($marchandises) : 0; ?>;
        const typesMarchandises = <?php echo json_encode($types_marchandises); ?>;
        const isViewMode = <?php echo ($mode == 'view') ? 'true' : 'false'; ?>;
        let currentStep = 1;
        
        // Fonction pour changer d'étape
        function goToStep(step) {
            // Masquer toutes les étapes
            document.querySelectorAll('.step').forEach(el => {
                el.classList.remove('active');
            });
            
            // Afficher l'étape demandée
            document.getElementById('step' + step).classList.add('active');
            
            // Mettre à jour les indicateurs d'étape
            document.querySelectorAll('.step-item').forEach(el => {
                el.classList.remove('active', 'completed');
                
                const stepNumber = parseInt(el.getAttribute('data-step'));
                if (stepNumber === step) {
                    el.classList.add('active');
                } else if (stepNumber < step) {
                    el.classList.add('completed');
                }
            });
            
            currentStep = step;
        }
        
        // Fonction pour valider l'étape 1
        function validateStep1() {
            const requiredFields = [
                'nom_navire',
                'id_type_bateau',
                'etat',
                'id_destination_port',
                'nom_capitaine',
                'prenom_capitaine'
            ];
            
            for (const fieldId of requiredFields) {
                const field = document.getElementById(fieldId);
                if (!field.value.trim()) {
                    alert(`Veuillez remplir le champ : ${field.previousElementSibling.textContent}`);
                    field.focus();
                    return false;
                }
            }
            
            return true;
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
            
            // Créer le nouvel élément
            const newField = document.createElement('div');
            newField.className = 'marchandise-item border rounded p-2';
            newField.innerHTML = `
                <div class="flex justify-between items-center mb-1">
                    <h4 class="font-medium text-gray-700 text-xs">#${marchandiseIndex + 1}</h4>
                    <button type="button" class="remove-marchandise text-red-600 hover:text-red-800 text-xs">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div>
                        <label class="compact-label block text-gray-700 font-bold">
                            Type *
                        </label>
                        <select name="marchandises[${marchandiseIndex}][type]" 
                                class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500" required>
                            <option value="">Sélectionner</option>
                            ${typesMarchandises.map(type => 
                                `<option value="${type.id}">${escapeHtml(type.nom)}</option>`
                            ).join('')}
                        </select>
                    </div>
                    <div>
                        <label class="compact-label block text-gray-700 font-bold">
                            Poids (t) *
                        </label>
                        <input type="number" name="marchandises[${marchandiseIndex}][poids]" 
                               step="0.01" min="0" value=""
                               class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500" 
                               required>
                    </div>
                </div>
                <div class="mt-1">
                    <label class="compact-label block text-gray-700 font-bold">
                        Note
                    </label>
                    <input type="text" name="marchandises[${marchandiseIndex}][note]" 
                           placeholder="Note (optionnel)"
                           class="compact-input w-full px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
            `;
            
            container.appendChild(newField);
            marchandiseIndex++;
            
            // Faire défiler vers le nouveau champ
            newField.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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
                title.textContent = `#${index + 1}`;
            });
            
            // Si plus de marchandises, afficher le message
            if (items.length === 0 && document.getElementById('noMarchandises')) {
                document.getElementById('noMarchandises').style.display = 'block';
            }
        }
        
        // Événements
        document.addEventListener('DOMContentLoaded', function() {
            // Bouton pour ajouter une marchandise
            const addBtn = document.getElementById('addMarchandise');
            if (addBtn && !isViewMode) {
                addBtn.addEventListener('click', addMarchandiseField);
            }
            
            // Délégation d'événements pour les boutons de suppression
            if (!isViewMode) {
                document.addEventListener('click', function(e) {
                    if (e.target.closest('.remove-marchandise')) {
                        removeMarchandiseField(e.target.closest('.remove-marchandise'));
                    }
                });
            }
            
            // Afficher/masquer la section marchandises en fonction de l'état
            const etatSelect = document.getElementById('etat');
            if (etatSelect && !isViewMode) {
                function toggleMarchandisesSection() {
                    const marchandisesSection = document.getElementById('marchandisesSection');
                    if (etatSelect.value === 'chargé') {
                        marchandisesSection.style.display = 'block';
                    } else {
                        marchandisesSection.style.display = 'none';
                    }
                }
                
                // Initialiser et écouter les changements
                toggleMarchandisesSection();
                etatSelect.addEventListener('change', toggleMarchandisesSection);
            }
            
            // Navigation entre les étapes
            const nextStepBtn = document.getElementById('nextStep');
            if (nextStepBtn) {
                nextStepBtn.addEventListener('click', function() {
                    if (validateStep1()) {
                        goToStep(2);
                    }
                });
            }
            
            const prevStepBtn = document.getElementById('prevStep');
            if (prevStepBtn) {
                prevStepBtn.addEventListener('click', function() {
                    goToStep(1);
                });
            }
            
            // Valider le formulaire avant soumission
            const form = document.getElementById('bateauForm');
            if (form && !isViewMode) {
                form.addEventListener('submit', function(e) {
                    const etat = document.getElementById('etat').value;
                    if (!etat) {
                        alert('Veuillez sélectionner un état pour le bateau');
                        e.preventDefault();
                        return false;
                    }
                    
                    if (etat === 'chargé') {
                        const marchandiseSelects = document.querySelectorAll('[name^="marchandises["][name$="][type]"]');
                        let hasMarchandise = false;
                        marchandiseSelects.forEach(select => {
                            if (select.value && select.value > 0) {
                                hasMarchandise = true;
                            }
                        });
                        
                        if (!hasMarchandise) {
                            if (!confirm('Le bateau est marqué comme "chargé" mais aucune marchandise n\'a été spécifiée. Voulez-vous continuer ?')) {
                                e.preventDefault();
                                return false;
                            }
                        }
                        
                        const poidsInputs = document.querySelectorAll('[name^="marchandises["][name$="][poids]"]');
                        for (let i = 0; i < poidsInputs.length; i++) {
                            const poids = parseFloat(poidsInputs[i].value);
                            if (isNaN(poids) || poids <= 0) {
                                alert(`Veuillez entrer un poids valide (supérieur à 0) pour la marchandise #${i + 1}`);
                                poidsInputs[i].focus();
                                e.preventDefault();
                                return false;
                            }
                        }
                    }
                    
                    return true;
                });
            }
            
            // Mettre en surbrillance la ligne sélectionnée dans le tableau
            const selectedRow = document.querySelector('.recent-bateau-row.bg-blue-50');
            if (selectedRow) {
                selectedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // Si en mode édition et qu'il y a des marchandises, aller directement à l'étape 2
            if (!isViewMode && marchandiseIndex > 0) {
                goToStep(2);
            }
            
            // Si le bateau est chargé en mode édition, aller directement à l'étape 2
            if (!isViewMode && document.getElementById('etat').value === 'chargé') {
                goToStep(2);
            }
        });
    </script>
</body>
</html>