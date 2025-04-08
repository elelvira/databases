<?php

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version= "1.0.0",
 *     title= "API Dokumentation",
 *     description= "Deskription removed for better illustration of structure.",
 *     @OA\Contact(
 *          email= "admin@example.test",
 *          name= "company",
 *          url= "https://example.test"
 *      ),
 *     @OA\License(
 *          name= "Apache 2.0",
 *          url= "http://www.apache.org/licenses/LICENSE-2.0.html"
 *     )
 * )
 */
/**
 * @OA\Schema(
 *   schema="Product",
 *   type="object",
 *   required={"full_name", "sex", "date_of_birth", "country_name", "prize_year", "prize_category"},
 *   @OA\Property(property="full_name", type="string"),
 *   @OA\Property(property="oragnization", type="string"),
 *   @OA\Property(property="sex", type="string"),
 *   @OA\Property(property="date_of_birth", type="string"),
 *   @OA\Property(property="date_of_death", type="string"),
 *   @OA\Property(property="country_name", type="string"),
 *   @OA\Property(property="prize_year", type="string"),
 *   @OA\Property(property="prize_category", type="string")
 * )
 */

class Laureate {

    private $db;
    private $id;
    private $full_name;
    private $oragnization;
    private $sex;
    private $date_of_birth;
    private $date_of_death;

    public function __construct($db) {
        $this->db = $db;
    }

    // Get all records
    public function index() {
        // Запрос для получения всех лауреатов с информацией о стране и призе
        $query = "
    SELECT 
        laureates.id, 
        laureates.full_name, 
        laureates.oragnization, 
        laureates.sex, 
        laureates.date_of_birth, 
        laureates.date_of_death,
        countries.country_name,
        prices.year AS prize_year,
        prices.category AS prize_category
    FROM laureates
    LEFT JOIN laureates_countries ON laureates.id = laureates_countries.laureate_id
    LEFT JOIN countries ON laureates_countries.country_id = countries.id
    LEFT JOIN laureate_prizes ON laureates.id = laureate_prizes.laureate_id
    LEFT JOIN prices ON laureate_prizes.prize_id = prices.id
    ";

        // Подготовка запроса
        $stmt = $this->db->prepare($query);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Возвращаем все записи в виде массива
    }

    /**
     * Get a product.
     *
     * @param ?int $id the product id
     */
    /**
     * @OA\Get(
     *     path="/laureates/{id}",
     *     tags={"laureates"},
     *     operationId="showLaureate",
     *     summary="Get laureate by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the laureate to return",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Product"),
     *         @OA\Header(
     *             header="X-Rate-Limit",
     *             description="calls per hour allowed by the user",
     *             @OA\Schema(type="integer", format="int32")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="oops, not found"
     *     )
     * )
     */

    // Get one record
    // Получить один запись о лауреате
    public function show($id = null, $full_name = null, $oragnization = null) {
        // Улучшенный SQL запрос, который соединяет все таблицы
        $query = "
    SELECT 
        laureates.id, 
        laureates.full_name, 
        laureates.oragnization, 
        laureates.sex, 
        laureates.date_of_birth, 
        laureates.date_of_death,
        countries.country_name,
        prices.year AS prize_year,
        prices.category AS prize_category
    FROM laureates
    LEFT JOIN laureates_countries ON laureates.id = laureates_countries.laureate_id
    LEFT JOIN countries ON laureates_countries.country_id = countries.id
    LEFT JOIN laureate_prizes ON laureates.id = laureate_prizes.laureate_id
    LEFT JOIN prices ON laureate_prizes.prize_id = prices.id
    WHERE 1=1
    ";

        if ($id) {
            $query .= " AND laureates.id = :id";
        }
        if ($full_name) {
            $query .= " AND laureates.full_name LIKE :full_name";
        }
        if ($oragnization) {
            $query .= " AND laureates.oragnization LIKE :oragnization";
        }

        // Подготовка запроса
        $stmt = $this->db->prepare($query);

        if ($id) {
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        }
        if ($full_name) {
            $stmt->bindParam(':full_name', $full_name, PDO::PARAM_STR);
        }
        if ($oragnization) {
            $stmt->bindParam(':oragnization', $oragnization, PDO::PARAM_STR);
        }

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }

