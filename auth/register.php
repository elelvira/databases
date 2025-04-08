<?php

session_start();

// Если пользователь уже вошел, перенаправляем его
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

require_once '../projekt1/config.php';
require_once 'vendor/autoload.php';
require_once 'utilities.php';
$pdo = connectDatabase($hostname, $database, $username, $password);

use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

$errors = [];
$reg_status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    var_dump($_POST['email']); // Проверяем, какой email приходит

    // Валидация email
    if (!isset($_POST['email']) || trim($_POST['email']) === '' || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Neplatný formát e-mailu.";
    }

    // Проверяем, существует ли уже такой email
    if (userExist($pdo, $_POST['email']) === true) {
        $errors['email'] = "Používateľ s týmto e-mailom už existuje.";
    }

    // Валидация имени и фамилии (длина + только буквы)
    if (empty($_POST['firstname']) || !preg_match("/^[a-zA-ZáčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ-]{2,30}$/", $_POST['firstname'])) {
        $errors['firstname'] = "Meno môže obsahovať len písmená (2-30 znakov).";
    }
    if (empty($_POST['lastname']) || !preg_match("/^[a-zA-ZáčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ-]{2,30}$/", $_POST['lastname'])) {
        $errors['lastname'] = "Priezvisko môže obsahovať len písmená (2-30 znakov).";
    }

    // Валидация пароля (минимум 8 символов, хотя бы одна цифра)
    if (empty($_POST['password']) || strlen($_POST['password']) < 8 || !preg_match('/[0-9]/', $_POST['password'])) {
        $errors['password'] = "Heslo musí mať aspoň 8 znakov a obsahovať číslo.";
    }

    // Проверка повторного пароля
    if ($_POST['password'] !== $_POST['password_repeat']) {
        $errors['password_repeat'] = "Heslá sa nezhodujú.";
    }

    // Если ошибок нет, создаем пользователя
    if (empty($errors)) {
        $sql = "INSERT INTO users (fullname, email, password, 2fa_code) VALUES (:fullname, :email, :password, :2fa_code)";

        $fullname = trim($_POST['firstname']) . ' ' . trim($_POST['lastname']);
        $email = trim($_POST['email']);
        $pw_hash = password_hash($_POST['password'], PASSWORD_ARGON2ID);

        $tfa = new TwoFactorAuth(new EndroidQrCodeProvider());
        $user_secret = $tfa->createSecret();
        $qr_code = $tfa->getQRCodeImageAsDataUri('Nobel Prizes', $user_secret);

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":fullname", $fullname, PDO::PARAM_STR);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":password", $pw_hash, PDO::PARAM_STR);
        $stmt->bindParam(":2fa_code", $user_secret, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $reg_status = "Registrácia prebehla úspešne.";
        } else {
            $errors['general'] = "Ups. Niečo sa pokazilo...";
        }
    }
    unset($stmt);
    unset($pdo);
}
?>

<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrácia</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to right, #4facfe, #00f2fe);
            text-align: center;
            padding: 20px;
        }
        .container {
            background: white;
            width: 400px;
            padding: 20px;
            margin: auto;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        input, button {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
        }
        button {
            background: #4facfe;
            color: white;
            cursor: pointer;
        }
        button:hover {
            background: #007bff;
        }
        .error {
            color: red;
            font-size: 14px;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .qr-container {
            margin-top: 20px;
        }
    </style>
</head>

<body>
<div class="container">
    <h2>Registrácia</h2>
    <p>Vytvorte si nový účet</p>

    <?php if ($reg_status): ?>
        <p class="success"><?= $reg_status ?></p>
    <?php endif; ?>

    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" onsubmit="return validateForm()">
        <label>Meno:</label>
        <input type="text" name="firstname" id="firstname">
        <div class="error" id="firstname_error"><?= $errors['firstname'] ?? '' ?></div>

        <label>Priezvisko:</label>
        <input type="text" name="lastname" id="lastname">
        <div class="error" id="lastname_error"><?= $errors['lastname'] ?? '' ?></div>

        <label>E-mail:</label>
        <input type="email" name="email" id="email" required>
        <div class="error" id="email_error"><?= $errors['email'] ?? '' ?></div>

        <label>Heslo:</label>
        <input type="password" name="password" id="password">
        <div class="error" id="password_error"><?= $errors['password'] ?? '' ?></div>

        <label>Zopakujte heslo:</label>
        <input type="password" name="password_repeat" id="password_repeat">
        <div class="error" id="password_repeat_error"><?= $errors['password_repeat'] ?? '' ?></div>

        <button type="submit">Vytvoriť konto</button>
    </form>

    <?php if (isset($qr_code)): ?>
        <div class="qr-container">
            <p>2FA kód: <?= $user_secret ?></p>
            <p>Naskenujte QR kód:</p>
            <img src="<?= $qr_code ?>" alt="QR kód pre autentifikáciu">
        </div>
    <?php endif; ?>

    <p>Máte účet? <a href="login.php">Prihláste sa</a></p>
</div>

<script>
    function validateForm() {
        let valid = true;
        document.querySelectorAll('.error').forEach(el => el.innerText = '');

        let email = document.getElementById('email').value;
        let emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

        if (!emailPattern.test(email)) {
            document.getElementById('email_error').innerText = 'Neplatný e-mail.';
            valid = false;
        }

        return valid;
    }
</script>

</body>
</html>
