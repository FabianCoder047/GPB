<?php
// Paramètres de connexion à la base de données
$host = "localhost";
$user = "root";        
$password = "";        
$dbname = "gpb";   

// Création de la connexion
$conn = new mysqli($host, $user, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Définir l'encodage 
$conn->set_charset("utf8mb4");

?>
