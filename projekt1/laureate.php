<?php
require_once ('config.php');
$db = connectDatabase($hostname, $database, $username, $password);

// Проверяем, есть ли ID в URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid laureate ID.");
}

$laureate_id = (int) $_GET['id'];

// Запрос в базу данных для получения информации о лауреате
$query = "SELECT laureates.*, 
                 countries.country_name, 
                 prices.year, 
                 prices.category, 
                 prices.contrib_sk, 
                 prices.contrib_en 
          FROM laureates 
          LEFT JOIN laureates_countries ON laureates.id = laureates_countries.laureate_id
          LEFT JOIN countries ON laureates_countries.country_id = countries.id
          LEFT JOIN laureate_prizes ON laureates.id = laureate_prizes.laureate_id
          LEFT JOIN prices ON laureate_prizes.prize_id = prices.id
          WHERE laureates.id = :laureate_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':laureate_id', $laureate_id, PDO::PARAM_INT);
$stmt->execute();
$laureate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$laureate) {
    die("No laureate found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($laureate['full_name']); ?></title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        /* Общие стили */
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(to right, #4facfe, #00f2fe);
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        /* Заголовок страницы (H1) */
        .page-title {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            color: white;
            text-shadow: 1px 1px 5px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
        }

        /* Центрирование контейнера */
        .container-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        /* Карточка лауреата */
        .container {
            background: white;
            max-width: 600px;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            text-align: center;
            animation: fadeIn 0.5s ease-in-out;
        }

        h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }

        p {
            font-size: 16px;
            margin: 8px 0;
            color: #555;
        }

        .highlight {
            font-weight: bold;
            color: #4facfe;
        }

        /* Кнопка "Назад" */
        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            font-weight: bold;
            color: white;
            background: #4facfe;
            padding: 10px 15px;
            border-radius: 8px;
            transition: 0.3s;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
        }

        .back-link:hover {
            background: #007bff;
            transform: scale(1.05);
        }

        /* Анимация */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Адаптивность */
        @media (max-width: 768px) {
            .container {
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
<h1 class="page-title">Information about laureate</h1>

<div class="container-wrapper">
    <div class="container">
        <h1><?php echo htmlspecialchars($laureate['full_name']); ?></h1>
        <p><span class="highlight">Organization:</span> <?php echo htmlspecialchars($laureate['oragnization'] ?: "N/A"); ?></p>
        <p><span class="highlight">Sex:</span> <?php echo htmlspecialchars($laureate['sex']); ?></p>
        <p><span class="highlight">Birth Date:</span> <?php echo htmlspecialchars($laureate['date_of_birth']); ?></p>
        <p><span class="highlight">Death Date:</span> <?php echo $laureate['date_of_death'] ? htmlspecialchars($laureate['date_of_death']) : "Still alive"; ?></p>
        <p><span class="highlight">Country:</span> <?php echo htmlspecialchars($laureate['country_name']); ?></p>
        <p><span class="highlight">Year of Award:</span> <?php echo htmlspecialchars($laureate['year']); ?></p>
        <p><span class="highlight">Category:</span> <?php echo htmlspecialchars($laureate['category']); ?></p>
        <p><span class="highlight">Contribution (SK):</span> <?php echo htmlspecialchars($laureate['contrib_sk']); ?></p>
        <p><span class="highlight">Contribution (EN):</span> <?php echo htmlspecialchars($laureate['contrib_en']); ?></p>

        <a class="back-link" href="index.php">← Back to list</a>
    </div>
</div>
</body>
</html>
