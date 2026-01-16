<?php
session_start();
require_once '../../config/db_connect.php';

// Vérifier l'authentification
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'agentDouane') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    exit();
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données
$data = json_decode(file_get_contents('php://input'), true);

// Valider les données
$required_fields = ['type_entite', 'id_entite'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Le champ $field est requis"]);
        exit();
    }
}

// Nettoyer les données
$type_entite = $conn->real_escape_string($data['type_entite']);
$id_entite = intval($data['id_entite']);
$frais_thc = isset($data['frais_thc']) && $data['frais_thc'] !== '' ? floatval($data['frais_thc']) : NULL;
$frais_magasinage = isset($data['frais_magasinage']) && $data['frais_magasinage'] !== '' ? floatval($data['frais_magasinage']) : NULL;
$droits_douane = isset($data['droits_douane']) && $data['droits_douane'] !== '' ? floatval($data['droits_douane']) : NULL;
$surestaries = isset($data['surestaries']) && $data['surestaries'] !== '' ? floatval($data['surestaries']) : NULL;
$commentaire = isset($data['commentaire']) ? $conn->real_escape_string($data['commentaire']) : NULL;

try {
    // Vérifier si des frais existent déjà pour cette entité
    $check_sql = "SELECT id FROM frais_transit WHERE type_entite = ? AND id_entite = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $type_entite, $id_entite);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Mettre à jour les frais existants
        $sql = "UPDATE frais_transit SET 
                frais_thc = ?, 
                frais_magasinage = ?, 
                droits_douane = ?, 
                surestaries = ?, 
                commentaire = ?,
                date_modification = CURRENT_TIMESTAMP
                WHERE type_entite = ? AND id_entite = ?";
    } else {
        // Insérer de nouveaux frais
        $sql = "INSERT INTO frais_transit 
                (type_entite, id_entite, frais_thc, frais_magasinage, droits_douane, surestaries, commentaire)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
    }
    
    $stmt = $conn->prepare($sql);
    
    if ($check_result->num_rows > 0) {
        // Pour UPDATE
        $stmt->bind_param("ddddssi", 
            $frais_thc, $frais_magasinage, $droits_douane, $surestaries, $commentaire,
            $type_entite, $id_entite
        );
    } else {
        // Pour INSERT
        $stmt->bind_param("sidddds", 
            $type_entite, $id_entite,
            $frais_thc, $frais_magasinage, $droits_douane, $surestaries, $commentaire
        );
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Frais enregistrés avec succès'
        ]);
    } else {
        throw new Exception($stmt->error);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
    ]);
}
?>