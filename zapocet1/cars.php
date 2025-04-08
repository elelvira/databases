<?php

require_once('../projekt1/config.php');
require_once('Car.class.php');
require_once('ServiceRecord.class.php');
require_once ('Mechanic.class.php');

$pdo = connectDatabase($hostname, $database, $username, $password);
$car = new Car($pdo);
$service = new ServiceRecord($pdo);
$mechanic = new Mechanic($pdo);

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$route = explode('/', $_GET['route']);

switch ($method) {

    // 1. GET /cars
    case 'GET':
        if ($route[0] == 'cars' && count($route) == 1) {
            echo json_encode($car->index());
            http_response_code(200);
            break;
        }

        // 2. GET /cars/{id}
        if ($route[0] == 'cars' && count($route) == 2 && is_numeric($route[1])) {
            $id = $route[1];
            $result = $car->show($id);
            if ($result) {
                echo json_encode($result);
                http_response_code(200);
                break;
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Car not found']);
                break;
            }
        }

        if ($route[0] == 'serviceRecords' && count($route) == 1) {
            echo json_encode($service->index());
            http_response_code(200);
            break;
        }

        if ($route[0] == 'serviceRecords' && count($route) == 2) {
            $result = $service->show($route[1]);
            echo json_encode($result ?: ['message' => 'Not found']);
            http_response_code($result ? 200 : 404);
            break;
        }

        http_response_code(404);
        echo json_encode(['message' => 'Not found']);
        break;

    // 3. POST /cars
    case 'POST':
        if ($route[0] == 'cars' && count($route) == 1) {
            $data = json_decode(file_get_contents("php://input"), true);
            $createdCar = $car->create($data);
            http_response_code(201);
            echo json_encode($createdCar);
            break;
        }

        // 4. POST /cars/{id}/service
        if ($route[0] == 'cars' && count($route) == 3 && is_numeric($route[1]) && $route[2] == 'service') {
            $carId = $route[1];
            $data = json_decode(file_get_contents("php://input"), true);
            $data['carId'] = $carId;
            $createdService = $service->create($data);
            http_response_code(204); // No content
            break;
        }

        if ($route[0] == 'mechanics' && count($route) == 1) {
            $data = json_decode(file_get_contents("php://input"), true);
            $createdMechanic = $mechanic->create($data);
            http_response_code(201);
            echo json_encode($createdMechanic);
            break;
        }
        http_response_code(400);
        echo json_encode(['message' => 'Bad request']);
        break;

    // 5. DELETE /cars/{id}
    case 'DELETE':
        if ($route[0] == 'cars' && count($route) == 2 && is_numeric($route[1])) {
            $id = $route[1];
            $deleted = $car->delete($id);
            if ($deleted) {
                http_response_code(200);
                echo json_encode(['message' => 'Car deleted']);
                break;
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Car not found']);
                break;
            }
        }

        // DELETE /mechanics/{id}
        if ($route[0] == 'mechanics' && count($route) == 2 && is_numeric($route[1])) {
            $id = $route[1];

            // найти carId, привязанный к этому механику через service_record
            $stmt = $pdo->prepare("SELECT DISTINCT carId FROM service_record WHERE mechanicId = ?");
            $stmt->execute([$id]);
            $carIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // удалить записи сервисов
            $stmt = $pdo->prepare("DELETE FROM service_record WHERE mechanicId = ?");
            $stmt->execute([$id]);

            // удалить машины (если есть)
            foreach ($carIds as $carId) {
                $car->delete($carId);
            }

            // удалить самого механика
            $stmt = $pdo->prepare("DELETE FROM mechanic WHERE id = ?");
            $stmt->execute([$id]);

            http_response_code(200);
            echo json_encode(['message' => 'Mechanic and related records deleted']);
            break;
        }

        http_response_code(400);
        echo json_encode(['message' => 'Bad request']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method Not Allowed']);
}

