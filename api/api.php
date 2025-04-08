<?php

require_once ('../projekt1/config.php');
require_once('Laureate.class.php');

$pdo=connectDatabase($hostname, $database, $username, $password);
$laureate = new Laureate($pdo);

header("Content-Type: application/json");

//https://node111.webte.fei.stuba.sk/api/api/v0/laureates/
// POST, GET, PUT, DELETE - CRUD: Create, Read, Update, Delete

$method = $_SERVER['REQUEST_METHOD'];
$route = explode('/', $_GET['route']);
error_log(print_r($_FILES, true));

switch ($method) {
    // В методе 'GET' вы передаете запрос в show
    case 'GET':
        if ($route[0] == 'laureates' && count($route) == 1) {
            http_response_code(200);
            echo json_encode($laureate->index());  // Get all laureates
            break;
        } elseif ($route[0] == 'laureates' && count($route) == 2 && is_numeric($route[1])) {
            $id = $route[1];
            $data = $laureate->show($id);  // Get laureate with country and prize info
            if ($data) {
                http_response_code(200);
                echo json_encode($data);
                break;
            }
        }
        http_response_code(404);
        echo json_encode(['message' => 'Not found']);
        break;
    case 'POST':
        //  СНАЧАЛА ОБРАБОТКА ЗАГРУЗКИ JSON
        if ($route[0] == 'laureates' && isset($route[1]) && $route[1] == 'upload') {
            if (!isset($_FILES['jsonFile']) || $_FILES['jsonFile']['error'] !== UPLOAD_ERR_OK) {
                error_log("❌ JSON upload: file not received or error = " . $_FILES['jsonFile']['error']);
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'File upload error']);
                exit;
            }

            $jsonContent = file_get_contents($_FILES['jsonFile']['tmp_name']);
            $laureates = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("❌ JSON decode error: " . json_last_error_msg());
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid JSON format']);
                exit;
            }

            if (!is_array($laureates)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid JSON structure']);
                exit;
            }

            $inserted = 0;
            foreach ($laureates as $entry) {
                $result = $laureate->store(
                    $entry['sex'] ?? '',
                    $entry['date_of_birth'] ?? '',
                    $entry['date_of_death'] ?? '',
                    $entry['country_name'] ?? '',
                    $entry['prize_category'] ?? '',
                    $entry['prize_year'] ?? '',
                    $entry['full_name'] ?? null,
                    $entry['oragnization'] ?? null
                );

                if (is_numeric($result)) {
                    $inserted++;
                }
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Laureates uploaded',
                'inserted' => $inserted
            ]);
            exit;
        }

        //  Обычный POST создаёт одного лауреата
        if ($route[0] == 'laureates' && count($route) == 1) {
            $data = json_decode(file_get_contents('php://input'), true);
            if ($data === null) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid JSON data']);
                exit;
            }

            $newID = $laureate->store(
                $data['sex'],
                $data['date_of_birth'],
                $data['date_of_death'],
                $data['country_name'],
                $data['prize_category'],
                $data['prize_year'],
                $data['full_name'],
                $data['oragnization']
            );

            if (!is_numeric($newID)) {
                http_response_code(400);
                echo json_encode(['message' => "Bad request", 'data' => $newID]);
                exit;
            }

            $new_laureate = $laureate->show($newID);
            http_response_code(201);
            echo json_encode([
                'message' => "Created successfully",
                'data' => $new_laureate
            ]);
            exit;
        }

        //  Всё остальное — ошибка
        http_response_code(400);
        echo json_encode(['message' => 'Bad request']);
        break;
    case 'PUT':
        if ($route[0] == 'laureates' && count($route) == 2 && is_numeric($route[1])) {
            $currentID = $route[1];
            $currentData = $laureate->show($currentID);
            if (!$currentData) {
                http_response_code(404);
                echo json_encode(['message' => 'Not found']);
                break;
            }

            $updatedData = json_decode(file_get_contents('php://input'), true);
            $currentData = array_merge($currentData, $updatedData);

            $status = $laureate->update(
                $currentID,
                $currentData['sex'],
                $currentData['date_of_birth'],
                $currentData['date_of_death'],
                $currentData['full_name'],
                $currentData['oragnization'],
                $currentData['country_name'],
                $currentData['prize_year'],
                $currentData['prize_category']
            );

            if ($status != 0) {
                http_response_code(400);
                echo json_encode(['message' => "Bad request", 'data' => $status]);
                break;
            }

            http_response_code(201);
            echo json_encode([
                'message' => "Updated successfully",
                'data' => $currentData
            ]);
            break;
        }
        http_response_code(404);
        echo json_encode(['message' => 'Not found']);
        break;
    case 'DELETE':
        if ($route[0] == 'laureates' && count($route) == 2 && is_numeric($route[1])) {
            $id = $route[1];
            $exist = $laureate->show($id);
            if (!$exist) {
                http_response_code(404);
                echo json_encode(['message' => 'Not found']);
                break;
            }

            $status = $laureate->destroy($id);

            if ($status != 0) {
                http_response_code(400);
                echo json_encode(['message' => "Bad request", 'data' => $status]);
                break;
            }

            http_response_code(201);
            echo json_encode(['message' => "Deleted successfully"]);
            break;

        }
        http_response_code(404);
        echo json_encode(['message' => 'Not found']);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}