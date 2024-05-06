<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "smartspenddb";
$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}

$userID = $_SESSION['user_id'];
$currentMonthYear = date('Y-m-01');
$alerts = [];

// Récupérer le budget du mois en cours, l'objectif d'épargne et les dépenses totales
$stmt = $conn->prepare("SELECT BudgetAmount, SavingsGoal FROM MonthlyBudgets WHERE UserID = ? AND MonthYear = ?");
$stmt->bind_param("is", $userID, $currentMonthYear);
$stmt->execute();
$budgetData = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT IFNULL(SUM(Montant), 0) AS TotalSpent FROM Transactions WHERE UserID = ? AND MONTH(Date) = MONTH(?) AND YEAR(Date) = YEAR(?)");
$stmt->bind_param("iss", $userID, $currentMonthYear, $currentMonthYear);
$stmt->execute();
$spendingData = $stmt->get_result()->fetch_assoc();

$remainingBudget = $budgetData['BudgetAmount'] - $spendingData['TotalSpent'] - $budgetData['SavingsGoal'];

// Générer des alertes en fonction des dépenses par rapport au budget et à l'objectif d'épargne
if ($remainingBudget < 0.1 * $budgetData['BudgetAmount']) {
    $alerts[] = "Attention! Il reste moins de 10% de votre budget total.";
}

if ($spendingData['TotalSpent'] > $budgetData['BudgetAmount']) {
    $alerts[] = "Vous avez dépassé votre budget mensuel!";
}

$monthlySpendingData = [];
$monthlyBudgetData = [];

// Récupérer les données historiques des 12 derniers mois pour les tendances
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m-01', strtotime("-$i months"));
    $stmt = $conn->prepare("SELECT IFNULL(SUM(Montant), 0) AS TotalSpent FROM Transactions WHERE UserID = ? AND MONTH(Date) = MONTH(?) AND YEAR(Date) = YEAR(?)");
    $stmt->bind_param("iss", $userID, $month, $month);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $monthlySpendingData[] = $result['TotalSpent'];

    $stmt = $conn->prepare("SELECT BudgetAmount FROM MonthlyBudgets WHERE UserID = ? AND MonthYear = ?");
    $stmt->bind_param("is", $userID, $month);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $monthlyBudgetData[] = $result ? $result['BudgetAmount'] : 0; // Assurer un montant de budget ou zéro s'il n'y en a pas
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSpend - Alertes</title>
    <link rel="stylesheet" href="alertes.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <h1>Alertes</h1>
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
        <div class="alerts">
            <?php foreach ($alerts as $alert): ?>
                <p style="color: white;
    background-color: red; /* Rouge vif pour une haute visibilité */
    padding: 10px;
    border-radius: 5px;
    margin: 10px 0;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); /* Ombre douce pour un effet 3D */
    font-weight: bold;
    animation: blink 2s linear infinite;"><?php echo $alert; ?></p>
            <?php endforeach; ?>
        </div>
        <canvas id="monthlySpendingChart"></canvas>
        <canvas id="budgetChart"></canvas>
    </main>
    <script>
        var ctx = document.getElementById('monthlySpendingChart').getContext('2d');
        var monthlySpendingChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [...Array(12).keys()].map(i => new Date(new Date().setMonth(new Date().getMonth() - i)).toLocaleString('fr', { month: 'short', year: 'numeric' }).toUpperCase()).reverse(),
                datasets: [{
                    label: 'Dépenses Mensuelles (€)',
                    data: [<?php echo implode(',', $monthlySpendingData); ?>],
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    fill: true
                }, {
                    label: 'Budget Mensuel (€)',
                    data: [<?php echo implode(',', $monthlyBudgetData); ?>],
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    fill: false
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        var ctx2 = document.getElementById('budgetChart').getContext('2d');
        var budgetChart = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: ['Budget Total', 'Dépenses', 'Économies', 'Budget Restant'],
                datasets: [{
                    label: 'Budget et Économies',
                    data: [<?php echo $budgetData['BudgetAmount']; ?>, <?php echo $spendingData['TotalSpent']; ?>, <?php echo $budgetData['SavingsGoal']; ?>, <?php echo $remainingBudget; ?>],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.2)', // Bleu pour le budget total
                        'rgba(255, 99, 132, 0.2)', // Rouge pour les dépenses
                        'rgba(75, 192, 192, 0.2)', // Vert pour l'objectif d'épargne
                        'rgba(153, 102, 255, 0.2)' // Violet pour le budget restant
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
    <footer>
        <p>© 2024 SmartSpend. Tous droits réservés.</p>
    </footer>
</body>
</html>
