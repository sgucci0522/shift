<?php
session_start();

// If user is already logged in, redirect them to the main page
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f7f6;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 30px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .error {
            color: #D8000C;
            background-color: #FFD2D2;
            border: 1px solid #D8000C;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <form action="auth_process.php" method="post">
            <h2 style="text-align: center;">ログイン</h2>
            <?php if (isset($_GET['error'])) { ?>
                <p class="error"><?php echo htmlspecialchars($_GET['error']); ?></p>
            <?php } ?>
            <div class="form-group">
                <label for="username">ユーザー名</label>
                <input type="text" name="username" id="username" required>
            </div>
            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" name="password" id="password" required>
            </div>
            <button type="submit">ログイン</button>
        </form>
    </div>
</body>
</html>