<?php

session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'vendor/autoload.php';
require_once '../projekt1/config.php';

use Google\Client;

$pdo = connectDatabase($hostname, $database, $username, $password);
$client = new Client();
$client->setAuthConfig('../../client_secret.json');

if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
    $client->setAccessToken($_SESSION['access_token']);
    $oauth = new Google\Service\Oauth2($client);
    $account_info = $oauth->userinfo->get();

    $_SESSION['fullname'] = $account_info->name;
    $_SESSION['gid'] = $account_info->id;
    $_SESSION['email'] = $account_info->email;
}

$message = "";

// Отключение 2FA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['disable_2fa'])) {
    $_SESSION['2fa_disabled'] = true;
    $message = "Dvojfaktorová autentifikácia bola dočasne vypnutá.";
}

// Сброс пароля
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    header("Location: forgot_password.php");
    exit;
}

// Изменение имени
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_name'])) {
    if (isset($_SESSION['gid'])) {
        $message = "Nemôžete zmeniť meno, pretože ste prihlásený cez Google účet.";
    } else {
        $new_name = trim($_POST['new_name']);

        // Проверяем, что введены два слова (имя и фамилия)
        if (empty($new_name) || !preg_match("/^[a-zA-ZáčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ-]{2,30} [a-zA-ZáčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ-]{2,30}$/", $new_name)) {
            $message = "Neplatný formát mena. Použite meno a priezvisko.";
        } else {
            $sql = "UPDATE users SET fullname = :fullname WHERE email = :email";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(":fullname", $new_name, PDO::PARAM_STR);
            $stmt->bindParam(":email", $_SESSION['email'], PDO::PARAM_STR);

            if ($stmt->execute()) {
                $_SESSION['fullname'] = $new_name;
                $message = "Meno bolo úspešne zmenené.";
            } else {
                $message = "Chyba pri zmene mena.";
            }
        }
    }
}

// Изменение пароля
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    if (isset($_SESSION['gid'])) {
        $message = "Nemôžete zmeniť heslo, pretože ste prihlásený cez Google účet.";
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = "Všetky polia sú povinné.";
        } elseif ($new_password !== $confirm_password) {
            $message = "Heslá sa nezhodujú.";
        } elseif (strlen($new_password) < 8 || !preg_match('/[0-9]/', $new_password)) {
            $message = "Heslo musí mať aspoň 8 znakov a obsahovať číslo.";
        } else {
            $sql = "SELECT password FROM users WHERE email = :email";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(":email", $_SESSION['email'], PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && password_verify($current_password, $row['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_ARGON2ID);
                $sql = "UPDATE users SET password = :password WHERE email = :email";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(":password", $hashed_password, PDO::PARAM_STR);
                $stmt->bindParam(":email", $_SESSION['email'], PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $message = "Heslo bolo úspešne zmenené.";
                } else {
                    $message = "Chyba pri zmene hesla.";
                }
            } else {
                $message = "Aktuálne heslo nie je správne.";
            }
        }
    }
}

// История входов
$logins = [];
$sql = "SELECT login_time, user_id, login_type FROM users_login WHERE email = :email ORDER BY login_time DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(":email", $_SESSION['email'], PDO::PARAM_STR);
$stmt->execute();
$logins = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zabezpečená stránka</title>

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
        h2 {
            color: #333;
        }
        .message {
            color: green;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            margin: 10px 5px;
            padding: 10px;
            background: #4facfe;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            border: none;
        }
        .btn:hover {
            background: #007bff;
        }
        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
        }
    </style>
</head>

<body>
<div class="container">
    <h2>Zabezpečená stránka</h2>
    <p>Obsah tejto stránky je dostupný len po prihlásení.</p>

    <?php if (!empty($message)): ?>
        <p class="message"><?= $message; ?></p>
    <?php endif; ?>

    <h3>História prihlásenia</h3>
    <ul>
        <?php foreach ($logins as $login): ?>
            <li><?= $login['login_time']; ?> - <?= $login['user_id']; ?> (<?= $login['login_type']; ?>)</li>
        <?php endforeach; ?>
    </ul>

    <h3>Zmena mena</h3>
    <form method="post">
        <input type="text" name="new_name" placeholder="Nové meno a priezvisko" required>
        <button type="submit" name="change_name" class="btn">Zmeniť meno</button>
    </form>

    <h3>Zmena hesla</h3>
    <form method="post">
        <input type="password" name="current_password" placeholder="Aktuálne heslo" required>
        <input type="password" name="new_password" placeholder="Nové heslo" required>
        <input type="password" name="confirm_password" placeholder="Potvrdiť nové heslo" required>
        <button type="submit" name="change_password" class="btn">Zmeniť heslo</button>
    </form>

    <h3>Dvojfaktorová autentifikácia</h3>
    <form method="post">
        <button type="submit" name="disable_2fa" class="btn">Vypnúť 2FA</button>
    </form>

    <p><a href="logout.php" class="btn">Odhlásenie</a> </p>
</div>
</body>

</html>
