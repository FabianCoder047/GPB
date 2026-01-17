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

// Traitement du formulaire de chargement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['charger_camion'])) {
    try {
        // Debug temporaire
        error_log("=== DÉBUT TRAITEMENT CHARGEMENT ===");
        error_log("Camion ID: " . ($_POST['camion_id'] ?? 'N/A'));
        error_log("Données POST: " . print_r($_POST, true));
        
        $conn->begin_transaction();
        
        $camion_id = $_POST['camion_id'];
        $note_chargement = $_POST['note_chargement'] ?? '';
        
        // Vérifier que le camion ID n'est pas vide
        if (empty($camion_id)) {
            throw new Exception("Aucun camion sélectionné");
        }
        
        // Récupérer les marchandises
        $marchandises = [];
        
        // Vérifier si des marchandises ont été soumises
        if (isset($_POST['marchandises']) && is_array($_POST['marchandises'])) {
            error_log("Nombre de marchandises dans POST: " . count($_POST['marchandises']));
            
            foreach ($_POST['marchandises'] as $index => $march) {
                if (!empty($march['type'])) {
                    $marchandises[] = [
                        'type' => $march['type'],
                        'note' => $march['note'] ?? ''
                    ];
                    error_log("Marchandise $index: type=" . $march['type'] . ", note=" . ($march['note'] ?? 'vide'));
                }
            }
        }
        
        if (empty($marchandises)) {
            throw new Exception("Veuillez ajouter au moins une marchandise");
        }
        
        // Vérifier que le camion existe et est vide (sans UPDATE)
        $stmt = $conn->prepare("
            SELECT ce.*, ps.ptav, ps.ptac, ps.ptra, ps.charge_essieu
            FROM camions_entrants ce
            LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
            WHERE ce.idEntree = ? AND ce.etat = 'Vide'
            AND ce.idEntree NOT IN (
                SELECT idEntree FROM chargement_camions
            )
        ");
        $stmt->bind_param("i", $camion_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Camion non trouvé, déjà chargé ou non vide");
        }
        
        $camion = $result->fetch_assoc();
        
        // NE PAS METTRE À JOUR la table camions_entrants
        // Seulement insérer dans chargement_camions
        
        // Enregistrer le chargement dans la table chargement_camions
        $stmt = $conn->prepare("
            INSERT INTO chargement_camions 
            (idEntree, note_chargement, date_chargement)
            VALUES (?, ?, NOW())
        ");
        $stmt->bind_param("is", 
            $camion_id,
            $note_chargement
        );
        $stmt->execute();
        $chargement_id = $conn->insert_id;
        
        error_log("Chargement créé avec ID: $chargement_id");
        
        // Ajouter les marchandises chargées
        foreach ($marchandises as $march) {
            $stmt = $conn->prepare("
                INSERT INTO marchandise_chargement_camion 
                (idChargement, idTypeMarchandise, note, date_ajout)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->bind_param("iis", 
                $chargement_id,
                $march['type'],
                $march['note']
            );
            $stmt->execute();
        }
        
        $conn->commit();
        
        $message = "Camion chargé avec succès !";
        $message_type = "success";
        
        // Rediriger pour éviter la resoumission du formulaire
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&camion=" . $camion_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Erreur lors du chargement: " . $e->getMessage();
        $message_type = "error";
        error_log("Erreur chargement: " . $e->getMessage());
    }
}

// Vérifier si succès après redirection
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "Camion chargé avec succès !";
    $message_type = "success";
}

// Récupérer la liste des camions vides (avec leurs informations de pesage)
$camions_vides = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            ce.*, 
            tc.nom as type_camion, 
            p.nom as port, 
            ps.ptav,
            ps.ptac,
            ps.ptra,
            ps.charge_essieu,
            DATE(ce.date_entree) as date_entree_format,
            TIME(ce.date_entree) as heure_entree
        FROM camions_entrants ce
        LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
        LEFT JOIN port p ON ce.idPort = p.id
        LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
        WHERE ce.etat = 'Vide'
        AND ce.idEntree NOT IN (
            SELECT idEntree FROM chargement_camions
        )
        ORDER BY ce.date_entree DESC
        LIMIT 50
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $camions_vides = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $message = "Erreur lors du chargement des camions vides: " . $e->getMessage();
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

// Récupérer la liste des camions récemment chargés
$camions_charges = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            ce.*,
            tc.nom as type_camion,
            p.nom as port,
            ps.ptav,
            ps.ptac,
            ps.ptra,
            ps.charge_essieu,
            cc.date_chargement,
            cc.note_chargement,
            GROUP_CONCAT(DISTINCT tm.nom SEPARATOR ', ') as marchandises_chargees
        FROM camions_entrants ce
        LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
        LEFT JOIN port p ON ce.idPort = p.id
        LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
        INNER JOIN chargement_camions cc ON ce.idEntree = cc.idEntree
        LEFT JOIN marchandise_chargement_camion mcc ON cc.idChargement = mcc.idChargement
        LEFT JOIN type_marchandise tm ON mcc.idTypeMarchandise = tm.id
        GROUP BY ce.idEntree, cc.idChargement
        ORDER BY cc.date_chargement DESC
        LIMIT 20
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $camions_charges = $result->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $message = "Erreur lors du chargement des camions chargés: " . $e->getMessage();
    $message_type = "error";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Entrepôt - Chargement des Camions</title>
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
        
        .marchandise-item {
            border-left: 4px solid #3b82f6;
            background-color: #f8fafc;
        }
        
        .remove-marchandise {
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .marchandise-item:hover .remove-marchandise {
            opacity: 1;
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
        
        .camion-row.selected {
            background-color: #dbeafe;
            border-left: 4px solid #3b82f6;
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
            <!-- Section 1: Liste des camions vides et formulaire -->
            <div>
                <!-- Liste des camions vides -->
                <div class="glass-card p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6 pb-4 border-b">
                        <i class="fas fa-truck mr-2"></i>Camions Vides Disponibles
                    </h2>
                    
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PTAV (kg)</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PTAC (kg)</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date entrée</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($camions_vides as $camion): ?>
                                <tr class="camion-row" 
                                    data-id="<?php echo $camion['idEntree']; ?>"
                                    data-immatriculation="<?php echo safe_html($camion['immatriculation']); ?>"
                                    data-type-camion="<?php echo safe_html($camion['type_camion']); ?>"
                                    data-port="<?php echo safe_html($camion['port']); ?>"
                                    data-chauffeur="<?php echo safe_html($camion['prenom_chauffeur'] . ' ' . $camion['nom_chauffeur']); ?>"
                                    data-ptav="<?php echo $camion['ptav']; ?>"
                                    data-ptac="<?php echo $camion['ptac']; ?>"
                                    data-ptra="<?php echo $camion['ptra']; ?>"
                                    data-charge-essieu="<?php echo $camion['charge_essieu']; ?>"
                                    onclick="selectCamion(this)">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-truck text-blue-600 text-sm"></i>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo safe_html($camion['immatriculation']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo safe_html($camion['type_camion']); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo number_format($camion['ptav'], 2); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo number_format($camion['ptac'], 2); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $camion['date_entree_format']; ?><br>
                                        <span class="text-xs"><?php echo $camion['heure_entree']; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($camions_vides)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-3xl text-gray-300 mb-2 block"></i>
                                        Aucun camion vide disponible pour le chargement
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Formulaire de chargement (caché par défaut) -->
                <div id="form-chargement-container" class="glass-card p-6 hidden-form">
                    <h2 class="text-xl font-bold text-gray-800 mb-6 pb-4 border-b">
                        <i class="fas fa-edit mr-2"></i>Formulaire de Chargement
                        <span id="selected-camion-title" class="text-blue-600 ml-2"></span>
                    </h2>
                    
                    <form id="formChargement" method="POST" class="space-y-6">
                        <!-- Champ caché pour l'ID du camion -->
                        <input type="hidden" id="selected_camion_id" name="camion_id" value="" required>
                        
                        <!-- Informations du camion sélectionné -->
                        <div id="camion-selected-info" class="mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <p class="text-xs text-gray-600 mb-1">Immatriculation</p>
                                    <p id="info-immatriculation" class="text-lg font-bold text-blue-800">-</p>
                                </div>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <p class="text-xs text-gray-600 mb-1">Type / Port</p>
                                    <p id="info-type-port" class="text-lg font-bold text-blue-800">-</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                    <p class="text-xs text-gray-600 mb-1">PTAV</p>
                                    <p id="info-ptav" class="text-lg font-bold text-blue-800">0.00 kg</p>
                                </div>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                    <p class="text-xs text-gray-600 mb-1">PTAC</p>
                                    <p id="info-ptac" class="text-lg font-bold text-blue-800">0.00 kg</p>
                                </div>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                    <p class="text-xs text-gray-600 mb-1">PTRA</p>
                                    <p id="info-ptra" class="text-lg font-bold text-blue-800">0.00 kg</p>
                                </div>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                    <p class="text-xs text-gray-600 mb-1">Charge Essieu Max</p>
                                    <p id="info-charge-essieu" class="text-lg font-bold text-blue-800">0.00 kg</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section des marchandises -->
                        <div class="border-t pt-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-gray-800">
                                    <i class="fas fa-boxes mr-2"></i>Marchandises à charger
                                </h3>
                                <button type="button" id="add-marchandise" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white text-sm font-bold py-2 px-4 rounded-lg">
                                    <i class="fas fa-plus mr-1"></i>Ajouter
                                </button>
                            </div>
                            
                            <div id="marchandises-container" class="space-y-4">
                                <!-- Template pour une marchandise -->
                                <div class="marchandise-item p-4 rounded-lg hidden template">
                                    <div class="flex justify-between items-start mb-3">
                                        <h4 class="font-medium text-gray-800">Marchandise #<span class="counter">1</span></h4>
                                        <button type="button" class="remove-marchandise text-red-500 hover:text-red-700">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="w-full mb-3">
                                        <label class="block text-gray-700 text-xs font-bold mb-1">Type de marchandise *</label>
                                        <select name="marchandises[][type]" 
                                                class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 marchandise-type" required>
                                            <option value="">Sélectionnez...</option>
                                            <?php foreach ($types_marchandises as $type): ?>
                                            <option value="<?php echo $type['id']; ?>">
                                                <?php echo safe_html($type['nom']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="w-full">
                                        <label class="block text-gray-700 text-xs font-bold mb-1">Note (optionnelle)</label>
                                        <textarea name="marchandises[][note]" rows="2"
                                                  placeholder="Informations supplémentaires..."
                                                  class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notes -->
                        <div class="form-group">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="note_chargement">
                                <i class="fas fa-sticky-note mr-2"></i>Notes sur le chargement
                            </label>
                            <textarea id="note_chargement" name="note_chargement" rows="3"
                                      placeholder="Observations, problèmes rencontrés, etc."
                                      class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        
                        <!-- Bouton de soumission -->
                        <div class="flex justify-end pt-6 border-t">
                            <button type="submit" name="charger_camion" id="submit-button"
                                    class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-8 rounded-lg text-lg transition duration-300">
                                <i class="fas fa-check-circle mr-2"></i>Valider le chargement
                            </button>
                            <button type="button" id="cancel-chargement"
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-6 rounded-lg text-lg transition duration-300 ml-3">
                                Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Section 2: Liste des camions chargés -->
            <div class="glass-card p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6 pb-4 border-b">
                    <i class="fas fa-list mr-2"></i>Camions Récemment Chargés
                </h2>
                
                <div class="scrollable-section">
                    <div class="space-y-4">
                        <?php foreach ($camions_charges as $camion): ?>
                        <div class="border rounded-lg p-4 hover:bg-gray-50 transition duration-200">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <div class="flex items-center mb-2">
                                        <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-truck text-blue-600 text-sm"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="font-bold text-gray-800">
                                                <?php echo safe_html($camion['immatriculation']); ?>
                                            </h3>
                                            <p class="text-sm text-gray-600">
                                                <?php echo safe_html($camion['type_camion']); ?> - 
                                                <?php echo date('H:i', strtotime($camion['date_chargement'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                    CHARGÉ
                                </span>
                            </div>
                            
                            <!-- Informations techniques -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">PTAV</p>
                                    <p class="text-sm font-bold"><?php echo number_format($camion['ptav'], 2); ?> kg</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">PTAC</p>
                                    <p class="text-sm font-bold"><?php echo number_format($camion['ptac'], 2); ?> kg</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">PTRA</p>
                                    <p class="text-sm font-bold"><?php echo number_format($camion['ptra'], 2); ?> kg</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">Charge Essieu</p>
                                    <p class="text-sm font-bold"><?php echo number_format($camion['charge_essieu'], 2); ?> kg</p>
                                </div>
                            </div>
                            
                            <!-- Marchandises chargées -->
                            <?php if (!empty($camion['marchandises_chargees'])): ?>
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <p class="text-xs text-gray-600 mb-1">
                                    <i class="fas fa-box mr-1"></i>Marchandises chargées:
                                </p>
                                <p class="text-sm text-gray-800 font-medium">
                                    <?php echo safe_html($camion['marchandises_chargees']); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($camion['note_chargement'])): ?>
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <p class="text-xs text-gray-600">
                                    <i class="fas fa-sticky-note mr-1"></i>
                                    <?php echo substr(safe_html($camion['note_chargement']), 0, 100); ?>
                                    <?php echo strlen($camion['note_chargement']) > 100 ? '...' : ''; ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($camions_charges)): ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-4"></i>
                            <p class="text-lg">Aucun camion chargé aujourd'hui</p>
                            <p class="text-sm mt-2">Commencez par charger un camion dans le formulaire</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Variables globales
        let marchandiseCounter = 1;
        let selectedRow = null;
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion de l'ajout de marchandise
            document.getElementById('add-marchandise').addEventListener('click', addMarchandise);
            
            // Gestion de l'annulation
            document.getElementById('cancel-chargement').addEventListener('click', function() {
                document.getElementById('form-chargement-container').classList.remove('visible-form');
                document.getElementById('form-chargement-container').classList.add('hidden-form');
                if (selectedRow) {
                    selectedRow.classList.remove('selected');
                    selectedRow = null;
                }
                resetForm();
            });
            
            // Simplification : soumission directe sans validation JavaScript
            // La validation se fera côté PHP
        });
        
        // Fonction pour sélectionner un camion
        function selectCamion(row) {
            // Désélectionner la ligne précédente
            if (selectedRow) {
                selectedRow.classList.remove('selected');
            }
            
            // Sélectionner la nouvelle ligne
            row.classList.add('selected');
            selectedRow = row;
            
            // Récupérer les données
            const camionId = row.getAttribute('data-id');
            const immatriculation = row.getAttribute('data-immatriculation');
            const typeCamion = row.getAttribute('data-type-camion');
            const port = row.getAttribute('data-port');
            
            // Mettre à jour le formulaire
            document.getElementById('selected_camion_id').value = camionId;
            document.getElementById('selected-camion-title').textContent = immatriculation;
            document.getElementById('info-immatriculation').textContent = immatriculation;
            document.getElementById('info-type-port').textContent = typeCamion + ' / ' + port;
            document.getElementById('info-ptav').textContent = formatPoids(parseFloat(row.getAttribute('data-ptav')) || 0);
            document.getElementById('info-ptac').textContent = formatPoids(parseFloat(row.getAttribute('data-ptac')) || 0);
            document.getElementById('info-ptra').textContent = formatPoids(parseFloat(row.getAttribute('data-ptra')) || 0);
            document.getElementById('info-charge-essieu').textContent = formatPoids(parseFloat(row.getAttribute('data-charge-essieu')) || 0);
            
            // Réinitialiser les marchandises
            resetMarchandises();
            
            // Afficher le formulaire
            document.getElementById('form-chargement-container').classList.remove('hidden-form');
            document.getElementById('form-chargement-container').classList.add('visible-form');
            
            // Ajouter une première marchandise
            addMarchandise();
            
            // Scroll vers le formulaire
            document.getElementById('form-chargement-container').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Fonction pour ajouter une marchandise
        function addMarchandise() {
            const container = document.getElementById('marchandises-container');
            const template = container.querySelector('.template').cloneNode(true);
            
            template.classList.remove('template', 'hidden');
            template.querySelector('.counter').textContent = marchandiseCounter;
            
            // Gestion de la suppression
            template.querySelector('.remove-marchandise').addEventListener('click', function() {
                template.remove();
                updateCounters();
            });
            
            container.appendChild(template);
            marchandiseCounter++;
        }
        
        // Fonction pour réinitialiser les marchandises
        function resetMarchandises() {
            const container = document.getElementById('marchandises-container');
            // Supprimer toutes les marchandises sauf le template
            const marchandises = container.querySelectorAll('.marchandise-item:not(.template)');
            marchandises.forEach(item => item.remove());
            
            marchandiseCounter = 1;
        }
        
        // Fonction pour réinitialiser le formulaire
        function resetForm() {
            document.getElementById('selected_camion_id').value = '';
            document.getElementById('selected-camion-title').textContent = '';
            document.getElementById('note_chargement').value = '';
            resetMarchandises();
            
            // Réactiver le bouton de soumission
            const submitBtn = document.getElementById('submit-button');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Valider le chargement';
        }
        
        // Fonction pour mettre à jour les compteurs
        function updateCounters() {
            const items = document.querySelectorAll('.marchandise-item:not(.template)');
            let counter = 1;
            
            items.forEach(item => {
                item.querySelector('.counter').textContent = counter;
                counter++;
            });
            
            marchandiseCounter = counter;
        }
        
        // Fonction pour formater les poids
        function formatPoids(poids) {
            return new Intl.NumberFormat('fr-FR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(poids) + ' kg';
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