        return $stmt->fetch(PDO::FETCH_ASSOC); // Возвращаем ассоциативный массив с результатами
    }

    /**
     * @OA\Post(
     *     path="/laureates",
     *     tags={"laureates"},
     *     operationId="storeLaureate",
     *     summary="Add products",
     *     @OA\RequestBody(
     *         required=true,
     *         description="New product",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Product")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     )
     * )
     */

    // Create a new record
    public function store($sex, $date_of_birth, $date_of_death, $country_name, $prize_category, $prize_year, $full_name = null, $oragnization = null) {
        $full_name = $full_name ?? '';
        $oragnization = $oragnization ?? '';
        if (trim($full_name) === '' && trim($oragnization) === '') {
            return "Error: Either full name or organization is required.";
        }

        if (trim($full_name) !== '') {
            $query = "SELECT COUNT(*) FROM laureates WHERE full_name = :full_name";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':full_name', $full_name);
        } elseif (trim($oragnization) !== '') {
            $query = "SELECT COUNT(*) FROM laureates WHERE oragnization = :oragnization";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':oragnization', $oragnization);
        } else {
            return "Error: Either full name or organization is required.";
        }

//        // Проверяем, существует ли лауреат с таким именем
//        $query = "SELECT COUNT(*) FROM laureates WHERE full_name = :full_name";
//        $stmt = $this->db->prepare($query);
//        $stmt->bindParam(':full_name', $full_name, PDO::PARAM_STR);

        try {
            $stmt->execute();
            $count = $stmt->fetchColumn();

            // Если лауреат уже существует, возвращаем ошибку
            if ($count > 0) {
                return "Error: Laureate with this name already exists.";
            }
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }

        // Если лауреат не найден, продолжаем добавление
        $stmt = $this->db->prepare("INSERT INTO laureates (full_name, oragnization, sex, date_of_birth, date_of_death) 
    VALUES (:full_name, :oragnization, :sex, :date_of_birth, :date_of_death)");

        $stmt->bindParam(':full_name', $full_name, PDO::PARAM_STR);
        $stmt->bindParam(':oragnization', $oragnization, PDO::PARAM_STR);
        $stmt->bindParam(':sex', $sex, PDO::PARAM_STR);
        $stmt->bindParam(':date_of_birth', $date_of_birth, PDO::PARAM_INT);
        $stmt->bindParam(':date_of_death', $date_of_death, PDO::PARAM_INT);
        try {
            $stmt->execute();
            $lastInsertId = $this->db->lastInsertId();
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }

        if ($country_name) {
            // Проверяем, есть ли такая страна
            $stmt = $this->db->prepare("SELECT id FROM countries WHERE country_name = :country_name");
            $stmt->bindParam(':country_name', $country_name, PDO::PARAM_STR);
            $stmt->execute();
            $country = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$country) {
                $stmt = $this->db->prepare("INSERT INTO countries (country_name) VALUES (:country_name)");
                $stmt->bindParam(':country_name', $country_name, PDO::PARAM_STR);
                $stmt->execute();
                $country_id = $this->db->lastInsertId();
            } else {
                $country_id = $country['id'];
            }

            // Привязываем страну к лауреату
            $stmt = $this->db->prepare("INSERT INTO laureates_countries (laureate_id, country_id) VALUES (:laureate_id, :country_id)");
            $stmt->bindParam(':laureate_id', $lastInsertId, PDO::PARAM_INT);
            $stmt->bindParam(':country_id', $country_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        if ($prize_category && $prize_year) {
            $contrib_sk = "Not specified";
            $contrib_en = "Not specified";

            $stmt = $this->db->prepare("INSERT INTO prices (category, year, contrib_sk, contrib_en) 
                                VALUES (:category, :year, :contrib_sk, :contrib_en)");
            $stmt->bindParam(':category', $prize_category, PDO::PARAM_STR);
            $stmt->bindParam(':year', $prize_year, PDO::PARAM_INT);
            $stmt->bindParam(':contrib_sk', $contrib_sk, PDO::PARAM_STR);
            $stmt->bindParam(':contrib_en', $contrib_en, PDO::PARAM_STR);
            $stmt->execute();
            $prize_id = $this->db->lastInsertId();

            $stmt = $this->db->prepare("INSERT INTO laureate_prizes (laureate_id, prize_id) VALUES (:laureate_id, :prize_id)");
            $stmt->bindParam(':laureate_id', $lastInsertId, PDO::PARAM_INT);
            $stmt->bindParam(':prize_id', $prize_id, PDO::PARAM_INT);
            $stmt->execute();
        }


        return $lastInsertId;
    }

    /**
     * @OA\Put(
     *     path="/laureates/{id}",
     *     tags={"laureates"},
     *     summary="Update laureate by ID",
     *     operationId="updateLaureate",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the laureate to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Laureate fields to update",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="full_name", type="string"),
     *             @OA\Property(property="oragnization", type="string"),
     *             @OA\Property(property="sex", type="string"),
     *             @OA\Property(property="date_of_birth", type="string"),
     *             @OA\Property(property="date_of_death", type="string"),
     *             @OA\Property(property="country_name", type="string"),
     *             @OA\Property(property="prize_category", type="string"),
     *             @OA\Property(property="prize_year", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     )
     * )
     */

    // Update a record
    public function update($id, $sex, $date_of_birth, $date_of_death, $full_name = null, $oragnization = null,$country_name=null, $prize_year=null, $prize_category=null) {
        // Начинаем с базового запроса
        $query = "UPDATE laureates SET sex = :sex, date_of_birth = :date_of_birth, date_of_death = :date_of_death";

        // Добавляем параметры только если они переданы
        $params = [
            ':sex' => $sex,
            ':date_of_birth' => $date_of_birth,
            ':date_of_death' => $date_of_death,
        ];

        if ($full_name !== null) {
            $query .= ", full_name = :full_name";
            $params[':full_name'] = $full_name;
        }

        if ($oragnization !== null) {
            $query .= ", oragnization = :oragnization";
            $params[':oragnization'] = $oragnization;
        }

        // Добавляем условие WHERE
        $query .= " WHERE id = :id";
        $params[':id'] = $id;

        // Подготовка запроса
        $stmt = $this->db->prepare($query);

        try {
            // Привязка всех параметров
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR); // Все параметры как строки
            }

            // Выполнение запроса
            $stmt->execute();
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }

        if ($country_name !== null) {
            // Сначала получаем ID страны по названию
            $stmt = $this->db->prepare("SELECT id FROM countries WHERE country_name = :country_name");
            $stmt->bindParam(':country_name', $country_name, PDO::PARAM_STR);
            $stmt->execute();
            $country = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($country) {
                // Обновляем таблицу связи
                $stmt = $this->db->prepare("UPDATE laureates_countries SET country_id = :country_id WHERE laureate_id = :laureate_id");
                $stmt->bindParam(':laureate_id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':country_id', $country['id'], PDO::PARAM_INT);
                try {
                    $stmt->execute();
                } catch (PDOException $e) {
                    return "Error: " . $e->getMessage();
                }
            }
        }

        if ($prize_year !== null || $prize_category !== null) {
            $stmt = $this->db->prepare("SELECT prize_id FROM laureate_prizes WHERE laureate_id = :laureate_id");
            $stmt->bindParam(':laureate_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && isset($result['prize_id'])) {
                $prize_id = $result['prize_id'];

                $query = "UPDATE prices SET year = :year, category = :category WHERE id = :prize_id";
                $stmt = $this->db->prepare($query);

                $cleanYear = substr(trim($prize_year), 0, 4); // Очищаем!

                $stmt->bindValue(':year', $cleanYear, PDO::PARAM_STR);
                $stmt->bindValue(':category', $prize_category, PDO::PARAM_STR);
                $stmt->bindValue(':prize_id', $prize_id, PDO::PARAM_INT);

                try {
                    $stmt->execute();
                } catch (PDOException $e) {
                    return "Error updating prize: " . $e->getMessage();
                }
            }
        }


        return 0; // Возвращаем 0 в случае успеха
    }

    /**
     * @OA\Delete(
     *     path="/laureates/{id}",
     *     tags={"laureates"},
     *     summary="Delete laureate by ID",
     *     operationId="deleteLaureate",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the laureate to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     )
     * )
     */

    // Delete a record
    public function destroy($id) {
        // Удаляем все связанные записи в таблице laureates_countries
        $stmt = $this->db->prepare("DELETE FROM laureates_countries WHERE laureate_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }

        // Удаляем все связанные записи в таблице laureate_prizes
        $stmt = $this->db->prepare("DELETE FROM laureate_prizes WHERE laureate_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }

        // Удаляем лауреата
        $stmt = $this->db->prepare("DELETE FROM laureates WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }

        return 0;
    }


//    public function storeMultiple($laureates) {
//        $this->db->beginTransaction();
//
//        try {
//            $stmt = $this->db->prepare("INSERT INTO laureates (full_name, oragnization, sex, date_of_birth, date_of_death)
//            VALUES (:full_name, :oragnization, :sex, :date_of_birth, :date_of_death)");
//
//            foreach ($laureates as $laureate) {
//                $stmt->bindParam(':full_name', $laureate['full_name'], PDO::PARAM_STR);
//                $stmt->bindParam(':oragnization', $laureate['oragnization'], PDO::PARAM_STR);
//                $stmt->bindParam(':sex', $laureate['sex'], PDO::PARAM_STR);
//                $stmt->bindParam(':date_of_birth', $laureate['date_of_birth'], PDO::PARAM_INT);
//                $stmt->bindParam(':date_of_death', $laureate['date_of_death'], PDO::PARAM_INT);
//                $stmt->execute();
//            }
//
//            $this->db->commit();
//        } catch (PDOException $e) {
//            $this->db->rollBack();
//            return "Error: " . $e->getMessage();
//        }
//
//        return "All laureates inserted successfully!";
//    }

//    public function storeWithPrize($laureateData, $prizeData) {
//        $this->db->beginTransaction();
//
//        try {
//            // Insert laureate
//            $stmt = $this->db->prepare("INSERT INTO laureates (full_name, oragnization, sex, date_of_birth, date_of_death)
//            VALUES (:full_name, :oragnization, :sex, :date_of_birth, :date_of_death)");
//
//            $stmt->bindParam(':full_name', $laureateData['full_name'], PDO::PARAM_STR);
//            $stmt->bindParam(':oragnization', $laureateData['oragnization'], PDO::PARAM_STR);
//            $stmt->bindParam(':sex', $laureateData['sex'], PDO::PARAM_STR);
//            $stmt->bindParam(':date_of_birth', $laureateData['date_of_birth'], PDO::PARAM_INT);
//            $stmt->bindParam(':date_of_death', $laureateData['date_of_death'], PDO::PARAM_INT);
//            $stmt->execute();
//
//            $laureateId = $this->db->lastInsertId();
//
//            // Insert prize
//            $stmt = $this->db->prepare("INSERT INTO laureate_prizes (laureate_id, prize_id) VALUES (:laureate_id, :prize_id)");
//
//            foreach ($prizeData as $prize) {
//                $stmt->bindParam(':laureate_id', $laureateId, PDO::PARAM_INT);
//                $stmt->bindParam(':prize_id', $prize['prize_id'], PDO::PARAM_INT);
//                $stmt->execute();
//            }
//
//            $this->db->commit();
//        } catch (PDOException $e) {
//            $this->db->rollBack();
//            return "Error: " . $e->getMessage();
//        }
//
//        return "Laureate and prize inserted successfully!";
//    }

}