<?php
session_start();
require_once '../../config/db_connect.php';

// Vérifier la session
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'enregistreurEntreeBateau') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    exit();
}

// Récupérer l'ID du bateau
$bateau_id = $_GET['id'] ?? null;

if (!$bateau_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID du bateau manquant']);
    exit();
}

try {
    // Récupérer les détails du bateau
    $stmt = $conn->prepare("
        SELECT 
            be.*, 
            tb.nom as type_bateau, 
            p.nom as port_nom,
            p.id as port_id
        FROM bateau_entrant be
        LEFT JOIN type_bateau tb ON be.id_type_bateau = tb.id
        LEFT JOIN port p ON be.id_port = p.id
        WHERE be.id = ?
    ");
    $stmt->bind_param("i", $bateau_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Bateau non trouvé']);
        exit();
    }
    
    $bateau = $result->fetch_assoc();
    
    // Récupérer les marchandises
    $stmt = $conn->prepare("
        SELECT mbe.*, tm.nom as type_marchandise
        FROM marchandise_bateau_entrant mbe
        LEFT JOIN type_marchandise tm ON mbe.id_type_marchandise = tm.id
        WHERE mbe.id_bateau_entrant = ?
        ORDER BY mbe.date_ajout DESC
    ");
    $stmt->bind_param("i", $bateau_id);
    $stmt->execute();
    $marchandises_result = $stmt->get_result();
    $marchandises = $marchandises_result->fetch_all(MYSQLI_ASSOC);
    
    // Ajouter les marchandises aux données du bateau
    $bateau['marchandises'] = $marchandises;
    
    // Retourner les données en JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($bateau, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>