<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? null;

// Vérifier si l'utilisateur est connecté et a le bon rôle
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'enregistreurEntreeCamion') {
    header("Location: ../../login.php");
    exit();
}

// Variable pour stocker l'ID du camion en cours d'édition
$editing_id = null;
$editing_data = null;

// Traitement du formulaire si soumis (CREATE ou UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editing_id = $_POST['editing_id'] ?? null;
    $immatriculation = $_POST['immatriculation'] ?? '';
    $idTypeCamion = $_POST['idTypeCamion'] ?? '';
    $nom_chauffeur = $_POST['nom_chauffeur'] ?? '';
    $prenom_chauffeur = $_POST['prenom_chauffeur'] ?? '';
    $telephone_chauffeur = $_POST['telephone_chauffeur'] ?? '';
    $idPort = $_POST['idPort'] ?? '';
    $etat = $_POST['etat'] ?? '';
    $marque = $_POST['marque'] ?? '';
    $nif = $_POST['nif'] ?? '';
    $agence = $_POST['agence'] ?? '';
    $destinataire = $_POST['destinataire'] ?? '';
    $t1 = $_POST['t1'] ?? '';
    $raison = $_POST['raison'] ?? '';
    $poids = $_POST['poids'] ?? '';
    
    if (!empty($immatriculation) && !empty($idTypeCamion)) {
        try {
            if (!empty($editing_id)) {
                // Mode ÉDITION - UPDATE
                $stmt = $conn->prepare("UPDATE camions_entrants SET immatriculation=?, idTypeCamion=?, nom_chauffeur=?, prenom_chauffeur=?, telephone_chauffeur=?, idPort=?, etat=?, marque=?, nif=?, agence=?, destinataire=?, t1=?, raison=?, poids=? WHERE idEntree=?");
                $stmt->bind_param("sisssssssssssdi", $immatriculation, $idTypeCamion, $nom_chauffeur, $prenom_chauffeur, $telephone_chauffeur, $idPort, $etat, $marque, $nif, $agence, $destinataire, $t1, $raison, $poids, $editing_id);
                $stmt->execute();
                $success = "Camion modifié avec succès!";
            } else {
                // Mode AJOUT - INSERT
                $stmt = $conn->prepare("INSERT INTO camions_entrants (immatriculation, idTypeCamion, nom_chauffeur, prenom_chauffeur, telephone_chauffeur, idPort, etat, marque, nif, agence, destinataire, t1, raison, date_entree, poids) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
                $stmt->bind_param("sisssssssssssd", $immatriculation, $idTypeCamion, $nom_chauffeur, $prenom_chauffeur, $telephone_chauffeur, $idPort, $etat, $marque, $nif, $agence, $destinataire, $t1, $raison, $poids);
                $stmt->execute();
                $success = "Camion enregistré avec succès!";
            }
            
            // Réinitialiser les valeurs du formulaire
            $_POST = array();
            $editing_id = null;
            $editing_data = null;
        } catch (Exception $e) {
            $error = "Erreur lors de l'enregistrement: " . $e->getMessage();
        }
    } else {
        $error = "Veuillez remplir les champs obligatoires";
    }
}

// Si on clique sur une ligne du tableau ou si on a un paramètre edit dans l'URL
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editing_id = $_GET['edit'];
    try {
        $stmt = $conn->prepare("SELECT * FROM camions_entrants WHERE idEntree = ?");
        $stmt->bind_param("i", $editing_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $editing_data = $result->fetch_assoc();
            // Pré-remplir les données POST pour le formulaire
            $_POST = [
                'immatriculation' => $editing_data['immatriculation'],
                'idTypeCamion' => $editing_data['idTypeCamion'],
                'nom_chauffeur' => $editing_data['nom_chauffeur'],
                'prenom_chauffeur' => $editing_data['prenom_chauffeur'],
                'telephone_chauffeur' => $editing_data['telephone_chauffeur'],
                'idPort' => $editing_data['idPort'],
                'etat' => $editing_data['etat'],
                'marque' => $editing_data['marque'],
                'nif' => $editing_data['nif'],
                'agence' => $editing_data['agence'],
                'destinataire' => $editing_data['destinataire'],
                't1' => $editing_data['t1'],
                'raison' => $editing_data['raison'],
                'poids' => $editing_data['poids']
            ];
        }
    } catch (Exception $e) {
        $error = "Erreur lors du chargement des données: " . $e->getMessage();
    }
}

