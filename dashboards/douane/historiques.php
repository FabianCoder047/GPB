<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'agentDouane') {
    header("Location: ../login.php");
    exit();
}

// Fonction utilitaire
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Variables pour les filtres
$search = $_GET['search'] ?? '';
$type_entite = $_GET['type_entite'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$montant_min = $_GET['montant_min'] ?? '';
$montant_max = $_GET['montant_max'] ?? '';

// Initialiser les données
$frais_list = [];
$total_frais = 0;
$stats = [
    'total' => 0,
    'total_thc' => 0,
    'total_magasinage' => 0,
    'total_douane' => 0,
    'total_surestaries' => 0,
    'total_general' => 0
];

try {
    // Requête pour récupérer les frais avec informations associées
    $query = "SELECT 
                ft.*,
                CASE 
                    WHEN ft.type_entite = 'camion_entrant' THEN ce.immatriculation
                    WHEN ft.type_entite = 'camion_sortant' THEN cs_entrants.immatriculation
                    WHEN ft.type_entite = 'bateau_entrant' THEN be.nom_navire
                    WHEN ft.type_entite = 'bateau_sortant' THEN bs.nom_navire
                END as entite_nom,
                CASE 
                    WHEN ft.type_entite = 'camion_entrant' THEN CONCAT(ce.nom_chauffeur, ' ', ce.prenom_chauffeur)
                    WHEN ft.type_entite = 'camion_sortant' THEN CONCAT(cs_entrants.nom_chauffeur, ' ', cs_entrants.prenom_chauffeur)
                    WHEN ft.type_entite = 'bateau_entrant' THEN CONCAT(be.nom_capitaine, ' ', be.prenom_capitaine)
                    WHEN ft.type_entite = 'bateau_sortant' THEN CONCAT(bs.nom_capitaine, ' ', bs.prenom_capitaine)
                END as personne,
                CASE 
                    WHEN ft.type_entite = 'camion_entrant' THEN ce.date_entree
                    WHEN ft.type_entite = 'camion_sortant' THEN cs.date_sortie
                    WHEN ft.type_entite = 'bateau_entrant' THEN be.date_entree
                    WHEN ft.type_entite = 'bateau_sortant' THEN bs.date_sortie
                END as date_operation,
                (COALESCE(ft.frais_thc, 0) + COALESCE(ft.frais_magasinage, 0) + 
                 COALESCE(ft.droits_douane, 0) + COALESCE(ft.surestaries, 0)) as total
              FROM frais_transit ft
              LEFT JOIN camions_entrants ce ON ft.type_entite = 'camion_entrant' AND ft.id_entite = ce.idEntree
              LEFT JOIN camions_sortants cs ON ft.type_entite = 'camion_sortant' AND ft.id_entite = cs.idSortie
              LEFT JOIN camions_entrants cs_entrants ON cs.idEntree = cs_entrants.idEntree
              LEFT JOIN bateau_entrant be ON ft.type_entite = 'bateau_entrant' AND ft.id_entite = be.id
              LEFT JOIN bateau_sortant bs ON ft.type_entite = 'bateau_sortant' AND ft.id_entite = bs.id
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $query .= " AND (";
        $query .= " CASE 
                        WHEN ft.type_entite = 'camion_entrant' THEN ce.immatriculation
                        WHEN ft.type_entite = 'camion_sortant' THEN cs_entrants.immatriculation
                        WHEN ft.type_entite = 'bateau_entrant' THEN be.nom_navire
                        WHEN ft.type_entite = 'bateau_sortant' THEN bs.nom_navire
                    END LIKE ?
                    OR
                    CASE 
                        WHEN ft.type_entite = 'camion_entrant' THEN CONCAT(ce.nom_chauffeur, ' ', ce.prenom_chauffeur)
                        WHEN ft.type_entite = 'camion_sortant' THEN CONCAT(cs_entrants.nom_chauffeur, ' ', cs_entrants.prenom_chauffeur)
                        WHEN ft.type_entite = 'bateau_entrant' THEN CONCAT(be.nom_capitaine, ' ', be.prenom_capitaine)
                        WHEN ft.type_entite = 'bateau_sortant' THEN CONCAT(bs.nom_capitaine, ' ', bs.prenom_capitaine)
                    END LIKE ?
                    OR ft.commentaire LIKE ?
                )";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sss";
    }
    
    if (!empty($type_entite)) {
        $query .= " AND ft.type_entite = ?";
        $params[] = $type_entite;
        $types .= "s";
    }
    
    if (!empty($date_debut)) {
        $query .= " AND DATE(ft.date_ajout) >= ?";
        $params[] = $date_debut;
        $types .= "s";
    }
    
    if (!empty($date_fin)) {
        $query .= " AND DATE(ft.date_ajout) <= ?";
        $params[] = $date_fin;
        $types .= "s";
    }
    
    if (!empty($montant_min) && is_numeric($montant_min)) {
        $query .= " AND (COALESCE(ft.frais_thc, 0) + COALESCE(ft.frais_magasinage, 0) + 
                      COALESCE(ft.droits_douane, 0) + COALESCE(ft.surestaries, 0)) >= ?";
        $params[] = $montant_min;
        $types .= "d";
    }
    
    if (!empty($montant_max) && is_numeric($montant_max)) {
        $query .= " AND (COALESCE(ft.frais_thc, 0) + COALESCE(ft.frais_magasinage, 0) + 
                      COALESCE(ft.droits_douane, 0) + COALESCE(ft.surestaries, 0)) <= ?";
        $params[] = $montant_max;
        $types .= "d";
    }
    
    $query .= " ORDER BY ft.date_ajout DESC";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $frais_list = $result->fetch_all(MYSQLI_ASSOC);
    
    // Calculer les statistiques
    if (!empty($frais_list)) {
        $stats['total'] = count($frais_list);
        foreach ($frais_list as $frais) {
            $stats['total_thc'] += $frais['frais_thc'] ?? 0;
            $stats['total_magasinage'] += $frais['frais_magasinage'] ?? 0;
            $stats['total_douane'] += $frais['droits_douane'] ?? 0;
            $stats['total_surestaries'] += $frais['surestaries'] ?? 0;
            $stats['total_general'] += $frais['total'] ?? 0;
        }
    }
    
    // Récupérer les totaux par type pour le graphique
    $stats_query = "SELECT 
                    type_entite,
                    COUNT(*) as count,
                    SUM(COALESCE(frais_thc, 0)) as total_thc,
                    SUM(COALESCE(frais_magasinage, 0)) as total_magasinage,
                    SUM(COALESCE(droits_douane, 0)) as total_douane,
                    SUM(COALESCE(surestaries, 0)) as total_surestaries
                    FROM frais_transit
                    GROUP BY type_entite";
    $stats_result = $conn->query($stats_query);
    $stats_by_type = $stats_result->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Frais de Transit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .table-container {
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .sticky-header {
            position: sticky;
            top: 0;
            background-color: #ffffff;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .data-row {
            transition: all 0.2s ease;
        }
        
        .data-row:hover {
            background-color: #f8fafc;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .type-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .frais-item {
            border-left: 4px solid;
            padding-left: 1rem;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 animate-fade-in">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo safe_html($error); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Tableau des frais -->
        <div class="bg-white rounded-xl shadow mb-8 animate-fade-in">
            <div class="p-6 border-b">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-list text-purple-600 mr-2"></i>Liste des Frais
                    </h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600">
                            <?php echo $stats['total']; ?> enregistrement(s)
                        </span>
                        
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <table class="min-w-full divide-y divide-gray-200 whitespace-nowrap">
                    <thead class="sticky-header bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entité</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chauffeur/Capitaine</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frais THC</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Magasinage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Douane</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Surestaries</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($frais_list)): ?>
                            <?php foreach ($frais_list as $frais): ?>
                                <?php
                                // Déterminer la couleur en fonction du type
                                $type_color = '';
                                $type_icon = '';
                                $type_label = '';
                                
                                switch ($frais['type_entite']) {
                                    case 'camion_entrant':
                                        $type_color = 'bg-blue-100 text-blue-800';
                                        $type_icon = 'truck';
                                        $type_label = 'Camion Entrant';
                                        break;
                                    case 'camion_sortant':
                                        $type_color = 'bg-green-100 text-green-800';
                                        $type_icon = 'truck-loading';
                                        $type_label = 'Camion Sortant';
                                        break;
                                    case 'bateau_entrant':
                                        $type_color = 'bg-indigo-100 text-indigo-800';
                                        $type_icon = 'ship';
                                        $type_label = 'Bateau Entrant';
                                        break;
                                    case 'bateau_sortant':
                                        $type_color = 'bg-purple-100 text-purple-800';
                                        $type_icon = 'anchor';
                                        $type_label = 'Bateau Sortant';
                                        break;
                                }
                                
                                $total = ($frais['frais_thc'] ?? 0) + ($frais['frais_magasinage'] ?? 0) + 
                                         ($frais['droits_douane'] ?? 0) + ($frais['surestaries'] ?? 0);
                                ?>
                                <tr class="data-row">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-purple-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-<?php echo $type_icon; ?> text-purple-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-bold text-gray-900">
                                                    <?php echo safe_html($frais['entite_nom'] ?? 'N/A'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="type-badge <?php echo $type_color; ?>">
                                            <i class="fas fa-<?php echo $type_icon; ?> mr-1"></i>
                                            <?php echo $type_label; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo safe_html($frais['personne'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo $frais['date_operation'] ? date('d/m/Y', strtotime($frais['date_operation'])) : 'N/A'; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo number_format($frais['frais_thc'] ?? 0, 2, ',', ' '); ?> F CFA
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo number_format($frais['frais_magasinage'] ?? 0, 2, ',', ' '); ?> F CFA
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo number_format($frais['droits_douane'] ?? 0, 2, ',', ' '); ?> F CFA
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo number_format($frais['surestaries'] ?? 0, 2, ',', ' '); ?> F CFA
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-lg font-bold text-gray-900">
                                            <?php echo number_format($total, 2, ',', ' '); ?> F CFA
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo date('d/m/Y', strtotime($frais['date_ajout'])); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo date('H:i', strtotime($frais['date_ajout'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex space-x-2">
                                            <button onclick="openFraisDetails(<?php echo $frais['id']; ?>)" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-sm font-medium transition duration-200">
                                                <i class="fas fa-eye mr-1.5"></i>Détails
                                            </button>
                                            
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="px-6 py-12 text-center">
                                    <div class="text-gray-400">
                                        <i class="fas fa-file-invoice-dollar text-4xl mb-3"></i>
                                        <p class="text-lg font-medium text-gray-500">Aucun frais trouvé</p>
                                        <p class="text-sm text-gray-400 mt-1">Commencez par ajouter des frais depuis la page d'enregistrement</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal pour afficher les détails -->
    <div id="detailsModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-4/5 md:w-2/5 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-800">Détails des Frais</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="modalContent" class="space-y-4">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
        </div>
    </div>
    
    <script>
        // Initialiser le graphique
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('fraisChart').getContext('2d');
            
            // Préparer les données pour le graphique
            const types = ['camion_entrant', 'camion_sortant', 'bateau_entrant', 'bateau_sortant'];
            const labels = ['Camions Entrants', 'Camions Sortants', 'Bateaux Entrants', 'Bateaux Sortants'];
            const colors = ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b'];
            
            const dataByType = <?php echo json_encode($stats_by_type); ?>;
            
            const datasets = [];
            const metrics = ['total_thc', 'total_magasinage', 'total_douane', 'total_surestaries'];
            const metricLabels = ['THC', 'Magasinage', 'Douane', 'Surestaries'];
            const metricColors = ['#60a5fa', '#34d399', '#a78bfa', '#fbbf24'];
            
            metrics.forEach((metric, index) => {
                const data = types.map(type => {
                    const item = dataByType.find(item => item.type_entite === type);
                    return item ? parseFloat(item[metric]) : 0;
                });
                
                datasets.push({
                    label: metricLabels[index],
                    data: data,
                    backgroundColor: metricColors[index],
                    borderColor: metricColors[index],
                    borderWidth: 1
                });
            });
            
            const fraisChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' F CFA';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += context.parsed.y.toFixed(2) + ' F CFA';
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
            
            // Initialiser les dates par défaut
            const dateDebut = document.getElementById('date_debut');
            const dateFin = document.getElementById('date_fin');
            
            if (!dateDebut.value) {
                const oneMonthAgo = new Date();
                oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
                dateDebut.valueAsDate = oneMonthAgo;
            }
            
            if (!dateFin.value) {
                dateFin.valueAsDate = new Date();
            }
            
            // Valider les dates
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                if (dateDebut.value && dateFin.value && dateDebut.value > dateFin.value) {
                    e.preventDefault();
                    alert('La date de début doit être antérieure à la date de fin');
                    dateDebut.focus();
                }
                
                const montantMin = document.getElementById('montant_min').value;
                const montantMax = document.getElementById('montant_max').value;
                
                if (montantMin && montantMax && parseFloat(montantMin) > parseFloat(montantMax)) {
                    e.preventDefault();
                    alert('Le montant minimum doit être inférieur au montant maximum');
                    document.getElementById('montant_min').focus();
                }
            });
        });
        
        // Fonction pour ouvrir les détails des frais
        // Fonction pour ouvrir les détails des frais
async function openFraisDetails(id) {
    try {
        // Afficher le loader
        document.getElementById('modalContent').innerHTML = `
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600 mx-auto"></div>
                <p class="mt-4 text-gray-600">Chargement des détails...</p>
            </div>
        `;
        
        // Afficher la modale
        document.getElementById('detailsModal').classList.remove('hidden');
        
        // Récupérer les détails via AJAX
        const response = await fetch(`get_frais_details.php?id=${id}`);
        
        // Vérifier si la réponse est OK
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        // Vérifier si le JSON est valide
        if (!data) {
            throw new Error('Réponse vide du serveur');
        }
        
        if (data.success) {
            const frais = data.frais;
            const frais_thc = parseFloat(frais.frais_thc) || 0;
            const frais_magasinage = parseFloat(frais.frais_magasinage) || 0;
            const droits_douane = parseFloat(frais.droits_douane) || 0;
            const surestaries = parseFloat(frais.surestaries) || 0;
            const total = frais_thc + frais_magasinage + droits_douane + surestaries;
            
            // Déterminer le type
            let typeLabel = '';
            let typeIcon = '';
            let typeColor = '';
            
            switch (frais.type_entite) {
                case 'camion_entrant':
                    typeLabel = 'Camion Entrant';
                    typeIcon = 'truck';
                    typeColor = 'text-blue-600';
                    break;
                case 'camion_sortant':
                    typeLabel = 'Camion Sortant';
                    typeIcon = 'truck-loading';
                    typeColor = 'text-green-600';
                    break;
                case 'bateau_entrant':
                    typeLabel = 'Bateau Entrant';
                    typeIcon = 'ship';
                    typeColor = 'text-indigo-600';
                    break;
                case 'bateau_sortant':
                    typeLabel = 'Bateau Sortant';
                    typeIcon = 'anchor';
                    typeColor = 'text-purple-600';
                    break;
            }
            
            const content = `
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="h-12 w-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-${typeIcon} ${typeColor} text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800">${frais.entite_nom || 'N/A'}</h4>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600">Total</p>
                            <p class="text-2xl font-bold text-gray-800">${total.toFixed(2)} F CFA</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Frais THC</p>
                            <p class="text-xl font-bold text-gray-800">${frais_thc.toFixed(2)} F CFA</p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Magasinage</p>
                            <p class="text-xl font-bold text-gray-800">${frais_magasinage.toFixed(2)} F CFA</p>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Droits de Douane</p>
                            <p class="text-xl font-bold text-gray-800">${droits_douane.toFixed(2)} F CFA</p>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Surestaries</p>
                            <p class="text-xl font-bold text-gray-800">${surestaries.toFixed(2)} F CFA</p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm font-medium text-gray-700 mb-2">Informations Chauffeur/Capitaine</p>
                        <p class="text-gray-800">${frais.personne || 'N/A'}</p>
                        <p class="text-sm text-gray-600 mt-1">Date opération: ${frais.date_operation ? new Date(frais.date_operation).toLocaleDateString('fr-FR') : 'N/A'}</p>
                    </div>
                    
                    ${frais.commentaire ? `
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm font-medium text-gray-700 mb-2">Commentaire</p>
                            <p class="text-gray-800">${frais.commentaire}</p>
                        </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('modalContent').innerHTML = content;
        } else {
            throw new Error(data.error || 'Erreur inconnue');
        }
        
    } catch (error) {
        console.error('Erreur:', error);
        document.getElementById('modalContent').innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-exclamation-triangle text-3xl text-red-500 mb-3"></i>
                <p class="text-lg font-medium text-gray-700">Erreur de chargement</p>
                <p class="text-sm text-gray-500 mt-2">${error.message}</p>
                <p class="text-xs text-gray-400 mt-1">Vérifiez la console pour plus de détails</p>
            </div>
        `;
    }
}
        
        // Fonction pour fermer la modale
        function closeModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }
        
        
        
        
        // Fermer la modale avec la touche Échap
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
        
        // Fermer la modale en cliquant en dehors
        document.getElementById('detailsModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeModal();
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