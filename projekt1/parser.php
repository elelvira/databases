<?php

function parseCSV($filename)
{
    $handle = fopen($filename, 'r');
    $data = array();
    while (($row = fgetcsv($handle, 0, ",")) !== FALSE) {
        $data[] = array_filter($row);
    }
    fclose($handle);
    unset($data[0]);
    return $data;
}

//$laureates = parseCSV('nobel_v5_FYZ.csv');
$laureates = array_merge(
    parseCSV('nobel_v5_FYZ.csv','Physics'),
    parseCSV('nobel_v5_LIT.csv', 'Literature'),
    parseCSV('nobel_v5_MED.csv', 'Medicine'),
    parseCSV('nobel_v5_MIER.csv', 'Economics'),
    parseCSV('nobel_v5_CHEM.csv', 'Chemistry')
);
echo "<pre>";
print_r($laureates);
echo "</pre>";

function getLaureantCountry($db)
{
    $stmt= $db->prepare("SELECT laureates.full_name, laureates.sex, laureates.date_of_birth, 
    laureates.date_of_death, countries.country_name  FROM laureates 
    LEFT JOIN laureates_countries 
            INNER JOIN countries 
            ON laureates_countries.country_id = countries.id
    ON laureates.id=laureates_countries.laureate_id");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result;
}
