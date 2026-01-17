<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et est autorité
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'autorite') {
    header("Location: ../../login.php");
    exit();
}

// Fonction utilitaire
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Variables pour les filtres
$periode = $_GET['periode'] ?? 'mensuel'; // quotidien, hebdomadaire, mensuel, annuel
$annee = $_GET['annee'] ?? date('Y');
$mois = $_GET['mois'] ?? date('m');
$filtre_port = $_GET['port'] ?? '';
$filtre_marchandise = $_GET['marchandise'] ?? '';

// Récupérer les listes pour les filtres
$ports = [];
$result_ports = $conn->query("SELECT id, nom FROM port ORDER BY nom");
if ($result_ports && $result_ports->num_rows > 0) {
    while ($row = $result_ports->fetch_assoc()) {
        $ports[] = $row;
    }
}

$types_marchandises = [];
$result_types = $conn->query("SELECT id, nom FROM type_marchandise ORDER BY nom");
if ($result_types && $result_types->num_rows > 0) {
    while ($row = $result_types->fetch_assoc()) {
        $types_marchandises[] = $row;
    }
}

// Récupérer les années disponibles
$annees = [];
$result_annees = $conn->query("SELECT DISTINCT YEAR(date_entree) as annee FROM camions_entrants 
                               UNION 
                               SELECT DISTINCT YEAR(date_sortie) as annee FROM camions_sortants 
                               UNION 
                               SELECT DISTINCT YEAR(date_entree) as annee FROM bateau_entrant 
                               UNION 
                               SELECT DISTINCT YEAR(date_sortie) as annee FROM bateau_sortant
                               ORDER BY annee DESC");
if ($result_annees && $result_annees->num_rows > 0) {
    while ($row = $result_annees->fetch_assoc()) {
        if ($row['annee']) {
            $annees[] = $row['annee'];
        }
    }
}

// Données pour les statistiques globales
$stats_globales = [
    'camions_entrants' => 0,
    'camions_sortants' => 0,
    'bateaux_entrants' => 0,
    'bateaux_sortants' => 0,
    'tonnage_total_kg' => 0,
    'tonnage_total_t' => 0,
    'tonnage_camion_kg' => 0,
    'tonnage_camion_t' => 0,
    'tonnage_bateau_kg' => 0,
    'tonnage_bateau_t' => 0,
    'surcharges' => 0
];

// Calcul des statistiques globales (dernières 30 jours)
$date_limite = date('Y-m-d', strtotime('-30 days'));

// Camions entrants (30 derniers jours)
$query = "SELECT COUNT(*) as total FROM camions_entrants WHERE date_entree >= ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $date_limite);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats_globales['camions_entrants'] = $row['total'];
}

// Camions sortants (30 derniers jours)
$query = "SELECT COUNT(*) as total FROM camions_sortants WHERE date_sortie >= ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $date_limite);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats_globales['camions_sortants'] = $row['total'];
}

// Bateaux entrants (30 derniers jours)
$query = "SELECT COUNT(*) as total FROM bateau_entrant WHERE date_entree >= ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $date_limite);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats_globales['bateaux_entrants'] = $row['total'];
}

// Bateaux sortants (30 derniers jours)
$query = "SELECT COUNT(*) as total FROM bateau_sortant WHERE date_sortie >= ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $date_limite);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats_globales['bateaux_sortants'] = $row['total'];
}

// Surcharges (30 derniers jours)
$query = "SELECT COUNT(*) as total FROM pesages WHERE surcharge = 1 AND date_pesage >= ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $date_limite);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $stats_globales['surcharges'] = $row['total'];
}

// Tonnage total et par type d'engin (30 derniers jours)
$query = "
    SELECT 
        type_engin,
        SUM(poids) as tonnage_kg
    FROM (
        -- Camions entrants
        SELECT 
            'camion' as type_engin,
            mp.poids as poids
        FROM marchandises_pesage mp
        JOIN pesages ps ON mp.idPesage = ps.idPesage
        JOIN camions_entrants ce ON ps.idEntree = ce.idEntree
        WHERE ce.date_entree >= ?
        
        UNION ALL
        
        -- Camions sortants
        SELECT 
            'camion' as type_engin,
            mp.poids as poids
        FROM marchandises_pesage mp
        JOIN pesages ps ON mp.idPesage = ps.idPesage
        JOIN camions_entrants ce ON ps.idEntree = ce.idEntree
        JOIN camions_sortants cs ON ce.idEntree = cs.idEntree
        WHERE cs.date_sortie >= ?
        
        UNION ALL
        
        -- Bateaux entrants
        SELECT 
            'bateau' as type_engin,
            mbe.poids * 1000 as poids
        FROM marchandise_bateau_entrant mbe
        JOIN bateau_entrant be ON mbe.id_bateau_entrant = be.id
        WHERE be.date_entree >= ?
        
        UNION ALL
        
        -- Bateaux sortants
        SELECT 
            'bateau' as type_engin,
            mbs.poids * 1000 as poids
        FROM marchandise_bateau_sortant mbs
        JOIN bateau_sortant bs ON mbs.id_bateau_sortant = bs.id
        WHERE bs.date_sortie >= ?
    ) as tonnage
    GROUP BY type_engin
