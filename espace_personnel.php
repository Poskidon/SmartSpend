<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Configuration de la connexion à la base de données
$servername = "localhost";
$username = "root";
$password = "";
$database = "smartspenddb";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("La connexion a échoué : " . $conn->connect_error);
}

$message = "";
$userID = $_SESSION['user_id'];

if (isset($_POST['ajouter'])) {
    $categorieID = $_POST['categorie'];
    $montant = $_POST['montant'];
    $date = $_POST['date'];
    $description = $_POST['description'];

    // Calculer les dépenses totales pour le mois en incluant la nouvelle transaction
    $monthYear = date('Y-m-01', strtotime($date));
    $stmt = $conn->prepare("SELECT IFNULL(SUM(Montant), 0) AS TotalSpent FROM Transactions WHERE UserID = ? AND MONTH(Date) = MONTH(?) AND YEAR(Date) = YEAR(?)");
    $stmt->bind_param("iss", $userID, $date, $date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $totalSpent = $result['TotalSpent'] + $montant;

    // Vérifier si cela dépasse le budget mensuel
    $budget_stmt = $conn->prepare("SELECT BudgetAmount FROM MonthlyBudgets WHERE UserID = ? AND MonthYear = ?");
    $budget_stmt->bind_param("is", $userID, $monthYear);
    $budget_stmt->execute();
    $budget_result = $budget_stmt->get_result()->fetch_assoc();

    if ($totalSpent > $budget_result['BudgetAmount']) {
        $message = "<div class='erreur'>Ajouter cette transaction dépasserait votre budget mensuel de " . $budget_result['BudgetAmount'] . ".</div>";
    } else {
        // Insérer la transaction si elle est dans le budget
        $sql_insert = "INSERT INTO Transactions (UserID, CategorieID, Montant, Date, Description) VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($sql_insert);
        $insert_stmt->bind_param("iisss", $userID, $categorieID, $montant, $date, $description);
        if ($insert_stmt->execute()) {
            $message = "<div class='succes'>La transaction a été ajoutée avec succès.</div>";
        } else {
            $message = "<div class='erreur'>Erreur lors de l'insertion de la transaction : " . $conn->error . "</div>";
        }
    }
    $stmt->close();
    $budget_stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSpend - Espace Personnel</title>
    <link rel="stylesheet" href="espace_personnel.css">
</head>
<body>
    <header>
        <h1>Espace Personnel - SmartSpend</h1>
        <nav>
            <ul>
                <li><a href="index.php">Accueil</a></li>
                <li><a href="espace_personnel.php">Espace Personnel</a></li>
                <li><a href="objectifs_financiers.php">Objectifs Financiers</a></li>
                <li><a href="alertes.php">Alertes</a></li>
                <li><a href="transactions.php">Transactions</a></li>
                <li><a href="deconnexion.php">Déconnexion</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <section id="ajouter-transaction">
            <h2>Ajouter une Nouvelle Transaction</h2>
            <form action="" method="post">
                <label for="categorie">Catégorie :</label>
                <select name="categorie" id="categorie">
                    <option value="1">Nourriture</option>
                    <option value="2">Logement</option>
                    <option value="3">Transport</option>
                    <option value="4">Loisirs</option>
                    <option value="5">Santé</option>
                    <option value="6">Éducation</option>
                    <option value="7">Factures</option>
                    <option value="8">Vêtements</option>
                    <option value="9">Épargne</option>
                    <option value="10">Divertissement</option>
                    <option value="11">Voyage</option>
                    <option value="12">Assurance</option>
                    <option value="13">Impôts</option>
                    <option value="14">Remboursements de dettes</option>
                    <option value="15">Autres</option>
                </select><br>
                <label for="montant">Montant :</label>
                <input type="number" name="montant" id="montant" step="0.01" min="0" required><br>
                <label for="date">Date :</label>
                <input type="date" name="date" id="date" required><br>
                <label for="description">Description :</label>
                <textarea name="description" id="description" rows="3"></textarea><br>
                <button type="submit" name="ajouter">Ajouter Transaction</button>
            </form>
            <?php echo $message; ?>
        </section>
    </main>
    <footer>
        <p>© 2024 SmartSpend. Tous droits réservés.</p>
    </footer>
</body>
</html>
