<?php
session_start();

// Проверка, авторизован ли пользователь
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

require_once "../projekt1/config.php";
require_once 'vendor/autoload.php';
require_once 'utilities.php';

$pdo = connectDatabase($hostname, $database, $username, $password);

use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

$redirect_uri = "https://node111.webte.fei.stuba.sk/auth/oauth2callback.php";

$errors = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['accept_cookies'])) {
        $errors = "Musíte prijať cookies, aby ste sa mohli prihlásiť.";
    } else {
        $sql = "SELECT id, fullname, email, password, 2fa_code, created_at FROM users WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":email", $_POST["email"], PDO::PARAM_STR);

        if ($stmt->execute()) {
            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch();
                $hashed_password = $row["password"];

                if (password_verify($_POST['password'], $hashed_password)) {
                    $tfa = new TwoFactorAuth(new EndroidQrCodeProvider());

                    if ($tfa->verifyCode($row["2fa_code"], $_POST['2fa'], 2)) {
                        $_SESSION["loggedin"] = true;
                        $_SESSION["fullname"] = $row['fullname'];
                        $_SESSION["email"] = $row['email'];
                        $_SESSION["created_at"] = $row['created_at'];

                        setcookie("user_accepted_cookies", "true", time() + (86400 * 30), "/"); // 30 дней

                        // Вставка данных о входе в users_login
                        $sql = "INSERT INTO users_login (user_id, email, fullname, login_type, login_time) 
                                    VALUES (:user_id, :email, :fullname, :login_type, NOW())";
                        $stmt = $pdo->prepare($sql);
                        $stmt->bindParam(":user_id", $row["id"], PDO::PARAM_INT);
                        $stmt->bindParam(":email", $row["email"], PDO::PARAM_STR);
                        $stmt->bindParam(":fullname", $row["fullname"], PDO::PARAM_STR);
                        $login_type = isset($_POST['2fa']) ? "2FA" : "password";
                        $stmt->bindParam(":login_type", $login_type, PDO::PARAM_STR);
                        $stmt->execute();

                        header("location: restricted.php");
                    } else {
                        $errors = "Neplatný 2FA kód.";
                    }
                } else {
                    $errors = "Nesprávne meno alebo heslo.";
                }
            } else {
                $errors = "Nesprávne meno alebo heslo.";
            }
        } else {
            $errors = "Ups. Niečo sa pokazilo...";
        }
        unset($stmt);
        unset($pdo);
    }
}
?>

<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prihlásenie</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: linear-gradient(to right, #4facfe, #00f2fe);
            color: #333;
        }

        .login-container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        p {
            font-size: 14px;
            color: #555;
        }

        .error {
            color: red;
            font-weight: bold;
            margin-bottom: 10px;
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        button {
            width: 100%;
            background: #4facfe;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background: #007bff;
        }

        .hidden {
            display: none;
        }

        .forgot-password {
            display: block;
            margin-top: 10px;
            text-decoration: none;
            color: #4facfe;
            font-size: 14px;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .cookie-box {
            background: #f8f9fa;
            color: #333;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: background 0.3s ease;
        }

        .cookie-box:hover {
            background: #e9ecef;
        }

        .cookie-box input {
            margin-left: 10px;
            transform: scale(1.2);
        }
    </style>
</head>
<body>

<div class="login-container">
    <h1>Prihlásenie</h1>
    <p>Prihlásenie registrovaného používateľa</p>

    <?php if (!empty($errors)) {
        echo "<div class='error'>$errors</div>";
    } ?>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
        <label for="email">E-Mail:</label>
        <input type="text" name="email" id="email" required>

        <label for="password">Heslo:</label>
        <input type="password" name="password" id="password" required>

        <label for="2fa" class="hidden" id="2fa-label">2FA kód:</label>
        <input type="number" name="2fa" id="2fa" class="hidden" required>

        <div class="cookie-box">
            <label>
                Súhlasím s používaním cookies
                <input type="checkbox" name="accept_cookies" id="accept_cookies">
            </label>
        </div>

        <button type="submit" id="login-btn" disabled>Prihlásiť sa</button>

        <a href="forgot_password.php" class="forgot-password">Zabudnuté heslo?</a>
    </form>

    <p>Alebo sa prihláste pomocou <a href="<?php echo filter_var($redirect_uri, FILTER_SANITIZE_URL) ?>">Google konta</a></p>
    <p>Nemáte účet? <a href="register.php">Zaregistrujte sa tu.</a></p>
</div>

<script>
    document.getElementById("password").addEventListener("input", function () {
        if (this.value.length > 3) {
            document.getElementById("2fa").classList.remove("hidden");
            document.getElementById("2fa-label").classList.remove("hidden");
        }
    });

    document.getElementById("accept_cookies").addEventListener("change", function () {
        document.getElementById("login-btn").disabled = !this.checked;
    });
</script>

</body>
</html>