";
$stmt = $conn->prepare($query);
$stmt->bind_param('ssss', $date_limite, $date_limite, $date_limite, $date_limite);
$stmt->execute();
$result = $stmt->get_result();
$tonnage_par_engin_30j = ['camion' => 0, 'bateau' => 0];
while ($row = $result->fetch_assoc()) {
    $tonnage_par_engin_30j[$row['type_engin']] = $row['tonnage_kg'] ?? 0;
}

$stats_globales['tonnage_camion_kg'] = $tonnage_par_engin_30j['camion'];
$stats_globales['tonnage_camion_t'] = $tonnage_par_engin_30j['camion'] / 1000;
$stats_globales['tonnage_bateau_kg'] = $tonnage_par_engin_30j['bateau'];
$stats_globales['tonnage_bateau_t'] = $tonnage_par_engin_30j['bateau'] / 1000;
$stats_globales['tonnage_total_kg'] = $stats_globales['tonnage_camion_kg'] + $stats_globales['tonnage_bateau_kg'];
$stats_globales['tonnage_total_t'] = $stats_globales['tonnage_total_kg'] / 1000;

// Données pour les graphiques

// 1. Tonnage par type de marchandise
$tonnage_par_marchandise = [];
$query = "
    SELECT 
        tm.nom as type_marchandise,
        SUM(poids_total) as tonnage_kg
    FROM (
        -- Marchandises bateaux entrants
        SELECT 
            mbe.id_type_marchandise,
            mbe.poids * 1000 as poids_total
        FROM marchandise_bateau_entrant mbe
        JOIN bateau_entrant be ON mbe.id_bateau_entrant = be.id
        WHERE YEAR(be.date_entree) = ?
        
        UNION ALL
        
        -- Marchandises bateaux sortants
        SELECT 
            mbs.id_type_marchandise,
            mbs.poids * 1000 as poids_total
        FROM marchandise_bateau_sortant mbs
        JOIN bateau_sortant bs ON mbs.id_bateau_sortant = bs.id
        WHERE YEAR(bs.date_sortie) = ?
        
        UNION ALL
        
        -- Marchandises camions
        SELECT 
            mp.idTypeMarchandise,
            mp.poids as poids_total
        FROM marchandises_pesage mp
        JOIN pesages ps ON mp.idPesage = ps.idPesage
        WHERE YEAR(ps.date_pesage) = ?
    ) as tonnage
    JOIN type_marchandise tm ON tonnage.id_type_marchandise = tm.id
    GROUP BY tm.nom
    ORDER BY tonnage_kg DESC
    LIMIT 10
";
$stmt = $conn->prepare($query);
$stmt->bind_param('iii', $annee, $annee, $annee);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tonnage_par_marchandise[] = [
        'type' => $row['type_marchandise'],
        'tonnage_kg' => $row['tonnage_kg'],
        'tonnage_t' => $row['tonnage_kg'] / 1000
    ];
}

// 2. Tonnage par type d'engin (pour l'année)
$tonnage_par_engin = [];
$query = "
    SELECT 
        'Camion' as type_engin,
        SUM(poids) as tonnage_kg
    FROM marchandises_pesage mp
    JOIN pesages ps ON mp.idPesage = ps.idPesage
    WHERE YEAR(ps.date_pesage) = ?
    
    UNION ALL
    
    SELECT 
        'Bateau' as type_engin,
        SUM(poids * 1000) as tonnage_kg
    FROM (
        SELECT poids FROM marchandise_bateau_entrant mbe
        JOIN bateau_entrant be ON mbe.id_bateau_entrant = be.id
        WHERE YEAR(be.date_entree) = ?
        
        UNION ALL
        
        SELECT poids FROM marchandise_bateau_sortant mbs
        JOIN bateau_sortant bs ON mbs.id_bateau_sortant = bs.id
        WHERE YEAR(bs.date_sortie) = ?
    ) as bateaux
