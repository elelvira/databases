<?php
class Car {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function index() {
        $stmt = $this->pdo->query("
        SELECT car.id, car.brand, car.carType, 
               sr.problem, sr.solution, sr.serviceAt, 
               m.name AS mechanicName
        FROM car
        LEFT JOIN service_record sr ON car.id = sr.carId
        LEFT JOIN mechanic m ON sr.mechanicId = m.id
    ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function show($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM car WHERE id = ?");
        $stmt->execute([$id]);
        $car = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($car) {
            $stmt = $this->pdo->prepare("SELECT * FROM service_record WHERE carId = ?");
            $stmt->execute([$id]);
            $car['serviceRecords'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $car;
    }

    public function create($data) {
        // 1. Создаём машину
        $stmt = $this->pdo->prepare("INSERT INTO car (brand, carType) VALUES (?, ?)");
        $stmt->execute([$data['brand'], $data['carType']]);
        $carId = $this->pdo->lastInsertId();

        // 2. Добавляем сервисные записи
        if (!empty($data['serviceRecords'])) {
            foreach ($data['serviceRecords'] as $record) {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO service_record (id, problem, solution, carId, mechanicId, serviceAt)
                 VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $record['id'],
                    $record['problem'],
                    $record['solution'],
                    $carId,
                    $record['mechanicId'] ?? null,
                    $record['serviceAt']
                ]);
            }
        }

        return $this->show($carId); // возвращаем машину с сервисами
    }

    public function delete($id) {
        // delete service records first (FK constraint)
        $stmt = $this->pdo->prepare("DELETE FROM service_record WHERE carId = ?");
        $stmt->execute([$id]);

        $stmt = $this->pdo->prepare("DELETE FROM car WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