// Récupérer les données pour les listes déroulantes
$types_camions = [];
$ports = [];
try {
    // Types de camions
    $result = $conn->query("SELECT * FROM type_camion ORDER BY nom");
    $types_camions = $result->fetch_all(MYSQLI_ASSOC);
    
    // Ports
    $result = $conn->query("SELECT * FROM port ORDER BY nom");
    $ports = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
}

// Pagination - Récupérer la liste des camions
$camions = [];
$total_pages = 1;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;

try {
    // Compter le nombre total de camions
    $count_result = $conn->query("SELECT COUNT(*) as total FROM camions_entrants");
    $count_data = $count_result->fetch_assoc();
    $total_items = $count_data['total'];
    $total_pages = ceil($total_items / $items_per_page);
    
    // Assurer que la page actuelle est valide
    if ($current_page < 1) $current_page = 1;
    if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
    
    // Calculer l'offset
    $offset = ($current_page - 1) * $items_per_page;
    
    // Récupérer les camions pour la page actuelle
    $query = "SELECT ce.*, tc.nom as type_camion, p.nom as port 
              FROM camions_entrants ce
              LEFT JOIN type_camion tc ON ce.idTypeCamion = tc.id
              LEFT JOIN port p ON ce.idPort = p.id
              ORDER BY ce.date_entree DESC
              LIMIT $items_per_page OFFSET $offset";
    $result = $conn->query($query);
    $camions = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $editing_id ? 'Modifier' : 'Enregistrement'; ?> des Camions Entrants</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        function editCamion(id) {
            // Ajouter le paramètre edit à l'URL et recharger la page
            const url = new URL(window.location);
            url.searchParams.set('edit', id);
            window.location.href = url.toString();
        }
        
        function cancelEdit() {
            // Retirer le paramètre edit de l'URL
            const url = new URL(window.location);
            url.searchParams.delete('edit');
            window.location.href = url.toString();
        }
        
        function updateRaisonOptions() {
            const etat = document.getElementById('etat').value;
            const raisonSelect = document.getElementById('raison');
            
            // Vider les options actuelles
            raisonSelect.innerHTML = '';
            
            // Ajouter une option vide
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Sélectionner une raison';
            raisonSelect.appendChild(defaultOption);
            
            // Définir les options en fonction de l'état
            if (etat === 'Chargé') {
                const options = ['Pesage', 'Déchargement', 'Déchargement et chargement'];
                options.forEach(option => {
                    const opt = document.createElement('option');
                    opt.value = option;
                    opt.textContent = option;
                    raisonSelect.appendChild(opt);
                });
            } else if (etat === 'Vide') {
                const opt = document.createElement('option');
                opt.value = 'Chargement';
                opt.textContent = 'Chargement';
                raisonSelect.appendChild(opt);
            }
            
            // Sélectionner la valeur précédente si elle existe
            const previousValue = "<?php echo $_POST['raison'] ?? ''; ?>";
            if (previousValue) {
                raisonSelect.value = previousValue;
            }
        }
        
        // Gestion des étapes du formulaire
        document.addEventListener('DOMContentLoaded', function() {
            updateRaisonOptions();
            
            const steps = document.querySelectorAll('.form-step');
            const nextBtns = document.querySelectorAll('.next-step');
            const prevBtns = document.querySelectorAll('.prev-step');
            const progress = document.getElementById('progress');
            
            // Initialiser les étapes
            let currentStep = 0;
            showStep(currentStep);
            
            // Gestion du bouton "Suivant"
            nextBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Valider l'étape actuelle avant de continuer
                    if (validateStep(currentStep)) {
                        currentStep++;
                        showStep(currentStep);
                        updateProgress();
                    }
                });
            });
            
            // Gestion du bouton "Précédent"
            prevBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    currentStep--;
                    showStep(currentStep);
                    updateProgress();
                });
            });
            
            function showStep(step) {
                steps.forEach((stepElement, index) => {
                    stepElement.classList.toggle('hidden', index !== step);
                });
                
                // Masquer/afficher les boutons de navigation
                document.querySelectorAll('.step-buttons').forEach(buttons => {
                    buttons.classList.toggle('hidden', step !== 0 && step !== steps.length - 1);
                });
                
                if (step === 0) {
                    document.getElementById('prev-btn-container').classList.add('hidden');
                    document.getElementById('next-btn-container').classList.remove('hidden');
                } else if (step === steps.length - 1) {
                    document.getElementById('prev-btn-container').classList.remove('hidden');
                    document.getElementById('next-btn-container').classList.remove('hidden');
                }
            }
            
            function validateStep(step) {
                const currentStepElement = steps[step];
                const requiredFields = currentStepElement.querySelectorAll('[required]');
                
                for (let field of requiredFields) {
                    if (!field.value.trim()) {
                        field.classList.add('border-red-500');
                        field.focus();
                        
                        // Afficher un message d'erreur
                        const errorMsg = field.parentElement.querySelector('.field-error');
                        if (errorMsg) {
                            errorMsg.classList.remove('hidden');
                        }
                        
                        return false;
                    } else {
                        field.classList.remove('border-red-500');
                        const errorMsg = field.parentElement.querySelector('.field-error');
                        if (errorMsg) {
                            errorMsg.classList.add('hidden');
                        }
                    }
                }
                return true;
            }
            
            function updateProgress() {
                const progressPercent = ((currentStep + 1) / steps.length) * 100;
                progress.style.width = `${progressPercent}%`;
                
                // Mettre à jour le texte d'étape
                document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
                    if (index <= currentStep) {
                        indicator.classList.add('text-blue-600', 'font-bold');
                        indicator.classList.remove('text-gray-400');
                    } else {
                        indicator.classList.remove('text-blue-600', 'font-bold');
                        indicator.classList.add('text-gray-400');
                    }
                });
            }
        });
    </script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto p-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Section Formulaire -->
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-truck-moving mr-2"></i>
                        <?php echo $editing_id ? 'Modifier un Camion' : 'Enregistrer un Nouveau Camion'; ?>
                    </h2>
                    <?php if ($editing_id): ?>
                        <button onclick="cancelEdit()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                            <i class="fas fa-times mr-2"></i>Annuler
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$editing_id): ?>
                <!-- Indicateur de progression (uniquement pour l'ajout) -->
                <div class="mb-6">
                    <div class="flex justify-between mb-2">
                        <span class="step-indicator">Étape 1: Informations camion</span>
                        <span class="step-indicator text-gray-400">Étape 2: Informations complémentaires</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div id="progress" class="bg-blue-600 h-2.5 rounded-full" style="width: 50%"></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4" id="multiStepForm">
                    <!-- Champ caché pour l'ID en cours d'édition -->
                    <input type="hidden" name="editing_id" value="<?php echo $editing_id ?? ''; ?>">
                    
                    <?php if ($editing_id): ?>
                        <!-- Mode édition - Formulaire complet -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="immatriculation">
                                    Immatriculation *
                                </label>
                                <input type="text" id="immatriculation" name="immatriculation" 
                                       value="<?php echo htmlspecialchars($_POST['immatriculation'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       required>
                                <div class="field-error hidden text-red-500 text-xs mt-1">Ce champ est obligatoire</div>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="marque">
                                    Marque
                                </label>
                                <input type="text" id="marque" name="marque"
                                       value="<?php echo htmlspecialchars($_POST['marque'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="idTypeCamion">
                                Type de Camion *
                            </label>
                            <select id="idTypeCamion" name="idTypeCamion" 
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required>
                                <option value="">Sélectionner un type</option>
                                <?php foreach ($types_camions as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                        <?php echo ($_POST['idTypeCamion'] ?? '') == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="field-error hidden text-red-500 text-xs mt-1">Ce champ est obligatoire</div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="prenom_chauffeur">
                                    Prénom Chauffeur
                                </label>
                                <input type="text" id="prenom_chauffeur" name="prenom_chauffeur"
                                       value="<?php echo htmlspecialchars($_POST['prenom_chauffeur'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="nom_chauffeur">
                                    Nom Chauffeur
                                </label>
                                <input type="text" id="nom_chauffeur" name="nom_chauffeur"
                                       value="<?php echo htmlspecialchars($_POST['nom_chauffeur'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="telephone_chauffeur">
                                Téléphone Chauffeur
                            </label>
                            <input type="text" id="telephone_chauffeur" name="telephone_chauffeur"
                                   value="<?php echo htmlspecialchars($_POST['telephone_chauffeur'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="idPort">
                                    Provenance (Port)
                                </label>
                                <select id="idPort" name="idPort" 
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Sélectionner un port</option>
                                    <?php foreach ($ports as $port): ?>
                                        <option value="<?php echo $port['id']; ?>"
                                            <?php echo ($_POST['idPort'] ?? '') == $port['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($port['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="etat">
                                    État *
                                </label>
                                <select id="etat" name="etat" 
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        onchange="updateRaisonOptions()"
                                        required>
                                    <option value="">Sélectionner un état</option>
                                    <option value="Chargé" <?php echo ($_POST['etat'] ?? '') == 'Chargé' ? 'selected' : ''; ?>>Chargé</option>
                                    <option value="Vide" <?php echo ($_POST['etat'] ?? '') == 'Vide' ? 'selected' : ''; ?>>Vide</option>
                                </select>
                                <div class="field-error hidden text-red-500 text-xs mt-1">Ce champ est obligatoire</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="raison">
                                    Raison *
                                </label>
                                <select id="raison" name="raison"
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        required>
                                    <option value="">Sélectionner une raison</option>
                                </select>
                                <div class="field-error hidden text-red-500 text-xs mt-1">Ce champ est obligatoire</div>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="poids">
                                    Poids
                                </label>
                                <input type="number" id="poids" name="poids" step="0.01"
                                       value="<?php echo htmlspecialchars($_POST['poids'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="nif">
                                    NIF
                                </label>
                                <input type="text" id="nif" name="nif"
                                       value="<?php echo htmlspecialchars($_POST['nif'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="agence">
                                    Agence
                                </label>
                                <input type="text" id="agence" name="agence"
                                       value="<?php echo htmlspecialchars($_POST['agence'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="destinataire">
                                    Destinataire
                                </label>
                                <input type="text" id="destinataire" name="destinataire"
                                       value="<?php echo htmlspecialchars($_POST['destinataire'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="t1">
                                    T1
                                </label>
                                <input type="text" id="t1" name="t1"
                                       value="<?php echo htmlspecialchars($_POST['t1'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" 
                                    class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-6 rounded-lg focus:outline-none focus:shadow-outline w-full">
                                <i class="fas fa-save mr-2"></i>Modifier le Camion
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Mode ajout - Formulaire en 2 étapes -->
                        <!-- Étape 1: Informations camion et chauffeur -->
                        <div class="form-step" id="step1">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="immatriculation">
                                        Immatriculation *
                                    </label>
                                    <input type="text" id="immatriculation" name="immatriculation" 
                                           value="<?php echo htmlspecialchars($_POST['immatriculation'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           required>
                                    <div class="field-error hidden text-red-500 text-xs mt-1">Ce champ est obligatoire</div>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="marque">
                                        Marque
                                    </label>
                                    <input type="text" id="marque" name="marque"
                                           value="<?php echo htmlspecialchars($_POST['marque'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="idTypeCamion">
                                    Type de Camion *
                                </label>
                                <select id="idTypeCamion" name="idTypeCamion" 
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        required>
                                    <option value="">Sélectionner un type</option>
                                    <?php foreach ($types_camions as $type): ?>
                                        <option value="<?php echo $type['id']; ?>" 
                                            <?php echo ($_POST['idTypeCamion'] ?? '') == $type['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="field-error hidden text-red-500 text-xs mt-1">Ce champ est obligatoire</div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="prenom_chauffeur">
                                        Prénom Chauffeur
                                    </label>
                                    <input type="text" id="prenom_chauffeur" name="prenom_chauffeur"
                                           value="<?php echo htmlspecialchars($_POST['prenom_chauffeur'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="nom_chauffeur">
                                        Nom Chauffeur
                                    </label>
                                    <input type="text" id="nom_chauffeur" name="nom_chauffeur"
                                           value="<?php echo htmlspecialchars($_POST['nom_chauffeur'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="telephone_chauffeur">
                                    Téléphone Chauffeur
                                </label>
                                <input type="text" id="telephone_chauffeur" name="telephone_chauffeur"
                                       value="<?php echo htmlspecialchars($_POST['telephone_chauffeur'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="step-buttons flex justify-end mt-6">
                                <button type="button" 
                                        class="next-step bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded-lg focus:outline-none focus:shadow-outline">
                                    Suivant <i class="fas fa-arrow-right ml-2"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Étape 2: Informations complémentaires -->
                        <div class="form-step hidden" id="step2">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="idPort">
                                        Provenance (Port)
                                    </label>
                                    <select id="idPort" name="idPort" 
                                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Sélectionner un port</option>
                                        <?php foreach ($ports as $port): ?>
                                            <option value="<?php echo $port['id']; ?>"
                                                <?php echo ($_POST['idPort'] ?? '') == $port['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($port['nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="etat">
                                        État *
                                    </label>
                                    <select id="etat" name="etat" 
                                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            onchange="updateRaisonOptions()"
                                            required>
                                        <option value="">Sélectionner un état</option>
                                        <option value="Chargé" <?php echo ($_POST['etat'] ?? '') == 'Chargé' ? 'selected' : ''; ?>>Chargé</option>
                                        <option value="Vide" <?php echo ($_POST['etat'] ?? '') == 'Vide' ? 'selected' : ''; ?>>Vide</option>
                                    </select>
                                    <div class="field-error hidden text-red-500 text-xs mt-1">Ce champ est obligatoire</div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="raison">
                                        Raison *
                                    </label>
                                    <select id="raison" name="raison"
                                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            required>
                                        <option value="">Sélectionner une raison</option>
                                    </select>
                                    <div class="field-error hidden text-red-500 text-xs mt-1">Ce champ est obligatoire</div>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="poids">
                                        Poids
                                    </label>
                                    <input type="number" id="poids" name="poids" step="0.01"
                                           value="<?php echo htmlspecialchars($_POST['poids'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="nif">
                                        NIF
                                    </label>
                                    <input type="text" id="nif" name="nif"
                                           value="<?php echo htmlspecialchars($_POST['nif'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="agence">
                                        Agence
                                    </label>
                                    <input type="text" id="agence" name="agence"
                                           value="<?php echo htmlspecialchars($_POST['agence'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="destinataire">
                                        Destinataire
                                    </label>
                                    <input type="text" id="destinataire" name="destinataire"
                                           value="<?php echo htmlspecialchars($_POST['destinataire'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="t1">
                                        T1
                                    </label>
                                    <input type="text" id="t1" name="t1"
                                           value="<?php echo htmlspecialchars($_POST['t1'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div class="step-buttons flex justify-between mt-6">
                                <div id="prev-btn-container" class="hidden">
                                    <button type="button" 
                                            class="prev-step bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-6 rounded-lg focus:outline-none focus:shadow-outline">
                                        <i class="fas fa-arrow-left mr-2"></i>Précédent
                                    </button>
                                </div>
                                <div id="next-btn-container">
                                    <button type="submit" 
                                            class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-6 rounded-lg focus:outline-none focus:shadow-outline">
                                        <i class="fas fa-save mr-2"></i>Enregistrer
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Section Liste -->
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-list mr-2"></i>Liste des Camions Entrants
                    <?php if ($editing_id): ?>
                        <span class="text-sm font-normal text-blue-600 ml-2">(Mode édition - Cliquez sur une autre ligne pour modifier un autre camion)</span>
                    <?php endif; ?>
                </h2>
                
                <!-- Statistiques -->
                <div class="mb-4 text-sm text-gray-600">
                    Affichage de <span class="font-bold"><?php echo ($current_page - 1) * $items_per_page + 1; ?>-<?php echo min($current_page * $items_per_page, $total_items); ?></span> sur <span class="font-bold"><?php echo $total_items; ?></span> camions
                </div>
                
                <div class="overflow-x-auto">
                    <div class="min-w-full inline-block align-middle">
                        <div class="overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Immatriculation</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Marque</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Type</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Chauffeur</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Tel Chauffeur</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">État</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Poids</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Agence</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">NIF</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Provenance</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Destinataire</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">T1</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Raison</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap tracking-wider">Date Entrée</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($camions as $camion): ?>
                                    <tr onclick="editCamion(<?php echo $camion['idEntree']; ?>)" 
                                        class="hover:bg-blue-50 cursor-pointer transition-colors duration-150 whitespace-nowrap <?php echo ($editing_id == $camion['idEntree']) ? 'bg-blue-100' : ''; ?>">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($camion['immatriculation']); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($camion['marque'] ?? '-'); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($camion['type_camion'] ?? '-'); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars(($camion['prenom_chauffeur'] ?? '') . ' ' . ($camion['nom_chauffeur'] ?? '')); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($camion['telephone_chauffeur'] ?? '-'); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo $camion['etat'] == 'Chargé' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                <?php echo htmlspecialchars($camion['etat'] ?? '-'); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($camion['poids'] ?? '-'); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($camion['agence'] ?? '-'); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($camion['nif'] ?? '-'); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($camion['port'] ?? '-'); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($camion['destinataire'] ?? '-'); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($camion['t1'] ?? '-'); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($camion['raison'] ?? '-'); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('d/m/Y H:i', strtotime($camion['date_entree'])); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($camions)): ?>
                                    <tr>
                                        <td colspan="14" class="px-4 py-4 text-center text-sm text-gray-500">
                                            Aucun camion enregistré
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-between border-t border-gray-200 px-4 py-3 mt-4">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?><?php echo $editing_id ? '&edit=' . $editing_id : ''; ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Précédent</a>
                        <?php endif; ?>
                        <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?><?php echo $editing_id ? '&edit=' . $editing_id : ''; ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Suivant</a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Page <span class="font-medium"><?php echo $current_page; ?></span> sur <span class="font-medium"><?php echo $total_pages; ?></span>
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <?php if ($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?><?php echo $editing_id ? '&edit=' . $editing_id : ''; ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                    <span class="sr-only">Précédent</span>
                                    <i class="fas fa-chevron-left h-5 w-5"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php
                                // Afficher les numéros de page
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $start_page + 4);
                                
                                if ($end_page - $start_page < 4) {
                                    $start_page = max(1, $end_page - 4);
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                <a href="?page=<?php echo $i; ?><?php echo $editing_id ? '&edit=' . $editing_id : ''; ?>" 
                                   class="relative inline-flex items-center px-4 py-2 text-sm font-semibold 
                                          <?php echo $i == $current_page ? 'bg-blue-600 text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>
                                
                                <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?><?php echo $editing_id ? '&edit=' . $editing_id : ''; ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                    <span class="sr-only">Suivant</span>
                                    <i class="fas fa-chevron-right h-5 w-5"></i>
                                </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
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