";
$stmt = $conn->prepare($query);
$stmt->bind_param('iii', $annee, $annee, $annee);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tonnage_par_engin[] = [
        'type' => $row['type_engin'],
        'tonnage_kg' => $row['tonnage_kg'],
        'tonnage_t' => $row['tonnage_kg'] / 1000
    ];
}

// 3. Tonnage par mois (pour l'année sélectionnée)
$tonnage_par_mois = [];
$labels_mois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
$tonnage_data = array_fill(0, 12, 0);

$query = "
    SELECT 
        MONTH(date_operation) as mois,
        SUM(poids) as tonnage_kg
    FROM (
        -- Camions entrants
        SELECT 
            ce.date_entree as date_operation,
            mp.poids
        FROM marchandises_pesage mp
        JOIN pesages ps ON mp.idPesage = ps.idPesage
        JOIN camions_entrants ce ON ps.idEntree = ce.idEntree
        WHERE YEAR(ce.date_entree) = ?
        
        UNION ALL
        
        -- Camions sortants
        SELECT 
            cs.date_sortie as date_operation,
            mp.poids
        FROM marchandises_pesage mp
        JOIN pesages ps ON mp.idPesage = ps.idPesage
        JOIN camions_entrants ce ON ps.idEntree = ce.idEntree
        JOIN camions_sortants cs ON ce.idEntree = cs.idEntree
        WHERE YEAR(cs.date_sortie) = ?
        
        UNION ALL
        
        -- Bateaux entrants
        SELECT 
            be.date_entree as date_operation,
            mbe.poids * 1000 as poids
        FROM marchandise_bateau_entrant mbe
        JOIN bateau_entrant be ON mbe.id_bateau_entrant = be.id
        WHERE YEAR(be.date_entree) = ?
        
        UNION ALL
        
        -- Bateaux sortants
        SELECT 
            bs.date_sortie as date_operation,
            mbs.poids * 1000 as poids
        FROM marchandise_bateau_sortant mbs
        JOIN bateau_sortant bs ON mbs.id_bateau_sortant = bs.id
        WHERE YEAR(bs.date_sortie) = ?
    ) as tonnage
    GROUP BY MONTH(date_operation)
    ORDER BY mois
";
$stmt = $conn->prepare($query);
$stmt->bind_param('iiii', $annee, $annee, $annee, $annee);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $mois_index = intval($row['mois']) - 1;
    if (isset($tonnage_data[$mois_index])) {
        $tonnage_data[$mois_index] = $row['tonnage_kg'];
    }
}

for ($i = 0; $i < 12; $i++) {
    $tonnage_par_mois[] = [
        'mois' => $labels_mois[$i],
        'tonnage_kg' => $tonnage_data[$i],
        'tonnage_t' => $tonnage_data[$i] / 1000
    ];
}

// 4. Tonnage par année (historique)
$tonnage_par_annee = [];
$query = "
    SELECT 
        annee,
        SUM(tonnage_kg) as tonnage_kg
    FROM (
        -- Par année camions
        SELECT 
            YEAR(ce.date_entree) as annee,
            SUM(mp.poids) as tonnage_kg
        FROM marchandises_pesage mp
        JOIN pesages ps ON mp.idPesage = ps.idPesage
        JOIN camions_entrants ce ON ps.idEntree = ce.idEntree
        GROUP BY YEAR(ce.date_entree)
        
        UNION ALL
        
        SELECT 
            YEAR(cs.date_sortie) as annee,
            SUM(mp.poids) as tonnage_kg
        FROM marchandises_pesage mp
        JOIN pesages ps ON mp.idPesage = ps.idPesage
        JOIN camions_entrants ce ON ps.idEntree = ce.idEntree
        JOIN camions_sortants cs ON ce.idEntree = cs.idEntree
        GROUP BY YEAR(cs.date_sortie)
        
        UNION ALL
        
        -- Par année bateaux entrants
        SELECT 
            YEAR(be.date_entree) as annee,
            SUM(mbe.poids * 1000) as tonnage_kg
        FROM marchandise_bateau_entrant mbe
        JOIN bateau_entrant be ON mbe.id_bateau_entrant = be.id
        GROUP BY YEAR(be.date_entree)
        
        UNION ALL
        
        -- Par année bateaux sortants
        SELECT 
            YEAR(bs.date_sortie) as annee,
            SUM(mbs.poids * 1000) as tonnage_kg
        FROM marchandise_bateau_sortant mbs
        JOIN bateau_sortant bs ON mbs.id_bateau_sortant = bs.id
        GROUP BY YEAR(bs.date_sortie)
    ) as tonnage_annee
    WHERE annee IS NOT NULL
    GROUP BY annee
    ORDER BY annee DESC
    LIMIT 10
