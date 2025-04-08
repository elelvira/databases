<?php
class Mechanic {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($data) {
        $stmt = $this->pdo->prepare("INSERT INTO mechanic (name, experienceYears) VALUES (?, ?)");
        $stmt->execute([
            $data['name'],
            $data['experienceYears'] ?? null
        ]);
        $id = $this->pdo->lastInsertId();
        return $this->show($id);
    }

    public function show($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM mechanic WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

