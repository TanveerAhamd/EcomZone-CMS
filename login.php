<?php
/**
 * LOGIN PAGE
 * Split layout design with purple gradient + login form
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

startSession();

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    redirect('admin/dashboard.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $error = 'Security token expired. Please try again.';
    } else {
        $email = sanitizeEmail($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter email and password.';
        } else {
            global $db;
            
            // Query user by email
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            
            // Verify password
            if ($user && password_verify($password, $user['password'])) {
                // Password correct - update last login
                $stmt = $db->prepare("
                    UPDATE users 
                    SET last_login = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $user['id']]);
                
                // Create session
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                // Handle remember me
                if (isset($_POST['remember_me'])) {
                    $token = bin2hex(random_bytes(32));
                    $stmt = $db->prepare("UPDATE users SET remember_token = :token WHERE id = :id");
                    $stmt->execute([':token' => $token, ':id' => $user['id']]);
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                }
                
                logActivity('LOGIN', 'auth', $user['id'], 'User logged in successfully');
                
                // Redirect to dashboard
                redirect('admin/dashboard.php');
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Login</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            background: #f4f4f4;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            height: 100vh;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .login-left {
            background: linear-gradient(135deg, #6418C3 0%, #1D1D35 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
            color: white;
        }

        .login-left-content h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            line-height: 1.3;
        }

        .login-left-content p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        .login-left .icon {
            font-size: 4rem;
            margin-bottom: 25px;
            opacity: 0.9;
        }

        .login-right {
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .login-form-card {
            width: 100%;
            max-width: 400px;
        }

        .login-form-card h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1D1D1D;
            margin-bottom: 10px;
        }

        .login-form-card .subtitle {
            color: #888;
            font-size: 0.95rem;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: 500;
            font-size: 0.85rem;
            color: #444;
            margin-bottom: 8px;
            display: block;
        }

        .form-group input {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 10px 15px;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            border-color: #6418C3;
            box-shadow: 0 0 0 3px rgba(100,24,195,0.15);
            outline: none;
        }

        .form-group input.error {
            border-color: #FF5E5E;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle-icon {
            position: absolute;
            right: 15px;
            top: 38px;
            cursor: pointer;
            color: #999;
            font-size: 1rem;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }

        .remember-forgot a {
            color: #6418C3;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .remember-forgot a:hover {
            color: #5010a6;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #6418C3, #7e2dd5);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            background: linear-gradient(135deg, #5910b8, #6e25c0);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(100,24,195,0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
            border: none;
            font-size: 0.9rem;
        }

        .alert-danger {
            background-color: rgba(255,94,94,0.1);
            color: #FF5E5E;
        }

        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
                height: auto;
            }

            .login-left {
                min-height: 200px;
                padding: 25px;
            }

            .login-left-content h1 {
                font-size: 1.8rem;
            }

            .login-left .icon {
                font-size: 3rem;
            }

            .login-right {
                min-height: 100vh;
                padding: 25px;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <!-- LEFT SIDE - Brand & Message -->
    <div class="login-left">
        <div class="login-left-content">
            <div class="icon">
                <i class="fas fa-cube"></i>
            </div>
            <h1><?php echo APP_NAME; ?></h1>
            <p>Manage Clients<br>Track Services<br>Grow Business</p>
        </div>
    </div>

    <!-- RIGHT SIDE - Login Form -->
    <div class="login-right">
        <div class="login-form-card">
            <h2>Welcome Back 👋</h2>
            <p class="subtitle">Sign in to <?php echo APP_NAME; ?></p>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo clean($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation">
                <?php echo csrfField(); ?>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo clean($email); ?>" required>
                </div>

                <div class="form-group password-toggle">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <i class="fas fa-eye password-toggle-icon" onclick="togglePassword()"></i>
                </div>

                <div class="remember-forgot">
                    <label style="display: flex; align-items: center; gap: 8px; margin: 0; font-weight: 400;">
                        <input type="checkbox" name="remember_me" class="form-check-input">
                        Remember me
                    </label>
                    <a href="#">Forgot password?</a>
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div style="text-align: center; margin-top: 20px; color: #999; font-size: 0.85rem;">
                Demo: admin@cms-ecomzone.com / Admin@123
            </div>
        </div>
    </div>
</div>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const icon = document.querySelector('.password-toggle-icon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>

</body>
</html>
