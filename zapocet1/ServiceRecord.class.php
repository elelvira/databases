<?php

class ServiceRecord
{
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($data) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO service_record (id, problem, solution, carId, mechanicId, serviceAt)
         VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $data['id'],
            $data['problem'],
            $data['solution'],
            $data['carId'],
            $data['mechanicId'] ?? null,
            $data['serviceAt']
        ]);
    }

    public function show($id) {
        $stmt = $this->pdo->prepare("
        SELECT s.*, c.brand, m.name AS mechanicName
        FROM service_record s
        LEFT JOIN car c ON s.carId = c.id
        LEFT JOIN mechanic m ON s.mechanicId = m.id
        WHERE s.id = ?
    ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}