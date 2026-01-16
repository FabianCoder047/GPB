<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'enregistreurEntreeCamion') {
    header("Location: ../../login.php");
    exit();
}

// Fonction pour nettoyer les données
function cleanData($data) {
    return htmlspecialchars($data ?? '-');
}

// Récupérer les filtres depuis l'URL
$filters = [
    'etat' => $_GET['etat'] ?? '',
    'raison' => $_GET['raison'] ?? '',
    'date_debut' => $_GET['date_debut'] ?? '',
    'date_fin' => $_GET['date_fin'] ?? '',
    'immatriculation' => $_GET['immatriculation'] ?? '',
    'chauffeur' => $_GET['chauffeur'] ?? '',
    'port' => $_GET['port'] ?? ''
];

// Construction de la requête SQL avec filtres
$where_conditions = [];
$params = [];
$types = '';

// Filtre par état
if (!empty($filters['etat'])) {
    $where_conditions[] = "ce.etat = ?";
    $params[] = $filters['etat'];
    $types .= 's';
}

// Filtre par raison
if (!empty($filters['raison'])) {
    $where_conditions[] = "ce.raison = ?";
    $params[] = $filters['raison'];
    $types .= 's';
}

// Filtre par période de date
if (!empty($filters['date_debut']) && !empty($filters['date_fin'])) {
    $where_conditions[] = "DATE(ce.date_entree) BETWEEN ? AND ?";
    $params[] = $filters['date_debut'];
    $params[] = $filters['date_fin'];
    $types .= 'ss';
} elseif (!empty($filters['date_debut'])) {
    $where_conditions[] = "DATE(ce.date_entree) >= ?";
    $params[] = $filters['date_debut'];
    $types .= 's';
} elseif (!empty($filters['date_fin'])) {
    $where_conditions[] = "DATE(ce.date_entree) <= ?";
    $params[] = $filters['date_fin'];
    $types .= 's';
}

// Filtre par immatriculation (recherche partielle)
if (!empty($filters['immatriculation'])) {
    $where_conditions[] = "ce.immatriculation LIKE ?";
    $params[] = '%' . $filters['immatriculation'] . '%';
    $types .= 's';
}

// Filtre par chauffeur (recherche partielle)
if (!empty($filters['chauffeur'])) {
    $where_conditions[] = "(ce.nom_chauffeur LIKE ? OR ce.prenom_chauffeur LIKE ?)";
    $params[] = '%' . $filters['chauffeur'] . '%';
    $params[] = '%' . $filters['chauffeur'] . '%';
    $types .= 'ss';
}

// Filtre par port
if (!empty($filters['port']) && $filters['port'] !== 'all') {
    $where_conditions[] = "ce.idPort = ?";
    $params[] = $filters['port'];
    $types .= 'i';
}

// Construction de la clause WHERE
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Récupérer toutes les données (sans pagination pour l'export)
$query = "SELECT ce.*, tc.nom as type_camion, p.nom as port
          FROM camions_entrants ce
          LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
          LEFT JOIN port p ON ce.idPort = p.id
          $where_sql
          ORDER BY ce.date_entree DESC";

try {
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
    die("Erreur lors de la récupération des données: " . $e->getMessage());
}

// Vérifier s'il y a des données
if (empty($camions)) {
    die("Aucune donnée à exporter avec les filtres actuels.");
}

// Créer le spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Titre du document
$sheet->setTitle('Camions Entrants');

// Style pour le titre
$titleStyle = [
    'font' => [
        'bold' => true,
        'size' => 16,
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
];

$sheet->setCellValue('A1', 'Rapport des Camions Entrants');
$sheet->mergeCells('A1:M1');
$sheet->getStyle('A1')->applyFromArray($titleStyle);

// Informations sur les filtres
$row = 3;
$sheet->setCellValue('A' . $row, 'Filtres appliqués:');
$sheet->getStyle('A' . $row)->getFont()->setBold(true);

$active_filters = [];
if (!empty($filters['etat'])) $active_filters[] = "État: " . $filters['etat'];
if (!empty($filters['raison'])) $active_filters[] = "Raison: " . $filters['raison'];
if (!empty($filters['date_debut'])) $active_filters[] = "Du: " . $filters['date_debut'];
if (!empty($filters['date_fin'])) $active_filters[] = "Au: " . $filters['date_fin'];
if (!empty($filters['immatriculation'])) $active_filters[] = "Immatriculation: " . $filters['immatriculation'];
if (!empty($filters['chauffeur'])) $active_filters[] = "Chauffeur: " . $filters['chauffeur'];
if (!empty($filters['port']) && $filters['port'] !== 'all') {
    // Récupérer le nom du port
    $port_query = $conn->prepare("SELECT nom FROM port WHERE id = ?");
    $port_query->bind_param("i", $filters['port']);
    $port_query->execute();
    $port_result = $port_query->get_result();
    if ($port_row = $port_result->fetch_assoc()) {
        $active_filters[] = "Port: " . $port_row['nom'];
    }
}

$filter_text = empty($active_filters) ? 'Aucun filtre' : implode(' | ', $active_filters);
$sheet->setCellValue('B' . $row, $filter_text);

// Statistiques
$row = 4;
$sheet->setCellValue('A' . $row, 'Nombre total de camions:');
$sheet->getStyle('A' . $row)->getFont()->setBold(true);
$sheet->setCellValue('B' . $row, count($camions));

// En-têtes du tableau
$row = 6;
$headers = [
    'Date Entrée',
    'Immatriculation',
    'Marque',
    'Type',
    'Chauffeur',
    'Téléphone',
    'État',
    'Raison',
    'Poids',
    'Provenance',
    'Agence',
    'NIF',
    'Destinataire'
];

$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4F81BD'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . $row, $header);
    $sheet->getStyle($col . $row)->applyFromArray($headerStyle);
    $col++;
}

// Données du tableau
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
];

$row++;
foreach ($camions as $camion) {
    $data = [
        date('d/m/Y H:i', strtotime($camion['date_entree'])),
        cleanData($camion['immatriculation']),
        cleanData($camion['marque']),
        cleanData($camion['type_camion']),
        cleanData(($camion['prenom_chauffeur'] ?? '') . ' ' . ($camion['nom_chauffeur'] ?? '')),
        cleanData($camion['telephone_chauffeur']),
        cleanData($camion['etat']),
        cleanData($camion['raison']),
        $camion['poids'] ? number_format($camion['poids'], 2) . ' kg' : '-',
        cleanData($camion['port']),
        cleanData($camion['agence']),
        cleanData($camion['nif']),
        cleanData($camion['destinataire'])
    ];

    $col = 'A';
    foreach ($data as $value) {
        $sheet->setCellValue($col . $row, $value);
        $sheet->getStyle($col . $row)->applyFromArray($dataStyle);
        $col++;
    }
    $row++;
}

// Date de génération
$row += 2;
$sheet->setCellValue('A' . $row, 'Rapport généré le ' . date('d/m/Y à H:i'));
$sheet->mergeCells('A' . $row . ':M' . $row);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// Ajuster la largeur des colonnes
foreach (range('A', 'M') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// En-têtes HTTP pour le téléchargement
$filename = 'rapport_camions_entrants_' . date('Y-m-d_H-i-s') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Créer le writer et sortir le fichier
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>