<?php
require_once "../projekt1/config.php";
$pdo = connectDatabase($hostname, $database, $username, $password);

$errors = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);

    if (empty($email)) {
        $errors = "E-mail nemôže byť prázdny.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors = "Neplatná e-mailová adresa. Skúste znova.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $token = bin2hex(random_bytes(32)); // Генерируем токен
            $stmt = $pdo->prepare("UPDATE users SET reset_token = :token, reset_expiry = NOW() + INTERVAL 1 HOUR WHERE email = :email");
            $stmt->bindParam(":token", $token, PDO::PARAM_STR);
            $stmt->bindParam(":email", $email, PDO::PARAM_STR);
            $stmt->execute();

            // Отправляем письмо с ссылкой для сброса пароля
            $reset_link = "https://node111.webte.fei.stuba.sk/reset_password.php?token=" . $token;
            $to = $email;
            $subject = "Obnova hesla";
            $message = "Kliknite na nasledujúci odkaz pre resetovanie vášho hesla: " . $reset_link;
            $headers = "From: no-reply@webte.sk";

            if (mail($to, $subject, $message, $headers)) {
                $success = "Odkaz na obnovenie hesla bol odoslaný na váš email.";
            } else {
                $errors = "Nepodarilo sa odoslať e-mail. Skúste neskôr.";
            }
        } else {
            $errors = "E-mailová adresa nebola nájdená.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zabudnuté heslo</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: linear-gradient(to right, #4facfe, #00f2fe);
            color: #333;
        }

        .reset-container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 400px;
        }

        h1 {
            font-size: 24px;
        }

        .error {
            color: red;
            font-weight: bold;
        }

        .success {
            color: green;
            font-weight: bold;
        }

        input, button {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            background: #4facfe;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #007bff;
        }
    </style>
</head>
<body>

<div class="reset-container">
    <h1>Obnova hesla</h1>
    <p>Zadajte svoju e-mailovú adresu a my vám pošleme odkaz na obnovenie hesla.</p>

    <?php if (!empty($errors)): ?>
        <div class="error"><?php echo $errors; ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php else: ?>
        <form action="forgot_password.php" method="post">
            <input type="email" name="email" placeholder="Váš e-mail" required>
            <button type="submit">Odoslať odkaz</button>
        </form>
    <?php endif; ?>

    <p><a href="login.php">Späť na prihlásenie</a></p>
</div>

</body>
</html>
