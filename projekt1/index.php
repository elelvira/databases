<?php
require_once ('config.php');

$db = connectDatabase($hostname, $database, $username, $password);

function processStatement($stmt)
{
    if ($stmt->execute()) {
        return 'New record created successfully';
    } else {
        return 'Error: ' . $stmt->errorInfo();
    }
}

function insertLaureat($db, $firstname, $lastname, $oragnization, $sex, $date_of_birth, $date_of_death)
{
    $full_name = $firstname . ' ' . $lastname;

    // Проверяем, существует ли уже такая запись
    $stmt = $db->prepare("SELECT id FROM laureates WHERE full_name = :full_name AND date_of_birth = :date_of_birth");
    $stmt->bindParam(':full_name', $full_name);
    $stmt->bindParam(':date_of_birth', $date_of_birth);
    $stmt->execute();

    $existing_id = $stmt->fetchColumn();
    if ($existing_id) {
        return "Error: Laureate already exists with ID $existing_id"; // Не вставляем, если уже есть
    }

    // Вставка нового лауреата
    $stmt = $db->prepare("INSERT INTO laureates (full_name, oragnization, sex, date_of_birth, date_of_death) 
                        VALUES(:full_name, :oragnization, :sex, :date_of_birth, :date_of_death)");

    if ($oragnization === '') {
        $stmt->bindValue(':oragnization', NULL, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':oragnization', $oragnization, PDO::PARAM_STR);
    }

    $stmt->bindParam(':sex', $sex, PDO::PARAM_STR);
    $stmt->bindParam(':date_of_birth', $date_of_birth, PDO::PARAM_STR);
    $stmt->bindParam(':date_of_death', $date_of_death, PDO::PARAM_STR);
    $stmt->bindParam(':full_name', $full_name);

    return processStatement($stmt);
}

function insertCountry($db, $country_name)
{
    // Проверяем, существует ли страна
    $stmt = $db->prepare("SELECT id FROM countries WHERE country_name = :country_name");
    $stmt->bindParam(':country_name', $country_name);
    $stmt->execute();

    $existing_id = $stmt->fetchColumn();
    if ($existing_id) {
        return "Error: Country already exists with ID $existing_id"; // Не вставляем, если страна уже есть
    }

    // Вставка новой страны
    $stmt = $db->prepare("INSERT INTO countries (country_name) VALUES(:country_name)");
    $stmt->bindParam(':country_name', $country_name, PDO::PARAM_STR);
    return processStatement($stmt);
}


function insertPrice($db, $year, $category, $contrib_sk, $contrib_en, $details_id)
{
    $stmt = $db->prepare("SELECT id FROM prices WHERE year = :year AND category = :category AND contrib_sk = :contrib_sk");
    $stmt->bindParam(':year', $year);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':contrib_sk', $contrib_sk);
    $stmt->execute();

    $existing_id = $stmt->fetchColumn();
    if ($existing_id) {
        return "Error: Price already exists with ID $existing_id"; // Не вставляем дубликаты
    }

    // Вставка нового приза
    $stmt = $db->prepare("INSERT INTO prices (year, category, contrib_sk, contrib_en, details_id) 
                            VALUES(:year, :category, :contrib_sk, :contrib_en, :details_id)");
    $stmt->bindParam(':year', $year, PDO::PARAM_INT);
    $stmt->bindParam(':category', $category, PDO::PARAM_STR);
    $stmt->bindParam(':contrib_sk', $contrib_sk, PDO::PARAM_STR);
    $stmt->bindParam(':contrib_en', $contrib_en, PDO::PARAM_STR);
    $stmt->bindParam(':details_id', $details_id, PDO::PARAM_INT);

    return processStatement($stmt);
}


function insertPriceDetails($db, $language_sk, $language_en, $genre_sk, $genre_en)
{
    $stmt = $db->prepare("INSERT INTO price_details (language_sk, language_en, genre_sk, genre_en) 
                            VALUES (:language_sk, :language_en, :genre_sk, :genre_en)");
    $stmt->bindParam(':language_sk', $language_sk, PDO::PARAM_STR);
    $stmt->bindParam(':language_en', $language_en, PDO::PARAM_STR);
    $stmt->bindParam(':genre_sk', $genre_sk, PDO::PARAM_STR);
    $stmt->bindParam(':genre_en', $genre_en, PDO::PARAM_STR);
    return processStatement($stmt);
}

function boundPrice($db, $laureate_id, $prize_id)
{
    $stmt= $db->prepare("INSERT INTO laureate_prizes (laureate_id, prize_id) 
                            VALUES (:laureate_id, :prize_id)");
    $stmt->bindParam(':laureate_id', $laureate_id, PDO::PARAM_INT);
    $stmt->bindParam(':prize_id', $prize_id, PDO::PARAM_INT);
    return processStatement($stmt);
}

function boundCountry($db, $country_id, $laureate_id)
{
    $stmt= $db->prepare("INSERT INTO laureates_countries (country_id, laureate_id) 
                        VALUES (:country_id, :laureate_id)");
    $stmt->bindParam(':country_id', $country_id, PDO::PARAM_INT);
    $stmt->bindParam(':laureate_id', $laureate_id, PDO::PARAM_INT);
    return processStatement($stmt);
}

function getLaureantCountry($db)
{
    $stmt = $db->prepare("SELECT laureates.full_name, 
                                 COALESCE(NULLIF(laureates.oragnization, ''), laureates.full_name) AS name_or_organization,
                                 laureates.date_of_birth, 
                                 laureates.date_of_death, 
                                 countries.country_name, 
                                 prices.year, 
                                 prices.category
                          FROM laureates 
                          LEFT JOIN laureates_countries 
                          ON laureates.id = laureates_countries.laureate_id
                          LEFT JOIN countries 
                          ON laureates_countries.country_id = countries.id
                          LEFT JOIN laureate_prizes 
                          ON laureates.id = laureate_prizes.laureate_id
                          LEFT JOIN prices 
                          ON laureate_prizes.prize_id = prices.id");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result;
}


function insertLaureantWithCountryWithPrice($db, $firstname, $lastname, $oragnization, $sex, $date_of_birth, $date_of_death, $country_name, $year, $category, $contrib_sk, $contrib_en, $details_id, $language_sk, $language_en, $genre_sk, $genre_en)
{
    $db->beginTransaction();

    $status=insertLaureat($db, $firstname, $lastname, $oragnization, $sex, $date_of_birth, $date_of_death);
    if(strpos($status, 'Error') !== false){
        $db->rollBack();
        return $status;
    }
    $laureate_id = $db->lastInsertId();

    $status=insertCountry($db, $country_name);
    if(strpos($status, 'Error') !== false){
        $db->rollBack();
        return $status;
    }
    $country_id = $db->lastInsertId();

    $status=boundCountry($db, $country_id, $laureate_id);
    if(strpos($status, 'Error') !== false){
        $db->rollBack();
        return $status;
    }

    $status=insertPrice($db, $year, $category, $contrib_sk, $contrib_en, $details_id);
    if(strpos($status, 'Error') !== false){
        $db->rollBack();
        return $status;
    }
    $prize_id=$db->lastInsertId();

    $status=insertPriceDetails($db, $language_sk, $language_en, $genre_sk, $genre_en);
    if(strpos($status, 'Error') !== false){
        $db->rollBack();
        return $status;
    }

    $status=boundPrice($db, $laureate_id, $prize_id);
    if(strpos($status, 'Error') !== false){
        $db->rollBack();
        return $status;
    }

    $db->commit();
    return $status;
}

function parseCSV($filename)
{
    $handle = fopen($filename, 'r');
    $data = [];
    $headers = fgetcsv($handle, 0, ","); // Считываем заголовки (первую строку)

    while (($row = fgetcsv($handle, 0, ",")) !== FALSE) {
        if (count($row) === count($headers)) { // Проверяем, соответствует ли число столбцов заголовкам
            $data[] = $row;
        }
    }

    fclose($handle);
    return $data;
}

function loadCSVFyz($db, $filename)
{
    $data=parseCSV($filename);
    foreach ($data as $row) {
        if(count($row)<9)continue;

        $year = $row[0];
        $firstname = $row[1];
        $lastname = $row[2];
        $sex = $row[3];
        $date_of_birth = $row[4];
        $date_of_death = $row[5] !== '' ? $row[5] : null; // Если пусто, ставим NULL
        $country = $row[6];
        $contrib_sk = $row[7];
        $contrib_en = $row[8];

        $status = insertLaureantWithCountryWithPrice(
            $db,
            $firstname, $lastname, null, $sex, $date_of_birth, $date_of_death,
            $country, $year, "Physics", $contrib_sk, $contrib_en,
            null, "", "", "", "" // Заглушки для price_details
        );
        echo "Insert Status: $status\n";
    }
}

function loadCSVChem($db, $filename)
{
    $data=parseCSV($filename);
    foreach ($data as $row) {
        if(count($row)<9)continue;

        $year = $row[0];
        $firstname = $row[1];
        $lastname = $row[2];
        $sex = $row[3];
        $date_of_birth = $row[4];
        $date_of_death = $row[5] !== '' ? $row[5] : null; // Если пусто, ставим NULL
        $country = $row[6];
        $contrib_sk = $row[7];
        $contrib_en = $row[8];

        $status = insertLaureantWithCountryWithPrice(
            $db,
            $firstname, $lastname, null, $sex, $date_of_birth, $date_of_death,
            $country, $year, "Chemistry", $contrib_sk, $contrib_en,
            null, "", "", "", "" // Заглушки для price_details
        );
        echo "Insert Chem Status: $status\n";
    }
}

function loadCSVMed($db, $filename)
{
    $data=parseCSV($filename);
    foreach ($data as $row) {
        if(count($row)<9)continue;

        $year = $row[0];
        $firstname = $row[1];
        $lastname = $row[2];
        $sex = $row[3];
        $date_of_birth = $row[4];
        $date_of_death = $row[5] !== '' ? $row[5] : null; // Если пусто, ставим NULL
        $country = $row[6];
        $contrib_sk = $row[7];
        $contrib_en = $row[8];

        $status = insertLaureantWithCountryWithPrice(
            $db,
            $firstname, $lastname, null, $sex, $date_of_birth, $date_of_death,
            $country, $year, "Medicine", $contrib_sk, $contrib_en,
            null, "", "", "", "" // Заглушки для price_details
        );
        echo "Insert Med Status: $status\n";
    }
}

function loadCSVLit($db, $filename)
{
    $data=parseCSV($filename);
    foreach ($data as $row) {
        if(count($row)<12)continue;

        $year = $row[0];
        $firstname = $row[1];
        $lastname = $row[2];
        $sex = $row[3];
        $date_of_birth = $row[4];
        $date_of_death = $row[5] !== '' ? $row[5] : null; // Если пусто, ставим NULL
        $country = $row[6];
        $contrib_sk = $row[7];
        $contrib_en = $row[8];
        $language_sk = $row[9];
        $language_en = $row[10];
        $genre_sk = $row[11];
        $genre_en = $row[12];

        $status = insertLaureantWithCountryWithPrice(
            $db,
            $firstname, $lastname, null, $sex, $date_of_birth, $date_of_death,
            $country, $year, "Literature", $contrib_sk, $contrib_en,
            null, $language_sk, $language_en, $genre_sk, $genre_en // Заглушки для price_details
        );
        echo "Insert Liter Status: $status\n";
    }
}

function loadCSVMier($db, $filename)
{
    $data=parseCSV($filename);
    foreach ($data as $row) {
        if(count($row)<10)continue;

        $year = $row[0];
        $firstname = $row[1];
        $lastname = $row[2];
        $oragnization = $row[3];
        $sex = $row[4];
        $date_of_birth = $row[5];
        $date_of_death = $row[6] !== '' ? $row[6] : null; // Если пусто, ставим NULL
        $country = $row[7];
        $contrib_sk = $row[8];
        $contrib_en = $row[9];

        $status = insertLaureantWithCountryWithPrice(
            $db,
            $firstname, $lastname, $oragnization, $sex, $date_of_birth, $date_of_death,
            $country, $year, "Economics", $contrib_sk, $contrib_en,
            null, "", "", "", "" // Заглушки для price_details
        );
        echo "Insert Mier Status: $status\n";

    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laureates from CSV</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- DataTables стили и библиотеки -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        /* Общие стили */
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(to right, #4facfe, #00f2fe);
            color: #333;
        }

        h2 {
            text-align: center;
            color: #fff;
            text-shadow: 1px 1px 5px rgba(0, 0, 0, 0.2);
        }

        /* Контейнер формы */
        .filter-container {
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            max-width: 600px;
            margin: 0 auto 20px;
        }

        /* Стили для селектов */
        select {
            padding: 8px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
            background: #f9f9f9;
        }

        select:hover {
            border-color: #4facfe;
        }

        .dataTables_wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        /* Контейнер для строки управления таблицей */
        .dataTables_length, .dataTables_filter {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 16px;
            color: #fff;
        }

        /* Стили для выпадающего списка "Show entries" */
        .dataTables_length label {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 500;
        }

        /* Стили для "Search" */
        .dataTables_filter input {
            padding: 8px;
            border-radius: 8px;
            border: 1px solid #fff;
            outline: none;
            transition: 0.3s;
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        /* Изменение цвета рамки при фокусе */
        .dataTables_filter input:focus {
            border-color: #00f2fe;
            background: rgba(255, 255, 255, 0.3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #4facfe;
            color: white;
            text-transform: uppercase;
            cursor: pointer;
        }

        tr:nth-child(even) {
            background: #f9f9f9;
        }

        tr:hover {
            background: rgba(79, 172, 254, 0.2);
        }

        /* Навигационная панель */
        .nav-bar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 15px;
            background: rgba(255, 255, 255, 0.2);
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        /* Контейнер для ссылок */
        .nav-links {
            display: flex;
            gap: 15px;
            margin-right: 20px;
        }

        /* Ссылки Login и Register */
        .nav-link {
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            transition: 0.3s;
            background: rgba(255, 255, 255, 0.3);
            white-space: nowrap; /* Чтобы текст не переносился */
        }

        /* Эффект наведения */
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: scale(1.05);
        }
        /* Стили для кнопок */
        .btn-edit, .btn-delete {
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: bold;
            text-decoration: none;
            color: #fff;
            margin-right: 10px;
            display: inline-block;
            cursor: pointer;
            transition: 0.3s;
        }
        .button-bar {
            display: flex;
            gap: 15px;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .upload-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .upload-btn {
            cursor: pointer;
        }

        .btn-edit {
            background-color: #4facfe;
        }

        .btn-delete {
            background-color: #f44336;
        }

        .btn-edit:hover {
            background-color: #3880c3;
        }

        .btn-delete:hover {
            background-color: #d32f2f;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0,0,0);
            background-color: rgba(0,0,0,0.4);
            padding-top: 60px;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        /* Адаптивность */
        @media (max-width: 768px) {
            .dataTables_length, .dataTables_filter {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .filter-container {
                flex-direction: column;
                max-width: 100%;
                padding: 10px;
            }

            select {
                width: 100%;
            }

            table {
                font-size: 14px;
            }

            .nav-bar {
                justify-content: center;
            }
            .nav-links {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
<!-- Навигационная панель -->
<div class="nav-bar">
    <div class="nav-links">
        <a href="../auth/login.php" class="nav-link">Login</a>
        <a href="../auth/register.php" class="nav-link">Register</a>
    </div>
</div>
<h2>Nobel Prices.</h2>
<div id="messageBox" style="text-align: center; margin: 20px 0; font-weight: bold;"></div>

<form method="GET" action="" class="filter-container">
    <label for="year">Year:</label>
    <select name="year" id="year" onchange="this.form.submit()">
        <option value="">All</option>
        <?php
        for ($i = 1901; $i <= 2023; $i++) {
            $selected = (isset($_GET['year']) && $_GET['year'] == $i) ? 'selected' : '';
            echo "<option value=\"$i\" $selected>$i</option>";
        }
        ?>
    </select>
    <label for="category">Category:</label>
    <select name="category" id="category" onchange="this.form.submit()">
        <option value="">All</option>
        <option value="Literature" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Literature') ? 'selected' : ''; ?>>Literature</option>
        <option value="Physics" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Physics') ? 'selected' : ''; ?>>Physics</option>
        <option value="Chemistry" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Chemistry') ? 'selected' : ''; ?>>Chemistry</option>
        <option value="Medicine" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Medicine') ? 'selected' : ''; ?>>Medicine</option>
        <option value="Economics" <?php echo (isset($_GET['category']) && $_GET['category'] == 'Economics') ? 'selected' : ''; ?>>Economics</option>
    </select>
</form>
<div class="button-bar">
    <button class="btn-edit" onclick="openCreateModal()">+ Add Laureate</button>

    <form id="bulkUploadForm" enctype="multipart/form-data" class="upload-form">
        <label for="jsonFile" class="btn-edit upload-btn">📁 Upload JSON</label>
        <input type="file" id="jsonFile" name="jsonFile" accept=".json" required hidden>
        <button type="submit" class="btn-edit">Submit</button>
    </form>

    <a href="../api/apidoc.php" class="btn-edit">📚 API Docs</a>
</div>

<table id="dataTable" class="display">
    <thead>
    <tr>
        <th>Name/Organization</th>
        <?php if (empty($_GET['year'])): ?>
            <th>Year</th>
        <?php endif; ?>
        <th>Country</th>
        <?php if (empty($_GET['category'])): ?>
            <th>Category</th>
        <?php endif; ?>
          <th>Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php
    require_once ('config.php');
    $db = connectDatabase($hostname, $database, $username, $password);

    $yearFilter = isset($_GET['year']) ? $_GET['year'] : '';
    $categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';

    // Запрос к базе данных
    $query = "SELECT laureates.id, 
                 COALESCE(NULLIF(laureates.oragnization, ''), laureates.full_name) AS name_or_organization,
                 laureates.date_of_birth, 
                 laureates.date_of_death, 
                 countries.country_name, 
                 prices.year, 
                 prices.category
          FROM laureates
          LEFT JOIN laureates_countries ON laureates.id = laureates_countries.laureate_id
          LEFT JOIN countries ON laureates_countries.country_id = countries.id
          LEFT JOIN laureate_prizes ON laureates.id = laureate_prizes.laureate_id
          LEFT JOIN prices ON laureate_prizes.prize_id = prices.id
          WHERE 1=1";

    // Фильтрация по году и категории
    if (!empty($yearFilter)) {
        $query .= " AND prices.year = :year";
    }
    if (!empty($categoryFilter)) {
        $query .= " AND prices.category = :category";
    }

    // Выполнение запроса
    $stmt = $db->prepare($query);
    if (!empty($yearFilter)) {
        $stmt->bindParam(':year', $yearFilter, PDO::PARAM_INT);
    }
    if (!empty($categoryFilter)) {
        $stmt->bindParam(':category', $categoryFilter, PDO::PARAM_STR);
    }

    $stmt->execute();
    $laureates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Отображение данных в таблице
    foreach ($laureates as $laureate): ?>
        <tr>
            <td>
                <a href="laureate.php?id=<?= htmlspecialchars($laureate['id'] ?? '') ?>">
                    <?= htmlspecialchars($laureate['name_or_organization'] ?? 'Unknown') ?>
                </a>
            </td>
            <?php if (empty($_GET['year'])): ?>
                <td><?= htmlspecialchars($laureate['year'] ?? '') ?></td>
            <?php endif; ?>
            <td><?= htmlspecialchars($laureate['country_name'] ?? '') ?></td>
            <?php if (empty($_GET['category'])): ?>
                <td><?= htmlspecialchars($laureate['category'] ?? '') ?></td>
            <?php endif; ?>
            <td>
                <a href="#" class="btn-edit" onclick="editLaureate(<?= $laureate['id'] ?>)">Edit</a>
                <a href="#" class="btn-delete" onclick="deleteLaureate(<?= $laureate['id'] ?>)">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<div id="createModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeCreateModal()">&times;</span>
        <h2>Create New Laureate</h2>
        <form id="createForm">
            <label for="newFullName">Full Name:</label>
            <input type="text" id="newFullName" name="full_name"><br><br>

            <label for="newSex">Sex:</label>
            <input type="text" id="newSex" name="sex"><br><br>

            <label for="newDob">Date of Birth:</label>
            <input type="text" id="newDob" name="date_of_birth" required><br><br>

            <label for="newDod">Date of Death:</label>
            <input type="text" id="newDod" name="date_of_death"><br><br>

            <label for="newOrganization">Organization:</label>
            <input type="text" id="newOrganization" name="organization"><br><br>

            <label for="newPrizeYear">Prize Year:</label>
            <input type="text" id="newPrizeYear" name="prize_year" required><br><br>

            <label for="newPrizeCategory">Category:</label>
            <input type="text" id="newPrizeCategory" name="prize_category" required><br><br>

            <label for="newCountry">Country:</label>
            <input type="text" id="newCountry" name="country_name" required><br><br>

            <button type="submit">Create</button>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Edit Laureate</h2>
        <form id="editForm">
            <!-- Форма для редактирования -->
            <label for="fullName">Full Name:</label>
            <input type="text" id="fullName" name="fullName" required><br><br>

            <label for="sex">Sex:</label>
            <input type="text" id="sex" name="sex"><br><br>

            <label for="dob">Date of Birth:</label>
            <input type="text" id="dob" name="dob" required><br><br>

            <label for="dod">Date of Death:</label>
            <input type="text" id="dod" name="dod"><br><br>

            <label for="organization">Organization:</label>
            <input type="text" id="organization" name="organization"><br><br>

            <label for="prizeYear">Year of Award:</label>
            <input type="text" id="prizeYear" name="prizeYear" required><br><br>

            <label for="editCategory">Category:</label>
            <input type="text" id="editCategory" name="editCategory" required><br><br>

            <label for="country">Country:</label>
            <input type="text" id="country" name="country" required><br><br>

            <button type="submit">Save</button>
        </form>
    </div>
</div>
<script>
    $(document).ready(function() {
        let table = $('#dataTable').DataTable({
            "paging": true,
            "ordering": true,
            "info": true,
            "searching": true,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
            "order": [[1, "desc"]]
        });

        let hideYear = <?php echo isset($_GET['year']) && $_GET['year'] !== '' ? 'true' : 'false'; ?>;
        let hideCategory = <?php echo isset($_GET['category']) && $_GET['category'] !== '' ? 'true' : 'false'; ?>;

        // Определяем изначальные индексы колонок
        let columnIndexYear = $('th:contains("Year")').index();
        let columnIndexCountry = $('th:contains("Country")').index();
        let columnIndexCategory = $('th:contains("Category")').index();

        if (hideYear && columnIndexYear !== -1) {
            table.column(columnIndexYear).visible(false);
        }
        if (hideCategory && columnIndexCategory !== -1) {
            table.column(columnIndexCategory).visible(false);
        }
    });

    function editLaureate(id) {
        fetch(`/api/api/v0/laureates/${id}`, {
            method: 'GET',
        })
            .then(response => response.json())
            .then(data => {
                console.log(data.prize_category);
                document.getElementById("fullName").value = data.full_name || '';
                document.getElementById("sex").value = data.sex || '';
                document.getElementById("dob").value = data.date_of_birth || '';
                document.getElementById("dod").value = data.date_of_death || '';
                document.getElementById("organization").value = data.organization || '';
                document.getElementById("prizeYear").value = data.prize_year || '';
                document.getElementById("editCategory").value = data.prize_category || '';
                document.getElementById("country").value = data.country_name || '';
                document.getElementById("editModal").style.display = "block";
                // Сохраняем ID в форме
                document.getElementById("editForm").dataset.id = id;
            })
            .catch(error => console.error('Error:', error));
    }


    document.getElementById("editForm").addEventListener("submit", function(event) {
        event.preventDefault();
        const id = this.dataset.id;
        const data = {
            full_name: document.getElementById("fullName").value,
            sex: document.getElementById("sex").value,
            date_of_birth: document.getElementById("dob").value,
            date_of_death: document.getElementById("dod").value,
            organization: document.getElementById("organization").value,
            prize_year: document.getElementById("prizeYear").value,
            prize_category: document.getElementById("editCategory").value,
            country_name: document.getElementById("country").value
        };

        fetch(`/api/api/v0/laureates/${id}`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
            .then(async response => response.json())
            .then(data => {
                if(data.message === "Updated successfully") {
                    location.reload();
                } else {
                    showMessage('Error: ' + (data.message || 'Update failed'));
                }
            })
            .catch(error => console.error('Error:', error));
    });

    document.getElementById("createForm").addEventListener("submit", function(event) {
        event.preventDefault();

        const fullNameRaw = document.getElementById("newFullName").value.trim();

        const data = {
            full_name: fullNameRaw,
            sex: document.getElementById("newSex").value,
            date_of_birth: document.getElementById("newDob").value,
            date_of_death: document.getElementById("newDod").value,
            oragnization: document.getElementById("newOrganization").value,
            prize_year: document.getElementById("newPrizeYear").value,
            prize_category: document.getElementById("newPrizeCategory").value,
            country_name: document.getElementById("newCountry").value
        };

        fetch('/api/api/v0/laureates', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
            .then(response => response.json())
            .then(json => {
                if (json.message === "Created successfully") {
                    showMessage("🎉 Laureate created!");
                    closeCreateModal();
                    location.reload(); // Обновим страницу
                } else {
                    showMessage("⚠️ Error: " + (json.message || "Something went wrong"));
                }
            })
            .catch(error => {
                console.error("❌ Error creating laureate:", error);
                showMessage("Error: " + error.message);
            });
    });


    // Закрытие модального окна
    function closeModal() {
        document.getElementById("editModal").style.display = "none";
    }

    function openCreateModal() {
        document.getElementById("createModal").style.display = "block";
    }

    function closeCreateModal() {
        document.getElementById("createModal").style.display = "none";
    }

    // Удаление лауреата
    function deleteLaureate(id) {
        fetch(`/api/api/v0/laureates/${id}`, {method: 'DELETE'})
            .then(response => response.json())
            .then(data => {
                if(data.message === "Deleted successfully") {
                    location.reload();
                } else {
                    showMessage('Error: ' + (data.message || 'Delete failed'), true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Error deleting laureate: ' + error.message, true);
            });
    }

    document.getElementById("bulkUploadForm").addEventListener("submit", function(event) {
        event.preventDefault();

        const formData = new FormData();
        const fileInput = document.getElementById("jsonFile");

        if (!fileInput.files.length) {
            showMessage("Please select a JSON file.");
            return;
        }

        formData.append("jsonFile", fileInput.files[0]);

        fetch("/api/api/v0/laureates/upload", {
            method: "POST",
            body: formData
        })
            .then(async (res) => {
                const contentType = res.headers.get("content-type") || "";

                // Пробуем распарсить JSON, если это реально JSON
                if (contentType.includes("application/json")) {
                    const json = await res.json();

                    if (res.ok && json.success) {
                        showMessage("🎉 Uploaded successfully: " + json.inserted + " laureates");
                        location.reload();
                    } else {
                        console.error("⚠️ Server responded with error JSON:", json);
                        showMessage("⚠️ Error: " + (json.message || "Unknown error"));
                    }

                } else {
                    // Сервер вернул не-JSON (например, HTML или warning)
                    const text = await res.text();
                    console.error("❌ Server returned non-JSON response:", text);
                    showMessage("Server error (non-JSON response): " + text);
                }
            })
            .catch((error) => {
                console.error("❌ Network or unexpected error:", error);
                showMessage("Error uploading JSON file. See console for details.");
            });
    });
    function showMessage(text, isError = false) {
        const box = document.getElementById("messageBox");
        box.style.color = isError ? "red" : "green";
        box.textContent = text;

        // Автоочистка через 5 секунд
        setTimeout(() => {
            box.textContent = "";
        }, 10000);
    }

</script>

</body>
</html>

