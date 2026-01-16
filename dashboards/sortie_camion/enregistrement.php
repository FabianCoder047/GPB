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

// Fonction pour vérifier si toutes les marchandises d'un chargement ont été pesées
function checkPesageComplet($conn, $chargement_id) {
    $result = [
        'complet' => false,
        'total_marchandises' => 0,
        'marchandises_pesees' => 0,
        'details' => []
    ];
    
    try {
        // Récupérer toutes les marchandises du chargement
        $stmt = $conn->prepare("
            SELECT 
                mcc.idTypeMarchandise,
                tm.nom as nom_marchandise
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
        
        // Récupérer les pesages pour ce chargement
        $stmt = $conn->prepare("
            SELECT 
                pcc.idPesageChargement,
                pcc.date_pesage,
                mpc.idTypeMarchandise,
                mpc.poids
            FROM pesage_chargement_camion pcc
            LEFT JOIN marchandises_pesage_camion mpc ON pcc.idPesageChargement = mpc.idPesageChargement
            WHERE pcc.idChargement = ?
            ORDER BY pcc.date_pesage DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $chargement_id);
        $stmt->execute();
        $pesage_result = $stmt->get_result();
        
        // Si aucun pesage n'existe
        if ($pesage_result->num_rows === 0) {
            $result['complet'] = false;
            // Préparer les détails
            foreach ($marchandises as $march) {
                $result['details'][] = [
                    'idTypeMarchandise' => $march['idTypeMarchandise'],
                    'nom_marchandise' => $march['nom_marchandise'],
                    'pese' => false,
                    'poids' => null
                ];
            }
            return $result;
        }
        
        // Récupérer le dernier pesage
        $pesage_data = [];
        while ($row = $pesage_result->fetch_assoc()) {
            if ($row['idTypeMarchandise']) {
                $pesage_data[$row['idTypeMarchandise']] = [
                    'poids' => $row['poids'],
                    'date_pesage' => $row['date_pesage']
                ];
            }
        }
        
        // Vérifier chaque marchandise
        $marchandises_pesees = 0;
        foreach ($marchandises as $march) {
            $pese = isset($pesage_data[$march['idTypeMarchandise']]);
            $result['details'][] = [
                'idTypeMarchandise' => $march['idTypeMarchandise'],
                'nom_marchandise' => $march['nom_marchandise'],
                'pese' => $pese,
                'poids' => $pese ? $pesage_data[$march['idTypeMarchandise']]['poids'] : null
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

// Variables pour les messages
$message = '';
$message_type = '';

// Traitement de la validation de sortie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_sortie'])) {
    try {
        error_log("=== DÉBUT TRAITEMENT SORTIE ===");
        
        $conn->begin_transaction();
        
        $camion_id = $_POST['camion_id'];
        $type_sortie = $_POST['type_sortie']; // 'charge' ou 'decharge'
        $chargement_id = $_POST['chargement_id'] ?? 0;
        $dechargement_id = $_POST['dechargement_id'] ?? 0;
        
        // Vérifier que le camion ID n'est pas vide
        if (empty($camion_id)) {
            throw new Exception("Aucun camion sélectionné");
        }
        
        // Vérifier que le camion n'est pas déjà sorti
        $stmt_check = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM camions_sortants 
            WHERE idEntree = ?
        ");
        $stmt_check->bind_param("i", $camion_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $already_exists = $result_check->fetch_assoc()['count'];
        
        if ($already_exists > 0) {
            throw new Exception("Ce camion est déjà sorti");
        }
        
        // Vérifier que le camion existe
        $stmt = $conn->prepare("
            SELECT ce.* 
            FROM camions_entrants ce
            WHERE ce.idEntree = ?
        ");
        $stmt->bind_param("i", $camion_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Camion non trouvé");
        }
        
        $camion = $result->fetch_assoc();
        
        // Vérifications spécifiques selon le type de sortie
        if ($type_sortie === 'charge') {
            // Vérifier l'existence du chargement
            $stmt_check_op = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM chargement_camions 
                WHERE idChargement = ? AND idEntree = ?
            ");
            $stmt_check_op->bind_param("ii", $chargement_id, $camion_id);
            $stmt_check_op->execute();
            $result_check_op = $stmt_check_op->get_result();
            $operation_exists = $result_check_op->fetch_assoc()['count'];
            
            if ($operation_exists === 0) {
                throw new Exception("Le chargement n'existe pas pour ce camion");
            }
            
            // Vérifier que toutes les marchandises ont été pesées
            $pesage_status = checkPesageComplet($conn, $chargement_id);
            if (!$pesage_status['complet']) {
                throw new Exception("Le pesage n'est pas complet pour ce chargement. " . 
                                   $pesage_status['marchandises_pesees'] . " sur " . 
                                   $pesage_status['total_marchandises'] . " marchandises pesées.");
            }
            
        } else {
            // Vérifier l'existence du déchargement
            $stmt_check_op = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM dechargements_camions
                WHERE idDechargement = ? AND idEntree = ?
            ");
            $stmt_check_op->bind_param("ii", $dechargement_id, $camion_id);
            $stmt_check_op->execute();
            $result_check_op = $stmt_check_op->get_result();
            $operation_exists = $result_check_op->fetch_assoc()['count'];
            
            if ($operation_exists === 0) {
                throw new Exception("Le déchargement n'existe pas pour ce camion");
            }
        }
        
        // Enregistrer la sortie dans la table camions_sortants seulement
        $stmt = $conn->prepare("
            INSERT INTO camions_sortants 
            (idEntree, idChargement, idDechargement, date_sortie, type_sortie)
            VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmt->bind_param("iiis", 
            $camion_id,
            $chargement_id,
            $dechargement_id,
            $type_sortie
        );
        $stmt->execute();
        $sortie_id = $conn->insert_id;
        
        error_log("Sortie créée avec ID: $sortie_id, type: $type_sortie");
        
        $conn->commit();
        
        $message = "Sortie du camion validée avec succès !";
        $message_type = "success";
        
        // Rediriger pour éviter la resoumission du formulaire
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&camion=" . $camion_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Erreur lors de la validation de sortie: " . $e->getMessage();
        $message_type = "error";
        error_log("Erreur sortie: " . $e->getMessage());
    }
}

// Vérifier si succès après redirection
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "Sortie du camion validée avec succès !";
    $message_type = "success";
}

// Récupérer les camions chargés non sortis avec vérification du pesage
$camions_charges_non_sortis = [];
$marchandises_par_chargement = [];

try {
    // Récupérer les camions avec chargement non sortis
    $stmt = $conn->prepare("
        SELECT 
            ce.idEntree,
            ce.immatriculation,
            ce.etat,
            ce.date_entree,
            tc.nom as type_camion,
            p.nom as port,
            cc.idChargement,
            cc.date_chargement,
            cc.note_chargement,
            ps.ptav,
            ps.ptac,
            ps.ptra,
            ps.charge_essieu,
            (SELECT COUNT(*) FROM marchandise_chargement_camion WHERE idChargement = cc.idChargement) as nb_marchandises
        FROM camions_entrants ce
        INNER JOIN chargement_camions cc ON ce.idEntree = cc.idEntree
        LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
        LEFT JOIN port p ON ce.idPort = p.id
        LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
        WHERE ce.idEntree NOT IN (
            SELECT idEntree FROM camions_sortants
        )
        ORDER BY cc.date_chargement ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $camions_charges_non_sortis = $result->fetch_all(MYSQLI_ASSOC);
    
    // Vérifier le pesage pour chaque chargement et ajouter l'information
    foreach ($camions_charges_non_sortis as &$camion) {
        $pesage_status = checkPesageComplet($conn, $camion['idChargement']);
        $camion['pesage_complet'] = $pesage_status['complet'];
        $camion['nb_marchandises_pesees'] = $pesage_status['marchandises_pesees'];
        $camion['details_pesage'] = $pesage_status['details'];
    }
    unset($camion); // Déréférencer pour éviter les effets de bord
    
    // Récupérer les marchandises pour chaque chargement (pour l'affichage détaillé)
    if (!empty($camions_charges_non_sortis)) {
        $chargement_ids = array_column($camions_charges_non_sortis, 'idChargement');
        if (!empty($chargement_ids)) {
            $placeholders = str_repeat('?,', count($chargement_ids) - 1) . '?';
            
            $stmt_march = $conn->prepare("
                SELECT 
                    mcc.idChargement,
                    mcc.idTypeMarchandise,
                    tm.nom as nom_marchandise,
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
    }
    
} catch (Exception $e) {
    $message = "Erreur lors du chargement des camions chargés: " . $e->getMessage();
    $message_type = "error";
    error_log("Erreur chargement camions chargés: " . $e->getMessage());
}

// Récupérer les camions déchargés non sortis
$camions_decharges_non_sortis = [];
$marchandises_par_dechargement = [];

try {
    // Récupérer les camions avec déchargement non sortis
    $stmt = $conn->prepare("
        SELECT 
            ce.idEntree,
            ce.immatriculation,
            ce.etat,
            ce.date_entree,
            tc.nom as type_camion,
            p.nom as port,
            d.idDechargement,
            d.date_dechargement,
            d.note_dechargement,
            ps.ptav,
            ps.ptac,
            ps.ptra,
            ps.charge_essieu
        FROM camions_entrants ce
        INNER JOIN dechargements_camions d ON ce.idEntree = d.idEntree
        LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
        LEFT JOIN port p ON ce.idPort = p.id
        LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
        WHERE ce.idEntree NOT IN (
            SELECT idEntree FROM camions_sortants
        )
        ORDER BY d.date_dechargement ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $camions_decharges_non_sortis = $result->fetch_all(MYSQLI_ASSOC);
    
    // Récupérer les marchandises pour chaque déchargement
    if (!empty($camions_decharges_non_sortis)) {
        $dechargement_ids = array_column($camions_decharges_non_sortis, 'idDechargement');
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
    }
    
} catch (Exception $e) {
    $message = "Erreur lors du chargement des camions déchargés: " . $e->getMessage();
    $message_type = "error";
    error_log("Erreur chargement camions déchargés: " . $e->getMessage());
}

// Filtrer les doublons
$camions_charges_ids = array_column($camions_charges_non_sortis, 'idEntree');
$camions_decharges_ids = array_column($camions_decharges_non_sortis, 'idEntree');

// Supprimer les camions déchargés de la liste des chargés s'ils y sont aussi
$camions_charges_filtres = array_filter($camions_charges_non_sortis, function($camion) use ($camions_decharges_ids) {
    return !in_array($camion['idEntree'], $camions_decharges_ids);
});

$camions_decharges_filtres = $camions_decharges_non_sortis;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enregistrement des Sorties - Agent Sortie</title>
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
        
        .camion-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            background-color: white;
            transition: all 0.3s;
        }
        
        .camion-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
        
        .marchandise-item {
            border-left: 3px solid #3b82f6;
            background-color: #f8fafc;
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 4px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
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
            max-width: 800px;
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
        
        .section-header {
            border-left: 4px solid;
            padding-left: 16px;
            margin-bottom: 20px;
        }
        
        .section-charge {
            border-left-color: #f59e0b;
        }
        
        .section-decharge {
            border-left-color: #10b981;
        }
        
        .state-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .state-vide {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .state-charge {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .state-decharge {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .state-sorti {
            background-color: #e5e7eb;
            color: #374151;
        }
        
        .pesage-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .pesage-complet {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .pesage-incomplet {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .pesage-manquant {
            background-color: #fee2e2;
            color: #dc2626;
        }
        
        .marchandise-pesee {
            border-left-color: #10b981;
        }
        
        .marchandise-non-pesee {
            border-left-color: #ef4444;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen">
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
        
        <!-- Deux sections côte à côte -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Section 1: Camions chargés -->
            <div class="glass-card p-6">
                <div class="section-header section-charge">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-upload mr-2"></i>Camions Chargés
                    </h2>
                    <p class="text-sm text-gray-600">Camions avec chargement enregistré (non sortis)</p>
                    
                </div>
                
                <?php if (!empty($camions_charges_filtres)): ?>
                    <div class="scrollable-section">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-gray-100 border-b-2 border-gray-300">
                                    <th class="px-4 py-3 text-left font-bold whitespace-nowrap text-gray-700">Immatriculation</th>
                                    <th class="px-4 py-3 text-left font-bold whitespace-nowrap text-gray-700">Type</th>
                                    <th class="px-4 py-3 text-left font-bold whitespace-nowrap text-gray-700">Port</th>
                                    <th class="px-4 py-3 text-left font-bold whitespace-nowrap text-gray-700">Date Entrée</th>
                                    <th class="px-4 py-3 text-left font-bold whitespace-nowrap text-gray-700">Date Chargement</th>
                                    <th class="px-4 py-3 text-center font-bold whitespace-nowrap text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($camions_charges_filtres as $camion): 
                                    $pesage_status = $camion['pesage_complet'] ? 'complet' : 'incomplet';
                                    $pesage_class = $camion['pesage_complet'] ? 'pesage-complet' : 'pesage-incomplet';
                                    $pesage_text = $camion['pesage_complet'] ? 'Pesage complet' : 'Pesage incomplet';
                                ?>
                                <tr class="border-b border-gray-200 hover:bg-orange-50 transition-colors" id="camion-charge-<?php echo $camion['idEntree']; ?>">
                                    <td class="px-4 py-3 font-medium text-gray-800">
                                        <div class="flex items-center">
                                            <i class="fas fa-truck text-orange-600 mr-2"></i>
                                            <?php echo safe_html($camion['immatriculation']); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <?php echo safe_html($camion['type_camion']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <?php echo safe_html($camion['port']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <?php echo !empty($camion['date_entree']) ? date('d/m/Y', strtotime($camion['date_entree'])) : 'N/A'; ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <?php echo !empty($camion['date_chargement']) ? date('d/m/Y H:i', strtotime($camion['date_chargement'])) : 'N/A'; ?>
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <button type="button" 
                                                onclick="showDetails(<?php echo $camion['idEntree']; ?>, 'charge', <?php echo $camion['idChargement']; ?>)"
                                                class="bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold py-1 px-3 rounded-lg mr-2">
                                            <i class="fas fa-eye mr-1"></i> Détails
                                        </button>
                                        <?php if ($camion['pesage_complet']): ?>
                                        <form method="POST" class="inline-block" onsubmit="return confirmValidation('chargé', '<?php echo safe_html($camion['immatriculation']); ?>')">
                                            <input type="hidden" name="camion_id" value="<?php echo $camion['idEntree']; ?>">
                                            <input type="hidden" name="chargement_id" value="<?php echo $camion['idChargement']; ?>">
                                            <input type="hidden" name="dechargement_id" value="0">
                                            <input type="hidden" name="type_sortie" value="charge">
                                            <button type="submit" name="valider_sortie" 
                                                    class="bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-1 px-3 rounded-lg">
                                                <i class="fas fa-check-circle mr-1"></i> Valider
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <button type="button" 
                                                class="bg-gray-300 text-gray-500 text-xs font-bold py-1 px-3 rounded-lg cursor-not-allowed"
                                                title="Pesage incomplet">
                                            <i class="fas fa-times-circle mr-1"></i> Bloqué
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
                        <p class="text-lg text-gray-500">Aucun camion chargé en attente de sortie</p>
                        <p class="text-sm text-gray-400 mt-2">Tous les camions chargés ont déjà été enregistrés comme sortis</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Section 2: Camions déchargés -->
            <div class="glass-card p-6">
                <div class="section-header section-decharge">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-download mr-2"></i>Camions Déchargés
                    </h2>
                    <p class="text-sm text-gray-600">Camions avec déchargement enregistré (non sortis)</p>
                </div>
                
                <?php if (!empty($camions_decharges_filtres)): ?>
                    <div class="scrollable-section">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-gray-100 border-b-2 border-gray-300">
                                    <th class="px-4 py-3 text-left font-bold whitespace-nowrap text-gray-700">Immatriculation</th>
                                    <th class="px-4 py-3 text-left font-bold whitespace-nowrap text-gray-700">Type</th>
                                    <th class="px-4 py-3 text-left font-bold whitespace-nowrap text-gray-700">Port</th>
                                    <th class="px-4 py-3 text-left font-bold whitespace-nowrap text-gray-700">Date Entrée</th>
                                    <th class="px-4 py-3 text-left font-bold whitespace-nowrap text-gray-700">Date Déchargement</th>
                                    <th class="px-4 py-3 text-center font-bold whitespace-nowrap text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($camions_decharges_filtres as $camion): ?>
                                <tr class="border-b border-gray-200 hover:bg-green-50 transition-colors" id="camion-decharge-<?php echo $camion['idEntree']; ?>">
                                    <td class="px-4 py-3 font-medium text-gray-800">
                                        <div class="flex items-center">
                                            <i class="fas fa-truck text-green-600 mr-2"></i>
                                            <?php echo safe_html($camion['immatriculation']); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <?php echo safe_html($camion['type_camion']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <?php echo safe_html($camion['port']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <?php echo !empty($camion['date_entree']) ? date('d/m/Y', strtotime($camion['date_entree'])) : 'N/A'; ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <?php echo !empty($camion['date_dechargement']) ? date('d/m/Y H:i', strtotime($camion['date_dechargement'])) : 'N/A'; ?>
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <button type="button" 
                                                onclick="showDetails(<?php echo $camion['idEntree']; ?>, 'decharge', <?php echo $camion['idDechargement']; ?>)"
                                                class="bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold py-1 px-3 rounded-lg mr-2">
                                            <i class="fas fa-eye mr-1"></i> Détails
                                        </button>
                                        <form method="POST" class="inline-block" onsubmit="return confirmValidation('déchargé', '<?php echo safe_html($camion['immatriculation']); ?>')">
                                            <input type="hidden" name="camion_id" value="<?php echo $camion['idEntree']; ?>">
                                            <input type="hidden" name="chargement_id" value="0">
                                            <input type="hidden" name="dechargement_id" value="<?php echo $camion['idDechargement']; ?>">
                                            <input type="hidden" name="type_sortie" value="decharge">
                                            <button type="submit" name="valider_sortie" 
                                                    class="bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-1 px-3 rounded-lg">
                                                <i class="fas fa-check-circle mr-1"></i> Valider
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
                        <p class="text-lg text-gray-500">Aucun camion déchargé en attente de sortie</p>
                        <p class="text-sm text-gray-400 mt-2">Tous les camions déchargés ont déjà été enregistrés comme sortis</p>
                    </div>
                <?php endif; ?>
            </div>
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
        
        // Données des camions (pré-chargées depuis PHP)
        const camionsCharges = <?php echo json_encode($camions_charges_filtres); ?>;
        const camionsDecharges = <?php echo json_encode($camions_decharges_filtres); ?>;
        
        // Fonction pour afficher les détails dans une modal
        function showDetails(camionId, type, operationId) {
            let camion = null;
            let marchandises = [];
            let detailsPesage = [];
            let titre = '';
            
            if (type === 'charge') {
                camion = camionsCharges.find(c => c.idEntree == camionId);
                marchandises = marchandisesCharge[operationId] || [];
                detailsPesage = camion.details_pesage || [];
                titre = `Détails du camion ${camion.immatriculation} (Chargé)`;
            } else {
                camion = camionsDecharges.find(c => c.idEntree == camionId);
                marchandises = marchandisesDecharge[operationId] || [];
                titre = `Détails du camion ${camion.immatriculation} (Déchargé)`;
            }
            
            if (!camion) return;
            
            // Construire le contenu HTML
            let content = `
                <div class="mb-6">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <p class="text-xs text-gray-500">Immatriculation</p>
                            <p class="font-bold">${camion.immatriculation}</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <p class="text-xs text-gray-500">Type / Port</p>
                            <p class="font-bold">${camion.type_camion} / ${camion.port}</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <p class="text-xs text-gray-500">État actuel</p>
                            <p class="font-bold">${camion.etat}</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <p class="text-xs text-gray-500">Date entrée</p>
                            <p class="font-bold">${formatDate(camion.date_entree)}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-4 gap-3 mb-4">
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <p class="text-xs text-gray-500">PTAV</p>
                            <p class="font-bold">${formatNumber(camion.ptav)} kg</p>
                        </div>
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <p class="text-xs text-gray-500">PTAC</p>
                            <p class="font-bold">${formatNumber(camion.ptac)} kg</p>
                        </div>
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <p class="text-xs text-gray-500">PTRA</p>
                            <p class="font-bold">${formatNumber(camion.ptra)} kg</p>
                        </div>
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <p class="text-xs text-gray-500">Charge Essieu</p>
                            <p class="font-bold">${formatNumber(camion.charge_essieu)} kg</p>
                        </div>
                    </div>
                </div>
            `;
            
            // Ajouter les informations spécifiques selon le type
            if (type === 'charge') {
                // Section pesage
                content += `
                    <div class="mb-6">
                        <div class="${camion.pesage_complet ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'} rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center">
                                    <i class="fas fa-weight-scale ${camion.pesage_complet ? 'text-green-600' : 'text-yellow-600'} mr-3 text-lg"></i>
                                    <div>
                                        <p class="text-xs text-gray-600">État du pesage</p>
                                        <p class="text-sm font-bold ${camion.pesage_complet ? 'text-green-700' : 'text-yellow-700'}">
                                            ${camion.pesage_complet ? 'COMPLET' : 'INCOMPLET'}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-600">Marchandises pesées</p>
                                    <p class="text-sm font-bold ${camion.pesage_complet ? 'text-green-700' : 'text-yellow-700'}">
                                        ${camion.nb_marchandises_pesees} / ${camion.nb_marchandises}
                                    </p>
                                </div>
                            </div>
                            ${camion.pesage_complet ? '' : `
                                <div class="mt-2 text-xs text-red-600 bg-red-100 p-2 rounded">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Le pesage n'est pas complet. Impossible de valider la sortie.
                                </div>
                            `}
                        </div>
                    </div>
                `;
                
                // Informations de chargement
                content += `
                    <div class="mb-6">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-alt text-yellow-600 mr-3"></i>
                                <div>
                                    <p class="text-xs text-gray-600">Date et heure de chargement</p>
                                    <p class="text-sm font-bold text-gray-800">
                                        ${formatDate(camion.date_chargement)}
                                    </p>
                                </div>
                            </div>
                            ${camion.note_chargement ? `
                                <div class="mt-3">
                                    <p class="text-xs text-gray-600">Note de chargement</p>
                                    <p class="text-sm text-gray-700 mt-1">${camion.note_chargement}</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            } else {
                content += `
                    <div class="mb-6">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-alt text-green-600 mr-3"></i>
                                <div>
                                    <p class="text-xs text-gray-600">Date et heure de déchargement</p>
                                    <p class="text-sm font-bold text-gray-800">
                                        ${formatDate(camion.date_dechargement)}
                                    </p>
                                </div>
                            </div>
                            ${camion.note_dechargement ? `
                                <div class="mt-3">
                                    <p class="text-xs text-gray-600">Note de déchargement</p>
                                    <p class="text-sm text-gray-700 mt-1">${camion.note_dechargement}</p>
                                </div>
                            ` : ''}
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
                    const poids = isPese ? detail.poids : 'Non pesé';
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
                                        <span class="font-medium">Poids:</span> ${poids} kg
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
                    content += `
                        <div class="marchandise-item">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-gray-800">${march.nom_marchandise}</p>
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
        
        // Fonction de confirmation avant validation
        function confirmValidation(type, immatriculation) {
            const typeLabel = type === 'chargé' ? 'CHARGÉ' : 'DÉCHARGÉ';
            return confirm(`⚠️ CONFIRMATION DE SORTIE\n\nImmatriculation : ${immatriculation}\nType : Camion ${typeLabel}\n\nÊtes-vous sûr de vouloir valider la sortie de ce camion ?\n\nCette action sera enregistrée définitivement.`);
        }
        
        // Fonctions utilitaires
        function formatNumber(num) {
            if (!num) return '0.00';
            return parseFloat(num).toLocaleString('fr-FR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
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
</body>
</html>