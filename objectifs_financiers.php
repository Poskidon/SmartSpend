<?php
session_start();

// Database connection setup
$servername = "localhost";
$username = "root";
$password = "";
$database = "smartspenddb";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}

$message = "";
$userID = $_SESSION['user_id'];

// Handle setting or updating the monthly budget and savings goal
if (isset($_POST['set_budget'])) {
    $monthYear = $_POST['month_year'] . '-01';  // Assurer que la date est le premier jour du mois
    $budgetAmount = $_POST['budget_amount'];
    $savingsGoal = $_POST['savings_goal'];

    // Vérifier si le budget pour le mois existe, puis mettre à jour ou insérer
    $sql = "SELECT BudgetID FROM MonthlyBudgets WHERE UserID = ? AND MonthYear = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userID, $monthYear);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $budgetID = $row['BudgetID'];
        $sql_update = "UPDATE MonthlyBudgets SET BudgetAmount = ?, SavingsGoal = ? WHERE BudgetID = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("ddi", $budgetAmount, $savingsGoal, $budgetID);
    } else {
        $sql_insert = "INSERT INTO MonthlyBudgets (UserID, MonthYear, BudgetAmount, SavingsGoal) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("isdd", $userID, $monthYear, $budgetAmount, $savingsGoal);
    }
    if ($stmt->execute()) {
        $message = "<div class='success'>Budget et objectif d'épargne définis avec succès.</div>";
    } else {
        $message = "<div class='error'>Erreur lors de la définition du budget et de l'objectif d'épargne : " . $conn->error . "</div>";
    }
    $stmt->close();
}

// Toujours récupérer le budget mensuel et l'objectif d'épargne du mois en cours pour l'affichage
$currentMonthYear = date('Y-m') . '-01'; // Cela garantit qu'il récupère le début du mois en cours
$sql_current = "SELECT BudgetAmount, SavingsGoal FROM MonthlyBudgets WHERE UserID = ? AND MonthYear = ?";
$stmt_current = $conn->prepare($sql_current);
$stmt_current->bind_param("is", $userID, $currentMonthYear);
$stmt_current->execute();
$result_current = $stmt_current->get_result();
$current_settings = $result_current->fetch_assoc();
$stmt_current->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSpend - Objectifs Financiers</title>
    <link rel="stylesheet" href="objectifs_financiers.css">
</head>
<body>
    <header>
        <h1>Objectifs Financiers</h1>
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
    <div class="container">
        <form action="" method="post">
            <label for="month_year">Mois et année :</label>
            <input type="month" id="month_year" name="month_year" required value="<?php echo isset($current_settings['BudgetAmount']) ? date('Y-m', strtotime($currentMonthYear)) : date('Y-m'); ?>">

            <label for="budget_amount">Budget mensuel :</label>
            <input type="number" id="budget_amount" name="budget_amount" step="0.01" required value="<?php echo $current_settings['BudgetAmount'] ?? ''; ?>">

            <label for="savings_goal">Objectif d'épargne :</label>
            <input type="number" id="savings_goal" name="savings_goal" step="0.01" required value="<?php echo $current_settings['SavingsGoal'] ?? ''; ?>">

            <button type="submit" name="set_budget">Définir le budget et l'objectif d'épargne</button>
        </form>
        <?php echo $message; ?>
    </div>
</body>
</html>
