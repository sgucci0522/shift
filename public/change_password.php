<?php
session_start();

// If user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワードの変更</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f7f6;
        }
        .change-password-container {
            width: 100%;
            max-width: 450px;
            padding: 30px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .error {
            color: #D8000C;
            background-color: #FFD2D2;
            border: 1px solid #D8000C;
        }
        .success {
            color: #4F8A10;
            background-color: #DFF2BF;
            border: 1px solid #4F8A10;
        }
    </style>
</head>
<body>
    <div class="change-password-container">
        <form action="change_password_process.php" method="post">
            <h2 style="text-align: center;">パスワードの変更</h2>
            <p style="text-align: center; margin-bottom: 20px;">セキュリティのため、初回ログイン時にパスワードを変更してください。</p>

            <?php if ($error): ?>
                <p class="message error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="message success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>

            <div class="form-group">
                <label for="new_password">新しいパスワード</label>
                <input type="password" name="new_password" id="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">新しいパスワード（確認用）</label>
                <input type="password" name="confirm_password" id="confirm_password" required>
            </div>
            <button type="submit">パスワードを変更</button>
        </form>
    </div>
</body>
</html>
