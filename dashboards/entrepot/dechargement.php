<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'agentEntrepot') {
    header("Location: ../../login.php");
    exit();
}

// Fonction utilitaire pour éviter les erreurs de dépréciation
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Variables pour les messages
$message = '';
$message_type = '';

// Traitement du formulaire de déchargement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decharger_camion'])) {
    try {
        error_log("=== DÉBUT TRAITEMENT DÉCHARGEMENT ===");
        error_log("Camion ID: " . ($_POST['camion_id'] ?? 'N/A'));
        
        $conn->begin_transaction();
        
        $camion_id = $_POST['camion_id'];
        $chargement_id = $_POST['chargement_id'] ?? 0;
        $note_dechargement = $_POST['note_dechargement'] ?? '';
        
        // Vérifier que le camion ID n'est pas vide
        if (empty($camion_id)) {
            throw new Exception("Aucun camion sélectionné");
        }
        
        // Récupérer les marchandises à décharger depuis le formulaire
        $marchandises_a_decharger = [];
        
        // Vérifier si des marchandises ont été soumises
        if (isset($_POST['marchandises']) && is_array($_POST['marchandises'])) {
            error_log("Nombre de marchandises dans POST: " . count($_POST['marchandises']));
            
            foreach ($_POST['marchandises'] as $index => $march) {
                if (!empty($march['type']) && isset($march['a_decharger']) && $march['a_decharger'] == '1') {
                    $marchandises_a_decharger[] = [
                        'idTypeMarchandise' => $march['type'],
                        'note' => $march['note'] ?? ''
                    ];
                    error_log("Marchandise $index à décharger: type=" . $march['type']);
                }
            }
        }
        
        if (empty($marchandises_a_decharger)) {
            throw new Exception("Veuillez sélectionner au moins une marchandise à décharger");
        }
        
        // Vérifier que le camion existe, a été chargé, et n'a pas encore été déchargé
        $stmt = $conn->prepare("
            SELECT ce.*
            FROM camions_entrants ce
            WHERE ce.idEntree = ?
            AND ce.etat = 'Chargé'
            AND ce.idEntree NOT IN (
                SELECT idEntree FROM dechargements_camions
            )
        ");
        $stmt->bind_param("i", $camion_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Camion non trouvé, non chargé, déjà déchargé ou état incorrect");
        }
        
        $camion = $result->fetch_assoc();
        
        // Récupérer les marchandises du pesage pour ce camion
        $marchandises_pesage = [];
        $stmt_check = $conn->prepare("
            SELECT mp.idTypeMarchandise, tm.nom as nom_marchandise, mp.poids AS poids
            FROM marchandises_pesage mp
            INNER JOIN type_marchandise tm ON mp.idTypeMarchandise = tm.id
            INNER JOIN pesages p ON mp.idPesage = p.idPesage
            WHERE p.idEntree = ?
        ");
        $stmt_check->bind_param("i", $camion_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        while ($row = $result_check->fetch_assoc()) {
            $marchandises_pesage[$row['idTypeMarchandise']] = $row['nom_marchandise'];
        }
        
        if (empty($marchandises_pesage)) {
            throw new Exception("Aucune marchandise enregistrée lors du pesage pour ce camion");
        }
        
        // Vérifier que chaque marchandise à décharger a été enregistrée au pesage
        foreach ($marchandises_a_decharger as $march) {
            if (!isset($marchandises_pesage[$march['idTypeMarchandise']])) {
                throw new Exception("La marchandise sélectionnée n'a pas été enregistrée lors du pesage");
            }
        }
        
        // Enregistrer le déchargement dans la table dechargements_camions
        $stmt = $conn->prepare("
            INSERT INTO dechargements_camions
            (idEntree, idChargement, note_dechargement, date_dechargement)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iis", 
            $camion_id,
            $chargement_id,
            $note_dechargement
        );
        $stmt->execute();
        $dechargement_id = $conn->insert_id;
        
        error_log("Déchargement créé avec ID: $dechargement_id");
        
        // Ajouter les marchandises déchargées
        foreach ($marchandises_a_decharger as $march) {
            $stmt = $conn->prepare("
                INSERT INTO marchandise_dechargement_camion 
                (idDechargement, idTypeMarchandise, note, date_ajout)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->bind_param("iis", 
                $dechargement_id,
                $march['idTypeMarchandise'],
                $march['note']
            );
            $stmt->execute();
        }
        
        // Mettre à jour l'état du camion à "Déchargé" dans camions_entrants
        $stmt = $conn->prepare("
            UPDATE camions_entrants 
            SET etat = 'Déchargé'
            WHERE idEntree = ?
        ");
        $stmt->bind_param("i", $camion_id);
        $stmt->execute();
        
        $conn->commit();
        
        $message = "Camion déchargé avec succès !";
        $message_type = "success";
        
        // Rediriger pour éviter la resoumission du formulaire
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&camion=" . $camion_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Erreur lors du déchargement: " . $e->getMessage();
        $message_type = "error";
        error_log("Erreur déchargement: " . $e->getMessage());
    }
}

// Vérifier si succès après redirection
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "Camion déchargé avec succès !";
    $message_type = "success";
}

// Récupérer la liste des camions chargés non déchargés (état = 'Chargé')
$camions_charges_a_decharger = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            ce.idEntree,
            ce.immatriculation,
            ce.etat,
            ce.date_entree,
            tc.nom as type_camion,
            p.nom as port,
            cc.idChargement,
            cc.date_chargement
        FROM camions_entrants ce
        LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
        LEFT JOIN port p ON ce.idPort = p.id
        LEFT JOIN chargement_camions cc ON ce.idEntree = cc.idEntree
        WHERE ce.etat = 'Chargé'
        AND ce.idEntree NOT IN (
            SELECT idEntree FROM dechargements_camions
        )
        ORDER BY ce.idEntree DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $camions_charges_a_decharger = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $message = "Erreur lors du chargement des camions à décharger: " . $e->getMessage();
    $message_type = "error";
}

// Récupérer les types de marchandises
$types_marchandises = [];
try {
    $stmt = $conn->prepare("SELECT id, nom FROM type_marchandise ORDER BY nom");
    $stmt->execute();
    $result = $stmt->get_result();
    $types_marchandises = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $message = "Erreur lors du chargement des types de marchandises: " . $e->getMessage();
    $message_type = "error";
}

// Fonction pour récupérer les marchandises du pesage d'un camion
function getMarchandisesPesage($conn, $camion_id) {
    $marchandises = [];

    $stmt = $conn->prepare("
        SELECT 
            mp.idTypeMarchandise,
            tm.nom AS nom_marchandise,
            mp.poids AS poids,
            mp.note
        FROM pesages p
        INNER JOIN marchandises_pesage mp ON p.idPesage = mp.idPesage
        INNER JOIN type_marchandise tm ON mp.idTypeMarchandise = tm.id
        WHERE p.idEntree = ?
        ORDER BY p.date_pesage DESC
        LIMIT 100
    ");

    $stmt->bind_param("i", $camion_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $marchandises[] = [
            'idTypeMarchandise' => $row['idTypeMarchandise'],
            'nom_marchandise'   => $row['nom_marchandise'],
            'note_pesage'       => $row['note'] ?? '',
            'poids'            => $row['poids']
        ];
    }

    return $marchandises;
}


// Vérifier si on demande le déchargement d'un camion spécifique
$chargement_a_decharger = null;
$marchandises_chargement = [];
if (isset($_GET['decharger']) && is_numeric($_GET['decharger'])) {
    try {
        $chargement_id = $_GET['decharger'];
        
        // Récupérer les informations du camion
        if ($chargement_id > 0) {
            // C'est un ID de chargement
            $stmt = $conn->prepare("
                SELECT 
                    cc.*,
                    ce.*,
                    tc.nom as type_camion,
                    p.nom as port,
                    ps.ptav,
                    ps.ptac,
                    ps.ptra,
                    ps.charge_essieu
                FROM chargement_camions cc
                INNER JOIN camions_entrants ce ON cc.idEntree = ce.idEntree
                LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
                LEFT JOIN port p ON ce.idPort = p.id
                LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
                WHERE cc.idChargement = ?
                AND ce.etat = 'Chargé'
                AND ce.idEntree NOT IN (
                    SELECT idEntree FROM dechargements_camions
                )
            ");
            $stmt->bind_param("i", $chargement_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $chargement_a_decharger = $result->fetch_assoc();
                // Récupérer les marchandises depuis le pesage
                $marchandises_chargement = getMarchandisesPesage($conn, $chargement_a_decharger['idEntree']);
            }
        } else {
            // C'est un ID de camion
            $camion_id = abs($chargement_id);
            
            $stmt = $conn->prepare("
                SELECT 
                    ce.*,
                    tc.nom as type_camion,
                    p.nom as port,
                    ps.ptav,
                    ps.ptac,
                    ps.ptra,
                    ps.charge_essieu
                FROM camions_entrants ce
                LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
                LEFT JOIN port p ON ce.idPort = p.id
                LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
                WHERE ce.idEntree = ?
                AND ce.etat = 'Chargé'
                AND ce.idEntree NOT IN (
                    SELECT idEntree FROM dechargements_camions
                )
            ");
            $stmt->bind_param("i", $camion_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $chargement_a_decharger = $result->fetch_assoc();
                $chargement_a_decharger['idChargement'] = 0;
                // Récupérer les marchandises depuis le pesage
                $marchandises_chargement = getMarchandisesPesage($conn, $camion_id);
            }
        }
        
        if (empty($chargement_a_decharger)) {
            throw new Exception("Camion non trouvé ou déjà déchargé");
        }
        
        // Vérifier qu'il y a des marchandises enregistrées au pesage
        if (empty($marchandises_chargement)) {
            throw new Exception("Aucune marchandise enregistrée lors du pesage pour ce camion");
        }
        
    } catch (Exception $e) {
        $message = "Erreur lors du chargement des données de déchargement: " . $e->getMessage();
        $message_type = "error";
        error_log("Erreur chargement données déchargement: " . $e->getMessage());
    }
}

// Récupérer la liste des camions récemment déchargés
$camions_decharges = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            d.idDechargement,
            d.date_dechargement,
            d.note_dechargement,
            ce.idEntree,
            ce.immatriculation,
            ce.etat,
            tc.nom AS type_camion,
            ps.ptav,
            ps.ptac,
            ps.ptra,
            GROUP_CONCAT(DISTINCT tm.nom SEPARATOR ', ') AS marchandises_dechargees
        FROM dechargements_camions d
        INNER JOIN camions_entrants ce ON d.idEntree = ce.idEntree
        LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
        LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
        LEFT JOIN marchandise_dechargement_camion mdc ON d.idDechargement = mdc.idDechargement
        LEFT JOIN type_marchandise tm ON mdc.idTypeMarchandise = tm.id
        GROUP BY d.idDechargement
        ORDER BY d.date_dechargement DESC
        LIMIT 20
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $camions_decharges = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $message = "Erreur lors du chargement des camions déchargés: " . $e->getMessage();
    $message_type = "error";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Entrepôt - Déchargement des Camions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .scrollable-section {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        
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
        
        .camion-row {
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .camion-row:hover {
            background-color: #f3f4f6;
        }
        
        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .hidden-form {
            display: none;
        }
        
        .visible-form {
            display: block;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .dechargement-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #f59e0b;
            color: #92400e;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .checkbox-container input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            cursor: pointer;
        }
        
        .checkbox-container label {
            cursor: pointer;
            font-weight: 500;
            color: #374151;
        }
        
        .marchandise-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            background-color: white;
            transition: all 0.3s;
        }
        
        .marchandise-card.selected {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        
        .marchandise-card.disabled {
            opacity: 0.6;
            background-color: #f3f4f6;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-charge {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-decharge {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .warning-badge {
            background-color: #fef3c7;
            color: #92400e;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .pesage-info {
            background-color: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 12px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .pesage-label {
            font-weight: bold;
            color: #1e40af;
            font-size: 0.9em;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-gray-100 min-h-screen">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        
        <?php if ($message): ?>
            <div class="mb-6 animate-fade-in">
                <div class="<?php echo $message_type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?> 
                            border px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-3"></i>
                        <span><?php echo safe_html($message); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?> 
        
        <!-- Interface principale avec deux sections côte à côte -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Section 1: Liste des camions chargés à décharger et formulaire -->
            <div>
                <!-- Liste des camions chargés à décharger -->
                <div class="glass-card p-6 mb-6 <?php echo $chargement_a_decharger ? 'hidden' : ''; ?>">
                    <h2 class="text-xl font-bold text-gray-800 mb-6 pb-4 border-b">
                        <i class="fas fa-truck-loading mr-2"></i>Camions Chargés à Décharger
                    </h2>
                    
                    <?php if (!empty($camions_charges_a_decharger)): ?>
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Immatriculation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Date Entrée</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($camions_charges_a_decharger as $camion): ?>
                                <?php 
                                // Vérifier s'il y a des marchandises au pesage pour ce camion
                                $hasMarchandisesPesage = false;
                                $temp_marchandises = getMarchandisesPesage($conn, $camion['idEntree']);
                                $hasMarchandisesPesage = !empty($temp_marchandises);
                                ?>
                                <tr class="camion-row <?php echo !$hasMarchandisesPesage ? 'opacity-60' : ''; ?>">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 bg-orange-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-truck text-orange-600 text-sm"></i>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo safe_html($camion['immatriculation']); ?>
                                                    <?php if (!$hasMarchandisesPesage): ?>
                                                    <span class="warning-badge ml-2">Pas de pesage</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo safe_html($camion['type_camion'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo !empty($camion['date_entree']) ? date('d/m/Y H:i', strtotime($camion['date_entree'])) : 'N/A'; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <?php if ($hasMarchandisesPesage): ?>
                                            <?php if (!empty($camion['idChargement'])): ?>
                                                <a href="?decharger=<?php echo $camion['idChargement']; ?>" 
                                                   class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg text-xs">
                                                    <i class="fas fa-download mr-1"></i>Décharger
                                                </a>
                                            <?php else: ?>
                                                <a href="?decharger=-<?php echo $camion['idEntree']; ?>" 
                                                   class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg text-xs">
                                                    <i class="fas fa-download mr-1"></i>Décharger
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">Non disponible</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-inbox text-3xl text-gray-300 mb-2 block"></i>
                        <p>Aucun camion chargé disponible pour le déchargement</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Formulaire de déchargement -->
                <div id="form-dechargement-container" class="glass-card p-6 <?php echo $chargement_a_decharger ? 'visible-form' : 'hidden-form'; ?>">
                    <?php if ($chargement_a_decharger): ?>
                        <div class="dechargement-badge">
                            <i class="fas fa-download mr-1"></i> DÉCHARGEMENT
                        </div>
                    <?php endif; ?>
                    
                    <h2 class="text-xl font-bold text-gray-800 mb-6 pb-4 border-b">
                        <i class="fas fa-download mr-2"></i>
                        Formulaire de Déchargement
                        <span id="selected-camion-title" class="text-blue-600 ml-2">
                            <?php echo $chargement_a_decharger ? safe_html($chargement_a_decharger['immatriculation']) : ''; ?>
                        </span>
                    </h2>
                    
                    <?php if ($chargement_a_decharger): ?>
                    <form id="formDechargement" method="POST" class="space-y-6" novalidate>
                        <!-- Champs cachés -->
                        <input type="hidden" id="selected_camion_id" name="camion_id" 
                               value="<?php echo $chargement_a_decharger['idEntree']; ?>">
                        <input type="hidden" id="selected_chargement_id" name="chargement_id" 
                               value="<?php echo $chargement_a_decharger['idChargement'] ?? 0; ?>">
                        <input type="hidden" name="decharger_camion" value="1">
                        
                        <!-- Informations du camion sélectionné -->
                        <div id="camion-selected-info" class="mb-6">
                            
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <p class="text-xs text-gray-600 mb-1">Immatriculation</p>
                                    <p id="info-immatriculation" class="text-lg font-bold text-blue-800">
                                        <?php echo safe_html($chargement_a_decharger['immatriculation']); ?>
                                    </p>
                                </div>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <p class="text-xs text-gray-600 mb-1">Type / Port</p>
                                    <p id="info-type-port" class="text-lg font-bold text-blue-800">
                                        <?php echo safe_html($chargement_a_decharger['type_camion'] ?? 'N/A') . ' / ' . safe_html($chargement_a_decharger['port'] ?? 'N/A'); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <?php if (!empty($chargement_a_decharger['ptav'])): ?>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                    <p class="text-xs text-gray-600 mb-1">PTAV</p>
                                    <p id="info-ptav" class="text-lg font-bold text-blue-800">
                                        <?php echo number_format($chargement_a_decharger['ptav'], 2) . ' kg'; ?>
                                    </p>
                                </div>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                    <p class="text-xs text-gray-600 mb-1">PTAC</p>
                                    <p id="info-ptac" class="text-lg font-bold text-blue-800">
                                        <?php echo number_format($chargement_a_decharger['ptac'], 2) . ' kg'; ?>
                                    </p>
                                </div>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                    <p class="text-xs text-gray-600 mb-1">PTRA</p>
                                    <p id="info-ptra" class="text-lg font-bold text-blue-800">
                                        <?php echo number_format($chargement_a_decharger['ptra'], 2) . ' kg'; ?>
                                    </p>
                                </div>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                    <p class="text-xs text-gray-600 mb-1">Charge Essieu Max</p>
                                    <p id="info-charge-essieu" class="text-lg font-bold text-blue-800">
                                        <?php echo number_format($chargement_a_decharger['charge_essieu'], 2) . ' kg'; ?>
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($chargement_a_decharger['date_chargement'])): ?>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-calendar-alt text-yellow-600 mr-3"></i>
                                    <div>
                                        <p class="text-xs text-gray-600 mb-1">Date et heure de chargement</p>
                                        <p class="text-sm font-bold text-gray-800">
                                            <?php echo date('d/m/Y', strtotime($chargement_a_decharger['date_chargement'])); ?> à <?php echo date('H:i', strtotime($chargement_a_decharger['date_chargement'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                        </div>
                        
                        <!-- Section des marchandises à décharger -->
                        <div class="border-t pt-6">
                            <div class="flex justify-between items-center mb-4">
                                <h5 class="text-lg font-bold text-gray-800">
                                    <i class="fas fa-weight-scale mr-2"></i>Marchandises
                                </h5>
                                <div class="flex items-center space-x-4">
                                    <button type="button" id="select-all" 
                                            class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-bold py-2 px-4 rounded-lg">
                                        <i class="fas fa-check-square mr-1"></i>Tout sélectionner
                                    </button>
                                    <button type="button" id="deselect-all" 
                                            class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-bold py-2 px-4 rounded-lg">
                                        <i class="fas fa-times-circle mr-1"></i>Tout désélectionner
                                    </button>
                                </div>
                            </div>
                            
                            <div id="marchandises-container" class="space-y-4">
                                <?php if (!empty($marchandises_chargement)): ?>
                                    <?php foreach ($marchandises_chargement as $index => $march): ?>
                                    <div class="marchandise-card selected" data-index="<?php echo $index; ?>">
                                        <div class="flex justify-between items-start mb-3">
                                            <div class="checkbox-container">
                                                <input type="checkbox" 
                                                       id="march_<?php echo $index; ?>" 
                                                       name="marchandises[<?php echo $index; ?>][a_decharger]" 
                                                       value="1" 
                                                       checked
                                                       class="marchandise-checkbox">
                                                <label for="march_<?php echo $index; ?>" class="text-lg font-medium text-gray-800">
                                                    <?php echo safe_html($march['nom_marchandise']); ?>
                                                    <span class="pesage-label ml-2">
                                                        <i class="fas fa-weight-scale mr-1"></i>PESAGE
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <input type="hidden" 
                                               name="marchandises[<?php echo $index; ?>][type]" 
                                               value="<?php echo $march['idTypeMarchandise']; ?>">
                                       
                                        <div class="grid grid-cols-2 gap-4 mt-3">
                                            <div>
                                                <label class="block text-gray-700 text-xs font-bold mb-1">Poids (pesage)</label>
                                                <p class="text-sm text-gray-800 bg-gray-50 p-2 rounded">
                                                    <?php echo isset($march['poids']) ? number_format($march['poids'], 2) . ' kg' : 'N/A'; ?>
                                                </p>
                                            </div>
                                            <?php if (!empty($march['note_pesage'])): ?>
                                            <div>
                                                <label class="block text-gray-700 text-xs font-bold mb-1">Note du pesage</label>
                                                <p class="text-sm text-gray-800 bg-gray-50 p-2 rounded">
                                                    <?php echo safe_html($march['note_pesage']); ?>
                                                </p>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="w-full mt-3">
                                            <label class="block text-gray-700 text-xs font-bold mb-1">Note sur le déchargement (optionnelle)</label>
                                            <textarea name="marchandises[<?php echo $index; ?>][note]" rows="2"
                                                      placeholder="État de la marchandise, observations..."
                                                      class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <i class="fas fa-exclamation-triangle text-3xl mb-3 text-yellow-500"></i>
                                        <p class="font-bold text-yellow-600">Aucune marchandise enregistrée lors du pesage</p>
                                        <p class="text-sm mt-2">Ce camion ne peut pas être déchargé car aucune marchandise n'a été enregistrée lors du pesage.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Notes -->
                        <div class="form-group">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="note_dechargement">
                                <i class="fas fa-sticky-note mr-2"></i>Notes sur le déchargement
                            </label>
                            <textarea id="note_dechargement" name="note_dechargement" rows="3"
                                      placeholder="Observations sur le déchargement, problèmes rencontrés, etc."
                                      class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        
                        <!-- Bouton de soumission -->
                        <div class="flex justify-end pt-6 border-t">
                            <?php if (!empty($marchandises_chargement)): ?>
                            <button type="submit" name="decharger_camion" id="submit-button"
                                    class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-8 rounded-lg text-lg transition duration-300">
                                <i class="fas fa-check-circle mr-2"></i>Valider le déchargement
                            </button>
                            <?php endif; ?>
                            <button type="button" id="cancel-dechargement"
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-6 rounded-lg text-lg transition duration-300 ml-3">
                                Annuler
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>Aucun camion sélectionné pour le déchargement.</p>
                        <a href="?" class="text-blue-500 hover:text-blue-700 mt-2 inline-block">
                            <i class="fas fa-arrow-left mr-1"></i> Retour à la liste
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Section 2: Liste des camions récemment déchargés -->
            <div class="glass-card p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6 pb-4 border-b">
                    <i class="fas fa-list mr-2"></i>Camions Récemment Déchargés
                </h2>
                
                <div class="scrollable-section">
                    <div class="space-y-4">
                        <?php foreach ($camions_decharges as $camion): ?>
                        <div class="border rounded-lg p-4 hover:bg-gray-50 transition duration-200 relative">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <div class="flex items-center mb-2">
                                        <div class="flex-shrink-0 h-8 w-8 bg-green-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-truck text-green-600 text-sm"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="font-bold text-gray-800">
                                                <?php echo safe_html($camion['immatriculation']); ?>
                                            </h3>
                                            <p class="text-sm text-gray-600">
                                                <?php echo safe_html($camion['type_camion'] ?? 'N/A'); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                    DÉCHARGÉ
                                </span>
                            </div>
                            
                            <!-- Informations techniques -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">PTAV</p>
                                    <p class="text-sm font-bold"><?php echo !empty($camion['ptav']) ? number_format($camion['ptav'], 2) . ' kg' : 'N/A'; ?></p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">PTAC</p>
                                    <p class="text-sm font-bold"><?php echo !empty($camion['ptac']) ? number_format($camion['ptac'], 2) . ' kg' : 'N/A'; ?></p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">PTRA</p>
                                    <p class="text-sm font-bold"><?php echo !empty($camion['ptra']) ? number_format($camion['ptra'], 2) . ' kg' : 'N/A'; ?></p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500"><i class="fas fa-download mr-1"></i>Déchargement</p>
                                    <p class="text-sm font-bold"><?php echo date('d/m/Y', strtotime($camion['date_dechargement'])); ?></p>
                                    <p class="text-xs text-gray-600"><?php echo date('H:i', strtotime($camion['date_dechargement'])); ?></p>
                                </div>
                            </div>
                            
                            <!-- Marchandises déchargées -->
                            <?php if (!empty($camion['marchandises_dechargees'])): ?>
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <p class="text-xs text-gray-600 mb-1">
                                    <i class="fas fa-box mr-1"></i>Marchandises déchargées:
                                </p>
                                <p class="text-sm text-gray-800 font-medium">
                                    <?php echo safe_html($camion['marchandises_dechargees']); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($camion['note_dechargement'])): ?>
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <p class="text-xs text-gray-600">
                                    <i class="fas fa-sticky-note mr-1"></i>
                                    <?php echo substr(safe_html($camion['note_dechargement']), 0, 100); ?>
                                    <?php echo strlen($camion['note_dechargement']) > 100 ? '...' : ''; ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($camions_decharges)): ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-4"></i>
                            <p class="text-lg">Aucun camion déchargé aujourd'hui</p>
                            <p class="text-sm mt-2">Commencez par décharger un camion dans le formulaire</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion de l'annulation
            const cancelBtn = document.getElementById('cancel-dechargement');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    hideForm();
                });
            }
            
            // Gestion de la sélection/désélection de toutes les marchandises
            const selectAllBtn = document.getElementById('select-all');
            const deselectAllBtn = document.getElementById('deselect-all');
            
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', selectAllMarchandises);
            }
            if (deselectAllBtn) {
                deselectAllBtn.addEventListener('click', deselectAllMarchandises);
            }
            
            // Gestion de la soumission du formulaire
            const form = document.getElementById('formDechargement');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    validateAndSubmit();
                });
            }
            
            // Ajouter des événements aux cases à cocher
            document.querySelectorAll('.marchandise-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    toggleMarchandiseCard(this);
                });
            });
        });
        
        // Fonction pour cacher le formulaire
        function hideForm() {
            // Si on est en mode déchargement spécifique, rediriger vers la page normale
            if (window.location.search.includes('decharger=')) {
                window.location.href = window.location.pathname;
            } else {
                const formContainer = document.getElementById('form-dechargement-container');
                if (formContainer) {
                    formContainer.classList.remove('visible-form');
                    formContainer.classList.add('hidden-form');
                }
                // Réafficher la liste des camions
                const camionList = document.querySelector('.glass-card:first-child');
                if (camionList) {
                    camionList.classList.remove('hidden');
                }
            }
        }
        
        // Fonction pour sélectionner toutes les marchandises
        function selectAllMarchandises() {
            document.querySelectorAll('.marchandise-checkbox').forEach(checkbox => {
                checkbox.checked = true;
                toggleMarchandiseCard(checkbox, true);
            });
        }
        
        // Fonction pour désélectionner toutes les marchandises
        function deselectAllMarchandises() {
            document.querySelectorAll('.marchandise-checkbox').forEach(checkbox => {
                checkbox.checked = false;
                toggleMarchandiseCard(checkbox, false);
            });
        }
        
        // Fonction pour activer/désactiver la carte de marchandise
        function toggleMarchandiseCard(checkbox, forceState = null) {
            const isChecked = forceState !== null ? forceState : checkbox.checked;
            const card = checkbox.closest('.marchandise-card');
            
            if (!card) return;
            
            if (isChecked) {
                card.classList.add('selected');
                card.classList.remove('disabled');
            } else {
                card.classList.remove('selected');
                card.classList.add('disabled');
            }
        }
        
        // Fonction pour valider et soumettre le formulaire
        function validateAndSubmit() {
            // Vérifier qu'au moins une marchandise est sélectionnée
            const selectedMarchandises = document.querySelectorAll('.marchandise-checkbox:checked');
            
            if (selectedMarchandises.length === 0) {
                alert('Veuillez sélectionner au moins une marchandise à décharger.');
                return false;
            }
            
            // Désactiver le bouton pour éviter les soumissions multiples
            const submitBtn = document.getElementById('submit-button');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Déchargement en cours...';
            }
            
            // Soumettre le formulaire
            const form = document.getElementById('formDechargement');
            if (form) {
                form.submit();
            }
            
            return true;
        }
    </script>
</body>
</html>