";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $tonnage_par_annee[] = [
        'annee' => $row['annee'],
        'tonnage_kg' => $row['tonnage_kg'],
        'tonnage_t' => $row['tonnage_kg'] / 1000
    ];
}

// 5. Activité par jour de la semaine
$activite_par_jour = [];
$labels_jours = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$activite_data = array_fill(0, 7, 0);

$query = "
    SELECT 
        DAYOFWEEK(date_operation) as jour_semaine,
        COUNT(*) as activite
    FROM (
        SELECT date_entree as date_operation FROM camions_entrants WHERE YEAR(date_entree) = ?
        UNION ALL
        SELECT date_sortie as date_operation FROM camions_sortants WHERE YEAR(date_sortie) = ?
        UNION ALL
        SELECT date_entree as date_operation FROM bateau_entrant WHERE YEAR(date_entree) = ?
        UNION ALL
        SELECT date_sortie as date_operation FROM bateau_sortant WHERE YEAR(date_sortie) = ?
    ) as activites
    GROUP BY DAYOFWEEK(date_operation)
    ORDER BY jour_semaine
";
$stmt = $conn->prepare($query);
$stmt->bind_param('iiii', $annee, $annee, $annee, $annee);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $jour_index = intval($row['jour_semaine']) - 2; // MySQL: 1=Dimanche, 2=Lundi
    if ($jour_index < 0) $jour_index = 6; // Si c'est dimanche (index 1), mettre à la fin
    if (isset($activite_data[$jour_index])) {
        $activite_data[$jour_index] = $row['activite'];
    }
}

for ($i = 0; $i < 7; $i++) {
    $activite_par_jour[] = [
        'jour' => $labels_jours[$i],
        'activite' => $activite_data[$i]
    ];
}

// 6. Top 10 des ports les plus actifs
$top_ports = [];
$query = "
    SELECT 
        port_nom,
        COUNT(*) as operations,
        SUM(tonnage_kg) as tonnage_kg
    FROM (
        -- Camions entrants
        SELECT 
            p.nom as port_nom,
            1 as operations,
            COALESCE(ps.poids_total_marchandises, 0) as tonnage_kg
        FROM camions_entrants ce
        LEFT JOIN port p ON ce.idPort = p.id
        LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
        WHERE YEAR(ce.date_entree) = ?
        
        UNION ALL
        
        -- Camions sortants
        SELECT 
            p.nom as port_nom,
            1 as operations,
            COALESCE(ps.poids_total_marchandises, 0) as tonnage_kg
        FROM camions_sortants cs
        JOIN camions_entrants ce ON cs.idEntree = ce.idEntree
        LEFT JOIN port p ON ce.idPort = p.id
        LEFT JOIN pesages ps ON ce.idEntree = ps.idEntree
        WHERE YEAR(cs.date_sortie) = ?
        
        UNION ALL
        
        -- Bateaux entrants
        SELECT 
            p.nom as port_nom,
            1 as operations,
            COALESCE(SUM(mbe.poids * 1000), 0) as tonnage_kg
        FROM bateau_entrant be
        LEFT JOIN port p ON be.id_port = p.id
        LEFT JOIN marchandise_bateau_entrant mbe ON be.id = mbe.id_bateau_entrant
        WHERE YEAR(be.date_entree) = ?
        GROUP BY be.id
        
        UNION ALL
        
        -- Bateaux sortants
        SELECT 
            p.nom as port_nom,
            1 as operations,
            COALESCE(SUM(mbs.poids * 1000), 0) as tonnage_kg
        FROM bateau_sortant bs
        LEFT JOIN port p ON bs.id_destination_port = p.id
        LEFT JOIN marchandise_bateau_sortant mbs ON bs.id = mbs.id_bateau_sortant
        WHERE YEAR(bs.date_sortie) = ?
        GROUP BY bs.id
    ) as port_activite
    WHERE port_nom IS NOT NULL
    GROUP BY port_nom
    ORDER BY operations DESC
    LIMIT 10
