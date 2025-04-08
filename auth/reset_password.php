<?php
require_once "../projekt1/config.php";
$pdo = connectDatabase($hostname, $database, $username, $password);

$errors = "";
$success = "";
$token = $_GET['token'] ?? '';

if (!$token) {
    die("Neplatná žiadosť.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = trim($_POST["new_password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    if (strlen($new_password) < 6) {
        $errors = "Heslo musí obsahovať aspoň 6 znakov.";
    } elseif ($new_password !== $confirm_password) {
        $errors = "Heslá sa nezhodujú.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password = :password, reset_token = NULL, reset_expiry = NULL WHERE reset_token = :token AND reset_expiry > NOW()");
        $stmt->bindParam(":password", $hashed_password, PDO::PARAM_STR);
        $stmt->bindParam(":token", $token, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $success = "Heslo bolo úspešne obnovené. Môžete sa prihlásiť.";
        } else {
            $errors = "Nepodarilo sa obnoviť heslo.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset hesla</title>
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
    <h1>Reset hesla</h1>

    <?php if ($errors): ?>
        <div class="error"><?php echo $errors; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php else: ?>
        <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
            <input type="password" name="new_password" placeholder="Nové heslo" required>
            <input type="password" name="confirm_password" placeholder="Potvrďte heslo" required>
            <button type="submit">Obnoviť heslo</button>
        </form>
    <?php endif; ?>

    <p><a href="login.php">Späť na prihlásenie</a></p>
</div>

</body>
</html>
