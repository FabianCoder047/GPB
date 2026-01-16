<?php
session_start();
require_once '../../config/db_connect.php';

// Vérifier l'authentification
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'agentDouane') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    exit();
}

// Vérifier les paramètres
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants']);
    exit();
}

$type_entite = $conn->real_escape_string($_GET['type']);
$id_entite = intval($_GET['id']);

try {
    $sql = "SELECT * FROM frais_transit 
            WHERE type_entite = ? AND id_entite = ? 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $type_entite, $id_entite);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $frais = $result->fetch_assoc();
        echo json_encode($frais);
    } else {
        // Retourner un objet vide si aucun frais n'existe
        echo json_encode([
            'frais_thc' => null,
            'frais_magasinage' => null,
            'droits_douane' => null,
            'surestaries' => null,
            'commentaire' => null
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}
?>