<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'enregistreurEntreeBateau') {
    header("Location: ../../login.php");
    exit();
}

// Fonction utilitaire pour éviter les erreurs de dépréciation
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Variables pour la recherche et filtres
$search = $_GET['search'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$etat = $_GET['etat'] ?? '';
$type_bateau = $_GET['type_bateau'] ?? '';

// Récupérer les types de bateaux pour le filtre
$types_bateaux = [];
try {
    $result = $conn->query("SELECT * FROM type_bateau ORDER BY nom");
    $types_bateaux = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = "Erreur lors du chargement des types de bateaux: " . $e->getMessage();
}

// Récupérer les bateaux avec filtres
$bateaux = [];
$total_bateaux = 0;
$bateaux_vides = 0;
$bateaux_charges = 0;

try {
    // Construire la requête de base
    $query = "
        SELECT be.*, tb.nom as type_bateau, p.nom as port_nom,
               (SELECT COUNT(*) FROM marchandise_bateau_entrant WHERE id_bateau_entrant = be.id) as nb_marchandises
        FROM bateau_entrant be
        LEFT JOIN type_bateau tb ON be.id_type_bateau = tb.id
        LEFT JOIN port p ON be.id_port = p.id
        WHERE 1=1
    ";
    
    $params = [];
    $types = '';
    
    // Filtre par recherche
    if (!empty($search)) {
        $query .= " AND (be.nom_navire LIKE ? OR be.immatriculation LIKE ? OR be.nom_capitaine LIKE ? OR be.prenom_capitaine LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $types .= 'ssss';
    }
    
    // Filtre par date
    if (!empty($date_debut)) {
        $query .= " AND DATE(be.date_entree) >= ?";
        $params[] = $date_debut;
        $types .= 's';
    }
    
    if (!empty($date_fin)) {
        $query .= " AND DATE(be.date_entree) <= ?";
        $params[] = $date_fin;
        $types .= 's';
    }
    
    // Filtre par état
    if (!empty($etat) && $etat !== 'tous') {
        $query .= " AND be.etat = ?";
        $params[] = $etat;
        $types .= 's';
    }
    
    // Filtre par type de bateau
    if (!empty($type_bateau) && is_numeric($type_bateau)) {
        $query .= " AND be.id_type_bateau = ?";
        $params[] = $type_bateau;
        $types .= 'i';
    }
    
    // Ajouter l'ordre et la limite
    $query .= " ORDER BY be.date_entree DESC";
    
    // Préparer et exécuter la requête
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }
    
    $bateaux = $result->fetch_all(MYSQLI_ASSOC);
    $total_bateaux = count($bateaux);
    
    // Compter les bateaux par état
    foreach ($bateaux as $bateau) {
        if ($bateau['etat'] == 'vide') {
            $bateaux_vides++;
        } elseif ($bateau['etat'] == 'chargé') {
            $bateaux_charges++;
        }
    }
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Bateaux - Entrées</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .scrollable-table {
            max-height: 65vh;
            overflow-y: auto;
        }
        
        .sticky-header {
            position: sticky;
            top: 0;
            background-color: #ffffff;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .highlight-row {
            background-color: #f0f9ff;
        }
        
        .bateau-card {
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }
        
        .bateau-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: #3b82f6;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            animation: fadeIn 0.3s ease-out;
        }
        
        .modal-content {
            animation: slideIn 0.3s ease-out;
            max-height: 85vh;
            overflow-y: auto;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .info-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .card-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-gray-100 min-h-screen">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo safe_html($error); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Formulaire de filtres -->
        <div class="bg-white shadow rounded-xl p-4 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-filter mr-2"></i>Filtres de Recherche
                </h2>
                <button type="button" onclick="resetFilters()" 
                        class="text-sm text-gray-600 hover:text-gray-800 flex items-center">
                    <i class="fas fa-redo mr-1"></i>Réinitialiser
                </button>
            </div>
            
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="search">
                            <i class="fas fa-search mr-1"></i>Recherche
                        </label>
                        <input type="text" id="search" name="search" 
                               value="<?php echo safe_html($search); ?>"
                               placeholder="Nom, immatriculation, capitaine..."
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="date_debut">
                            <i class="fas fa-calendar-alt mr-1"></i>Date début
                        </label>
                        <input type="date" id="date_debut" name="date_debut"
                               value="<?php echo safe_html($date_debut); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="date_fin">
                            <i class="fas fa-calendar-alt mr-1"></i>Date fin
                        </label>
                        <input type="date" id="date_fin" name="date_fin"
                               value="<?php echo safe_html($date_fin); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="etat">
                            <i class="fas fa-box mr-1"></i>État
                        </label>
                        <select id="etat" name="etat"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="tous" <?php echo $etat === 'tous' || $etat === '' ? 'selected' : ''; ?>>Tous les états</option>
                            <option value="vide" <?php echo $etat === 'vide' ? 'selected' : ''; ?>>Vide</option>
                            <option value="chargé" <?php echo $etat === 'chargé' ? 'selected' : ''; ?>>Chargé</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="type_bateau">
                            <i class="fas fa-ship mr-1"></i>Type de bateau
                        </label>
                        <select id="type_bateau" name="type_bateau"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Tous les types</option>
                            <?php foreach ($types_bateaux as $type): ?>
                                <option value="<?php echo $type['id']; ?>"
                                    <?php echo $type_bateau == $type['id'] ? 'selected' : ''; ?>>
                                    <?php echo safe_html($type['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-3 flex items-end justify-end space-x-2">
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition duration-200 flex items-center">
                            <i class="fas fa-search mr-2"></i>Appliquer les filtres
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Liste des bateaux -->
        <div class="bg-white shadow rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">
                            <i class="fas fa-list mr-2"></i>Liste des Bateaux
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">
                            <?php echo $total_bateaux; ?> bateau(x) trouvé(s) 
                            <?php if ($date_debut || $date_fin): ?>
                                entre <?php echo $date_debut ? date('d/m/Y', strtotime($date_debut)) : '...'; ?> 
                                et <?php echo $date_fin ? date('d/m/Y', strtotime($date_fin)) : '...'; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="mt-2 md:mt-0 flex items-center space-x-2">
                        <button onclick="exportToPDF()" 
                                class="px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg text-sm font-medium flex items-center">
                            <i class="fas fa-file-pdf mr-1"></i>Exporter
                        </button>
                        <button onclick="exportToExcel()" 
                                class="px-3 py-1.5 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg text-sm font-medium flex items-center">
                            <i class="fas fa-file-excel mr-1"></i>Exporter
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Vue tableau -->
            <div id="tableView" class="scrollable-table">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="sticky-header bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bateau</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capitaine</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type / État</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Port / Agence</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Entrée</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($bateaux as $bateau): ?>
                        <tr class="hover:bg-blue-50 transition-colors duration-150">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                                        <i class="fas fa-ship text-white"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-bold text-gray-900">
                                            <?php echo safe_html($bateau['nom_navire']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 font-medium">
                                            <?php echo safe_html($bateau['immatriculation']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-user text-gray-400 mr-1"></i>
                                    <?php echo safe_html(($bateau['prenom_capitaine'] ?? '') . ' ' . ($bateau['nom_capitaine'] ?? '')); ?>
                                </div>
                                <?php if ($bateau['tel_capitaine']): ?>
                                <div class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-phone text-gray-400 mr-1"></i>
                                    <?php echo safe_html($bateau['tel_capitaine']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 mb-1">
                                    <?php echo safe_html($bateau['type_bateau'] ?? 'Non spécifié'); ?>
                                </div>
                                <div>
                                    <span class="info-badge <?php echo $bateau['etat'] == 'chargé' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <i class="fas <?php echo $bateau['etat'] == 'chargé' ? 'fa-box' : 'fa-box-open'; ?> mr-1"></i>
                                        <?php echo safe_html($bateau['etat']); ?>
                                        <?php if ($bateau['etat'] == 'chargé' && $bateau['nb_marchandises'] > 0): ?>
                                            <span class="ml-1">(<?php echo $bateau['nb_marchandises']; ?>)</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-anchor text-gray-400 mr-1"></i>
                                    <?php echo safe_html($bateau['port_nom'] ?? 'Non spécifié'); ?>
                                </div>
                                <?php if ($bateau['agence']): ?>
                                <div class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-building text-gray-400 mr-1"></i>
                                    <?php echo safe_html($bateau['agence']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-gray-900">
                                    <?php echo date('d/m/Y', strtotime($bateau['date_entree'] ?? '')); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo date('H:i', strtotime($bateau['date_entree'] ?? '')); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex space-x-2">
                                    <button onclick="openModal(<?php echo $bateau['id']; ?>)" 
                                            class="inline-flex items-center px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-sm font-medium transition duration-200">
                                        <i class="fas fa-eye mr-1.5"></i>Détails
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($bateaux)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="text-gray-400">
                                    <i class="fas fa-ship text-4xl mb-3"></i>
                                    <p class="text-lg font-medium text-gray-500">Aucun bateau trouvé</p>
                                    <p class="text-sm text-gray-400 mt-1">Essayez de modifier vos critères de recherche</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Vue cartes (cachée par défaut) -->
            <div id="cardView" class="hidden p-6 card-grid">
                <?php foreach ($bateaux as $bateau): ?>
                <div class="bateau-card bg-white p-4 hover:shadow-lg">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <div class="flex items-center">
                                <div class="h-8 w-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center mr-2">
                                    <i class="fas fa-ship text-white text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800"><?php echo safe_html($bateau['nom_navire']); ?></h3>
                                    <p class="text-xs text-gray-500"><?php echo safe_html($bateau['immatriculation']); ?></p>
                                </div>
                            </div>
                        </div>
                        <span class="info-badge <?php echo $bateau['etat'] == 'chargé' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo safe_html($bateau['etat']); ?>
                        </span>
                    </div>
                    
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-user text-gray-400 mr-2 w-4"></i>
                            <span><?php echo safe_html(($bateau['prenom_capitaine'] ?? '') . ' ' . ($bateau['nom_capitaine'] ?? '')); ?></span>
                        </div>
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-anchor text-gray-400 mr-2 w-4"></i>
                            <span><?php echo safe_html($bateau['port_nom'] ?? 'Non spécifié'); ?></span>
                        </div>
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-ship text-gray-400 mr-2 w-4"></i>
                            <span><?php echo safe_html($bateau['type_bateau'] ?? 'Non spécifié'); ?></span>
                        </div>
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-calendar text-gray-400 mr-2 w-4"></i>
                            <span><?php echo date('d/m/Y', strtotime($bateau['date_entree'] ?? '')); ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-t">
                        <button onclick="openModal(<?php echo $bateau['id']; ?>)" 
                                class="w-full inline-flex items-center justify-center px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-sm font-medium transition duration-200">
                            <i class="fas fa-eye mr-1.5"></i>Voir les détails
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($bateaux)): ?>
                <div class="col-span-full">
                    <div class="text-center py-12">
                        <i class="fas fa-ship text-4xl text-gray-300 mb-3"></i>
                        <p class="text-lg font-medium text-gray-500">Aucun bateau trouvé</p>
                        <p class="text-sm text-gray-400 mt-1">Essayez de modifier vos critères de recherche</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modale pour afficher les détails -->
    <div id="modalOverlay" class="modal-overlay">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div id="modalContent" class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-3xl">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
        </div>
    </div>
    
    <script>
        // Variables globales
        let currentView = 'table';
        
        // Fonction pour ouvrir la modale avec les détails
        async function openModal(bateauId) {
            try {
                // Afficher le loader
                document.getElementById('modalContent').innerHTML = `
                    <div class="p-8 text-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                        <p class="mt-4 text-gray-600">Chargement des détails...</p>
                    </div>
                `;
                
                // Afficher la modale
                document.getElementById('modalOverlay').style.display = 'block';
                
                // Récupérer les détails via AJAX
                const response = await fetch(`get_bateau_details.php?id=${bateauId}`);
                const data = await response.json();
                
                // Construire le contenu de la modale
                document.getElementById('modalContent').innerHTML = `
                    <div class="p-6">
                        <!-- En-tête de la modale -->
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">
                                    <i class="fas fa-ship mr-2"></i>${data.nom_navire || 'Bateau'}
                                </h2>
                                <p class="text-gray-600">Immatriculation: ${data.immatriculation || 'Non spécifiée'}</p>
                            </div>
                            <button onclick="closeModal()" 
                                    class="text-gray-400 hover:text-gray-600 text-xl">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <!-- Informations principales -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <!-- Colonne gauche -->
                            <div class="space-y-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">
                                        <i class="fas fa-info-circle mr-1"></i>Informations générales
                                    </h3>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <div class="space-y-2">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Type de bateau:</span>
                                                <span class="font-medium">${data.type_bateau || 'Non spécifié'}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">État:</span>
                                                <span class="font-medium ${data.etat === 'chargé' ? 'text-green-600' : 'text-gray-600'}">
                                                    ${data.etat || 'Non spécifié'}
                                                </span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Port de provenance:</span>
                                                <span class="font-medium">${data.port_nom || 'Non spécifié'}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Agence:</span>
                                                <span class="font-medium">${data.agence || 'Non spécifiée'}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">
                                        <i class="fas fa-ruler-combined mr-1"></i>Dimensions
                                    </h3>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <div class="grid grid-cols-3 gap-2 text-center">
                                            <div class="p-2 bg-white rounded">
                                                <p class="text-xs text-gray-500">Longueur</p>
                                                <p class="font-bold text-lg">${data.longueur || '0'} m</p>
                                            </div>
                                            <div class="p-2 bg-white rounded">
                                                <p class="text-xs text-gray-500">Largeur</p>
                                                <p class="font-bold text-lg">${data.largeur || '0'} m</p>
                                            </div>
                                            <div class="p-2 bg-white rounded">
                                                <p class="text-xs text-gray-500">Hauteur</p>
                                                <p class="font-bold text-lg">${data.hauteur || '0'} m</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Colonne droite -->
                            <div class="space-y-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">
                                        <i class="fas fa-user-tie mr-1"></i>Capitaine
                                    </h3>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <div class="space-y-2">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Nom:</span>
                                                <span class="font-medium">${data.nom_capitaine || 'Non spécifié'}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Prénom:</span>
                                                <span class="font-medium">${data.prenom_capitaine || 'Non spécifié'}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Téléphone:</span>
                                                <span class="font-medium">${data.tel_capitaine || 'Non spécifié'}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">
                                        <i class="fas fa-calendar-alt mr-1"></i>Dates
                                    </h3>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <div class="space-y-2">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Date d'entrée:</span>
                                                <span class="font-medium">${new Date(data.date_entree).toLocaleDateString('fr-FR')}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Heure d'entrée:</span>
                                                <span class="font-medium">${new Date(data.date_entree).toLocaleTimeString('fr-FR')}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Agent d'enregistrement:</span>
                                                <span class="font-medium">${data.agent_enregistrement || 'Non spécifié'}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section marchandises -->
                        <div class="mb-6">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">
                                <i class="fas fa-boxes mr-1"></i>Marchandises
                            </h3>
                            ${data.marchandises && data.marchandises.length > 0 ? `
                                <div class="bg-gray-50 rounded-lg overflow-hidden">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-100">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Poids (t)</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Note</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                ${data.marchandises.map((marchandise, index) => `
                                                    <tr class="hover:bg-white">
                                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                                            ${marchandise.type_marchandise || 'Type inconnu'}
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-900">
                                                            ${marchandise.poids ? parseFloat(marchandise.poids).toFixed(2) : '0.00'} t
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-900">
                                                            ${marchandise.note || '-'}
                                                        </td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ` : `
                                <div class="bg-gray-50 rounded-lg p-8 text-center">
                                    <i class="fas fa-box-open text-3xl text-gray-300 mb-3"></i>
                                    <p class="text-gray-600">Aucune marchandise</p>
                                    ${data.etat === 'chargé' ? '<p class="text-sm text-gray-500 mt-1">Le bateau est marqué comme chargé mais aucune marchandise n\'a été enregistrée</p>' : ''}
                                </div>
                            `}
                        </div>
                        
                        <!-- Notes -->
                        ${data.note ? `
                            <div class="mb-6">
                                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">
                                    <i class="fas fa-sticky-note mr-1"></i>Notes et observations
                                </h3>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-gray-700">${data.note}</p>
                                </div>
                            </div>
                        ` : ''}
                        
                        <!-- Boutons d'action -->
                        <div class="flex justify-end space-x-3 pt-6 border-t">
                            <button onclick="closeModal()" 
                                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg transition duration-200">
                                Fermer
                            </button>
                            <a href="enregistrement.php?select=${bateauId}&mode=edit"
                               class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition duration-200">
                                <i class="fas fa-edit mr-1"></i>Modifier
                            </a>
                        </div>
                    </div>
                `;
                
            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('modalContent').innerHTML = `
                    <div class="p-8 text-center">
                        <i class="fas fa-exclamation-triangle text-3xl text-red-500 mb-3"></i>
                        <p class="text-lg font-medium text-gray-700">Erreur de chargement</p>
                        <p class="text-sm text-gray-500 mt-2">Impossible de charger les détails du bateau.</p>
                        <button onclick="closeModal()" 
                                class="mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg">
                            Fermer
                        </button>
                    </div>
                `;
            }
        }
        
        // Fonction pour fermer la modale
        function closeModal() {
            document.getElementById('modalOverlay').style.display = 'none';
        }
        
        // Fermer la modale en cliquant en dehors
        document.getElementById('modalOverlay').addEventListener('click', function(event) {
            if (event.target === this) {
                closeModal();
            }
        });
        
        // Fermer la modale avec la touche Échap
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
        
        // Fonction pour changer la vue (tableau/cartes)
        function toggleView() {
            const tableView = document.getElementById('tableView');
            const cardView = document.getElementById('cardView');
            
            if (currentView === 'table') {
                tableView.classList.add('hidden');
                cardView.classList.remove('hidden');
                currentView = 'card';
            } else {
                cardView.classList.add('hidden');
                tableView.classList.remove('hidden');
                currentView = 'table';
            }
        }
        
        // Fonction pour réinitialiser les filtres
        function resetFilters() {
            window.location.href = 'historiques.php';
        }
        
        // Fonction pour exporter en Excel
        function exportToExcel() {
            const table = document.querySelector('table');
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            // Parcourir chaque ligne
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    let text = cols[j].innerText;
                    text = text.replace(/[\n\r]+|[\s]{2,}/g, ' ').trim();
                    row.push(`"${text}"`);
                }
                csv.push(row.join(","));
            }
            
            // Créer et télécharger le fichier
            const csvString = csv.join("\n");
            const blob = new Blob(["\ufeff", csvString], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            
            if (navigator.msSaveBlob) {
                navigator.msSaveBlob(blob, "historiques.csv");
            } else {
                link.href = URL.createObjectURL(blob);
                link.download = "historiques_" + new Date().toISOString().split('T')[0] + ".csv";
                link.click();
            }
        }
        
        // Initialiser les dates par défaut (dernier mois)
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const oneMonthAgo = new Date();
            oneMonthAgo.setMonth(today.getMonth() - 1);
            
            const dateDebut = document.getElementById('date_debut');
            const dateFin = document.getElementById('date_fin');
            
            // Si les dates sont vides, mettre les valeurs par défaut
            if (!dateDebut.value) {
                dateDebut.valueAsDate = oneMonthAgo;
            }
            
            if (!dateFin.value) {
                dateFin.valueAsDate = today;
            }
            
            // Valider les dates
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                if (dateDebut.value && dateFin.value && dateDebut.value > dateFin.value) {
                    e.preventDefault();
                    alert('La date de début doit être antérieure à la date de fin');
                    dateDebut.focus();
                }
            });
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