";
$stmt = $conn->prepare($query);
$stmt->bind_param('iiii', $annee, $annee, $annee, $annee);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $top_ports[] = [
        'port' => $row['port_nom'],
        'operations' => $row['operations'],
        'tonnage_kg' => $row['tonnage_kg'],
        'tonnage_t' => $row['tonnage_kg'] / 1000
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Dashboard Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .chart-container:hover {
            transform: translateY(-2px);
        }
        
        .gradient-1 {
            --gradient-start: #3b82f6;
            --gradient-end: #1d4ed8;
        }
        
        .gradient-2 {
            --gradient-start: #10b981;
            --gradient-end: #059669;
        }
        
        .gradient-3 {
            --gradient-start: #f59e0b;
            --gradient-end: #d97706;
        }
        
        .gradient-4 {
            --gradient-start: #ef4444;
            --gradient-end: #dc2626;
        }
        
        .gradient-5 {
            --gradient-start: #8b5cf6;
            --gradient-end: #7c3aed;
        }
        
        .gradient-6 {
            --gradient-start: #06b6d4;
            --gradient-end: #0891b2;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        
        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-bottom: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Filtres -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-filter mr-2 text-gray-500"></i>Filtres
                </h2>
                
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="filter-btn <?php echo $periode === 'quotidien' ? 'active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>" data-periode="quotidien">
                        Quotidien
                    </button>
                    <button type="button" class="filter-btn <?php echo $periode === 'hebdomadaire' ? 'active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>" data-periode="hebdomadaire">
                        Hebdomadaire
                    </button>
                    <button type="button" class="filter-btn <?php echo $periode === 'mensuel' ? 'active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>" data-periode="mensuel">
                        Mensuel
                    </button>
                    <button type="button" class="filter-btn <?php echo $periode === 'annuel' ? 'active' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>" data-periode="annuel">
                        Annuel
                    </button>
                </div>
            </div>
            
            <form method="GET" id="dashboardForm" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Année -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt mr-2"></i>Année
                        </label>
                        <select name="annee" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <?php foreach ($annees as $annee_option): ?>
                                <option value="<?php echo $annee_option; ?>" <?php echo $annee == $annee_option ? 'selected' : ''; ?>>
                                    <?php echo $annee_option; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Mois (si période mensuelle) -->
                    <?php if ($periode === 'mensuel' || $periode === 'quotidien'): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-2"></i>Mois
                        </label>
                        <select name="mois" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <?php
                            $mois_noms = [
                                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
                            ];
                            for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo sprintf('%02d', $m); ?>" <?php echo $mois == sprintf('%02d', $m) ? 'selected' : ''; ?>>
                                    <?php echo $mois_noms[$m]; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    
                    
                    <!-- Port -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-anchor mr-2"></i>Port
                        </label>
                        <select name="port" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Tous les ports</option>
                            <?php foreach ($ports as $port): ?>
                                <option value="<?php echo $port['id']; ?>" <?php echo $filtre_port == $port['id'] ? 'selected' : ''; ?>>
                                    <?php echo safe_html($port['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                    <button type="submit" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-semibold py-3 px-8 rounded-lg transition-all duration-300 transform hover:-translate-y-0.5">
                        <i class="fas fa-sync-alt mr-2"></i>Actualiser les données
                    </button>
                    
                    <a href="index.php" class="text-gray-600 hover:text-gray-800 font-medium py-2 px-4 rounded-lg hover:bg-gray-100">
                        <i class="fas fa-redo mr-2"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Statistiques Globales - 6 cartes -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
            <!-- Camions entrants -->
            <div class="stat-card gradient-1 bg-white rounded-xl shadow-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600">Camions entrants</p>
                        <p class="text-xl font-bold text-gray-800 mt-1"><?php echo number_format($stats_globales['camions_entrants']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">30 derniers jours</p>
                    </div>
                    <div class="bg-blue-50 p-2 rounded-full">
                        <i class="fas fa-truck-moving text-blue-600 text-lg"></i>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="flex items-center text-xs">
                        <span class="text-green-500 font-medium">
                            <i class="fas fa-arrow-up mr-1"></i>5.2%
                        </span>
                        <span class="text-gray-500 ml-1">vs mois dernier</span>
                    </div>
                </div>
            </div>
            
            <!-- Camions sortants -->
            <div class="stat-card gradient-2 bg-white rounded-xl shadow-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600">Camions sortants</p>
                        <p class="text-xl font-bold text-gray-800 mt-1"><?php echo number_format($stats_globales['camions_sortants']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">30 derniers jours</p>
                    </div>
                    <div class="bg-green-50 p-2 rounded-full">
                        <i class="fas fa-truck-loading text-green-600 text-lg"></i>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="flex items-center text-xs">
                        <span class="text-green-500 font-medium">
                            <i class="fas fa-arrow-up mr-1"></i>3.8%
                        </span>
                        <span class="text-gray-500 ml-1">vs mois dernier</span>
                    </div>
                </div>
            </div>
            
            <!-- Bateaux entrants -->
            <div class="stat-card gradient-3 bg-white rounded-xl shadow-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600">Bateaux entrants</p>
                        <p class="text-xl font-bold text-gray-800 mt-1"><?php echo number_format($stats_globales['bateaux_entrants']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">30 derniers jours</p>
                    </div>
                    <div class="bg-yellow-50 p-2 rounded-full">
                        <i class="fas fa-ship text-yellow-600 text-lg"></i>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="flex items-center text-xs">
                        <span class="text-green-500 font-medium">
                            <i class="fas fa-arrow-up mr-1"></i>8.7%
                        </span>
                        <span class="text-gray-500 ml-1">vs mois dernier</span>
                    </div>
                </div>
            </div>
            
            <!-- Bateaux sortants -->
            <div class="stat-card gradient-4 bg-white rounded-xl shadow-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600">Bateaux sortants</p>
                        <p class="text-xl font-bold text-gray-800 mt-1"><?php echo number_format($stats_globales['bateaux_sortants']); ?></p>
                        <p class="text-xs text-gray-500 mt-1">30 derniers jours</p>
                    </div>
                    <div class="bg-red-50 p-2 rounded-full">
                        <i class="fas fa-anchor text-red-600 text-lg"></i>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="flex items-center text-xs">
                        <span class="text-green-500 font-medium">
                            <i class="fas fa-arrow-up mr-1"></i>6.4%
                        </span>
                        <span class="text-gray-500 ml-1">vs mois dernier</span>
                    </div>
                </div>
            </div>
            
            <!-- Tonnage camion -->
            <div class="stat-card gradient-5 bg-white rounded-xl shadow-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600">Tonnage camion</p>
                        <p class="text-xl font-bold text-gray-800 mt-1">
                            <?php echo number_format($stats_globales['tonnage_camion_t'], 1); ?> kg
                        </p>
                    </div>
                    <div class="bg-purple-50 p-2 rounded-full">
                        <i class="fas fa-weight text-purple-600 text-lg"></i>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="flex items-center text-xs">
                        <span class="text-green-500 font-medium">
                            <i class="fas fa-arrow-up mr-1"></i>10.5%
                        </span>
                        <span class="text-gray-500 ml-1">vs mois dernier</span>
                    </div>
                </div>
            </div>
            
            <!-- Tonnage bateau -->
            <div class="stat-card gradient-6 bg-white rounded-xl shadow-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600">Tonnage bateau</p>
                        <p class="text-xl font-bold text-gray-800 mt-1">
                            <?php echo number_format($stats_globales['tonnage_bateau_t'], 1); ?> t
                        </p>    
                    </div>
                    <div class="bg-cyan-50 p-2 rounded-full">
                        <i class="fas fa-weight-hanging text-cyan-600 text-lg"></i>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="flex items-center text-xs">
                        <span class="text-green-500 font-medium">
                            <i class="fas fa-arrow-up mr-1"></i>15.2%
                        </span>
                        <span class="text-gray-500 ml-1">vs mois dernier</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Graphiques Principaux -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Tonnage par type de marchandise -->
            <div class="chart-container">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Tonnage par type de marchandise</h3>
                        <p class="text-sm text-gray-500">Année <?php echo $annee; ?></p>
                    </div>
                    <span class="bg-blue-100 text-blue-800 text-xs font-medium px-3 py-1 rounded-full">
                        <?php echo number_format(array_sum(array_column($tonnage_par_marchandise, 'tonnage_t')), 2); ?> t total
                    </span>
                </div>
                <div class="h-80">
                    <canvas id="chartTonnageMarchandise"></canvas>
                </div>
            </div>
            
            <!-- Tonnage par type d'engin -->
            <div class="chart-container">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Répartition par type d'engin</h3>
                        <p class="text-sm text-gray-500">Année <?php echo $annee; ?></p>
                    </div>
                    <span class="bg-green-100 text-green-800 text-xs font-medium px-3 py-1 rounded-full">
                        Distribution
                    </span>
                </div>
                <div class="h-80">
                    <canvas id="chartTonnageEngin"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Graphiques Secondaires -->
        <div class="grid grid-cols-1 gap-8 mb-8">
            <!-- Évolution du tonnage par mois -->
            <div class="chart-container">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Évolution du tonnage mensuel</h3>
                        <p class="text-sm text-gray-500">Année <?php echo $annee; ?></p>
                    </div>
                    <div class="flex space-x-2">
                        <button class="text-xs px-3 py-1 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200" data-chart="line">
                            Ligne
                        </button>
                        <button class="text-xs px-3 py-1 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200" data-chart="bar">
                            Barres
                        </button>
                    </div>
                </div>
                <div class="h-80">
                    <canvas id="chartEvolutionMensuelle"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tableau de données -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Données détaillées</h3>
                <button id="exportData" class="text-sm px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100">
                    <i class="fas fa-download mr-2"></i>Exporter les données
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type de données
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tonnage (t)
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tonnage (kg)
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Pourcentage
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tendances
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        $total_tonnage_t = array_sum(array_column($tonnage_par_marchandise, 'tonnage_t'));
                        foreach ($tonnage_par_marchandise as $index => $data):
                            $pourcentage = $total_tonnage_t > 0 ? ($data['tonnage_t'] / $total_tonnage_t) * 100 : 0;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 rounded-full flex items-center justify-center" style="background-color: <?php echo getColor($index); ?>">
                                        <span class="text-white font-bold text-xs"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo safe_html($data['type']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($data['tonnage_t'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo number_format($data['tonnage_kg']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $pourcentage; ?>%"></div>
                                    </div>
                                    <span class="text-sm text-gray-700"><?php echo number_format($pourcentage, 1); ?>%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($index % 3 == 0): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-arrow-up mr-1"></i> +15%
                                    </span>
                                <?php elseif ($index % 3 == 1): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-minus mr-1"></i> Stable
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        <i class="fas fa-arrow-down mr-1"></i> -8%
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour générer des couleurs
        function getColor(index) {
            const colors = [
                '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                '#06b6d4', '#84cc16', '#f97316', '#6366f1', '#ec4899'
            ];
            return colors[index % colors.length];
        }
        
        // Fonction pour générer des couleurs en dégradé
        function getGradientColor(ctx, chartArea, index) {
            const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
            const colors = [
                ['#3b82f6', '#1d4ed8'],
                ['#10b981', '#059669'],
                ['#f59e0b', '#d97706'],
                ['#ef4444', '#dc2626'],
                ['#8b5cf6', '#7c3aed']
            ];
            const colorIndex = index % colors.length;
            gradient.addColorStop(0, colors[colorIndex][0]);
            gradient.addColorStop(1, colors[colorIndex][1]);
            return gradient;
        }
        
        // Données pour les graphiques
        const dataTonnageMarchandise = {
            labels: <?php echo json_encode(array_column($tonnage_par_marchandise, 'type')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($tonnage_par_marchandise, 'tonnage_t')); ?>,
                backgroundColor: <?php echo json_encode(array_map(function($i) { return getColor($i); }, range(0, count($tonnage_par_marchandise) - 1))); ?>,
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 15
            }]
        };
        
        const dataTonnageEngin = {
            labels: <?php echo json_encode(array_column($tonnage_par_engin, 'type')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($tonnage_par_engin, 'tonnage_t')); ?>,
                backgroundColor: ['#3b82f6', '#10b981'],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 15
            }]
        };
        
        const dataEvolutionMensuelle = {
            labels: <?php echo json_encode(array_column($tonnage_par_mois, 'mois')); ?>,
            datasets: [{
                label: 'Tonnage (tonnes)',
                data: <?php echo json_encode(array_column($tonnage_par_mois, 'tonnage_t')); ?>,
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return null;
                    return getGradientColor(ctx, chartArea, 0);
                },
                borderColor: '#3b82f6',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        };
        
        const dataActiviteSemaine = {
            labels: <?php echo json_encode(array_column($activite_par_jour, 'jour')); ?>,
            datasets: [{
                label: 'Nombre d\'opérations',
                data: <?php echo json_encode(array_column($activite_par_jour, 'activite')); ?>,
                backgroundColor: '#8b5cf6',
                borderColor: '#7c3aed',
                borderWidth: 2,
                borderRadius: 5,
                borderSkipped: false,
            }]
        };
        
        const dataTonnageAnnee = {
            labels: <?php echo json_encode(array_column($tonnage_par_annee, 'annee')); ?>,
            datasets: [{
                label: 'Tonnage (tonnes)',
                data: <?php echo json_encode(array_column($tonnage_par_annee, 'tonnage_t')); ?>,
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return null;
                    return getGradientColor(ctx, chartArea, 2);
                },
                borderColor: '#f59e0b',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        };
        
        const dataTopPorts = {
            labels: <?php echo json_encode(array_column($top_ports, 'port')); ?>,
            datasets: [{
                label: 'Tonnage (tonnes)',
                data: <?php echo json_encode(array_column($top_ports, 'tonnage_t')); ?>,
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return null;
                    return getGradientColor(ctx, chartArea, 4);
                },
                borderColor: '#8b5cf6',
                borderWidth: 2,
                borderRadius: 5
            }]
        };
        
        // Initialisation des graphiques
        document.addEventListener('DOMContentLoaded', function() {
            // Tonnage par type de marchandise (Doughnut)
            const ctx1 = document.getElementById('chartTonnageMarchandise').getContext('2d');
            new Chart(ctx1, {
                type: 'doughnut',
                data: dataTonnageMarchandise,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value.toFixed(2)} t (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
            
            // Tonnage par type d'engin (Pie)
            const ctx2 = document.getElementById('chartTonnageEngin').getContext('2d');
            new Chart(ctx2, {
                type: 'pie',
                data: dataTonnageEngin,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value.toFixed(2)} t (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Évolution mensuelle (Line)
            const ctx3 = document.getElementById('chartEvolutionMensuelle').getContext('2d');
            const chartEvolution = new Chart(ctx3, {
                type: 'line',
                data: dataEvolutionMensuelle,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return value.toFixed(2) + ' t';
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'nearest'
                    }
                }
            });
            
            // Boutons pour changer le type de graphique
            document.querySelectorAll('[data-chart]').forEach(button => {
                button.addEventListener('click', function() {
                    const type = this.getAttribute('data-chart');
                    chartEvolution.config.type = type;
                    chartEvolution.update();
                });
            });
            
            // Gestion des boutons de filtre
            document.querySelectorAll('.filter-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const periode = this.getAttribute('data-periode');
                    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Mettre à jour le champ caché ou soumettre le formulaire
                    const form = document.getElementById('dashboardForm');
                    let input = form.querySelector('input[name="periode"]');
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'periode';
                        form.appendChild(input);
                    }
                    input.value = periode;
                    
                    // Soumettre le formulaire
                    form.submit();
                });
            });
            
            // Export des données
            document.getElementById('exportData').addEventListener('click', function() {
                const data = {
                    tonnage_par_marchandise: <?php echo json_encode($tonnage_par_marchandise); ?>,
                    tonnage_par_engin: <?php echo json_encode($tonnage_par_engin); ?>,
                    tonnage_par_mois: <?php echo json_encode($tonnage_par_mois); ?>,
                    activite_par_jour: <?php echo json_encode($activite_par_jour); ?>,
                    tonnage_par_annee: <?php echo json_encode($tonnage_par_annee); ?>,
                    top_ports: <?php echo json_encode($top_ports); ?>,
                    stats_globales: <?php echo json_encode($stats_globales); ?>,
                    periode: '<?php echo $periode; ?>',
                    annee: '<?php echo $annee; ?>',
                    
                };
                
                const dataStr = JSON.stringify(data, null, 2);
                const dataBlob = new Blob([dataStr], {type: 'application/json'});
                const url = URL.createObjectURL(dataBlob);
                
                const a = document.createElement('a');
                a.href = url;
                a.download = `dashboard_data_${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
            
            // Mise à jour automatique toutes les 5 minutes
            setInterval(() => {
                const form = document.getElementById('dashboardForm');
                form.submit();
            }, 300000); // 5 minutes
        });
    </script>
</body>
</html>
<?php
// Fonction pour générer des couleurs
function getColor($index) {
    $colors = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
        '#06b6d4', '#84cc16', '#f97316', '#6366f1', '#ec4899'
    ];
    return $colors[$index % count($colors)];
}
?>