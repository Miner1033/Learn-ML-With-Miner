<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "Database";

$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email) || empty($password)) {
        die("Please fill all fields");
    }

    // ✅ Check for admin directly
    if ($email === "admin@gmail.com" && $password === "Admin@123") {
        $_SESSION["user_email"] = $email;
        $_SESSION["role"] = "admin"; // Optional, in case you want to check role later
        header("Location: CRUD_home.php");
        exit();
    }

    // Check other users from database
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // ✅ Check hashed password from database
            if (password_verify($password, $user['password'])) {
                $_SESSION["user_email"] = $user['email'];
                $_SESSION["role"] = "user"; // Optional
                header("Location: index.html");
                exit();
            } else {
                echo "<script>alert('Wrong password!'); window.history.back();</script>";
            }
        } else {
            echo "<script>alert('User not found!'); window.history.back();</script>";
        }

        $stmt->close();
    } else {
        echo "SQL error: " . $conn->error;
    }
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Learn ML With Miner</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #64ffda; /* Teal accent color */
            --secondary-color: #112240; /* Dark blue background */
            --text-color: #ccd6f6; /* Light text color */
            --dark-accent: #0a192f; /* Slightly darker blue */
            --placeholder-color: rgba(204, 214, 246, 0.7); /* Placeholder text color */
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            background-color: var(--secondary-color);
            color: var(--text-color);
            overflow: hidden;
        }
        
        /* Glowing dots background effect */
        .dots {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .dot {
            position: absolute;
            background: rgba(100, 255, 218, 0.15);
            border-radius: 50%;
            filter: blur(1px);
        }
        
        .login-container {
            max-width: 500px;
            width: 100%;
            position: relative;
        }
        
        .login-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            background-color: rgba(10, 25, 47, 0.8);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(100, 255, 218, 0.1);
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--dark-accent), rgba(100, 255, 218, 0.1));
            color: var(--primary-color);
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid rgba(100, 255, 218, 0.1);
        }
        
        .login-header p {
            color: var(--text-color);
            margin-top: 10px;
        }
        
        .login-body {
            padding: 30px;
            background-color: rgba(10, 25, 47, 0.6);
        }
        
        .form-control {
            background-color: rgba(10, 25, 47, 0.8);
            border: 1px solid rgba(100, 255, 218, 0.2);
            color: var(--text-color);
        }
        
        .form-control::placeholder {
            color: var(--placeholder-color);
            opacity: 1;
        }
        
        .form-control:-ms-input-placeholder {
            color: var(--placeholder-color);
        }
        
        .form-control::-ms-input-placeholder {
            color: var(--placeholder-color);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(100, 255, 218, 0.25);
            background-color: rgba(10, 25, 47, 0.9);
            color: var(--text-color);
        }
        
        .input-group-text {
            background-color: rgba(10, 25, 47, 0.8);
            border: 1px solid rgba(100, 255, 218, 0.2);
            color: var(--primary-color);
        }
        
        .btn-login {
            background-color: var(--primary-color);
            border: none;
            padding: 10px;
            font-weight: 600;
            color: var(--dark-accent);
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            background-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(100, 255, 218, 0.3);
        }
        
        .social-login .btn {
            padding: 10px;
            font-weight: 500;
            transition: all 0.3s;
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .social-login .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(100, 255, 218, 0.2);
            background-color: rgba(100, 255, 218, 0.1);
        }
        
        .divider {
            position: relative;
            text-align: center;
            margin: 20px 0;
        }
        
        .divider::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background-color: rgba(100, 255, 218, 0.2);
            z-index: -1;
        }
        
        .divider span {
            background-color: var(--dark-accent);
            padding: 0 15px;
            color: var(--primary-color);
        }
        
        a {
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        a:hover {
            color: #fff;
            text-decoration: underline;
        }
        
        label {
            color: var(--primary-color);
        }
        
        .register-text {
            color: var(--text-color);
        }
        
        .register-text a {
            color: var(--primary-color);
        }
        
        /* Password toggle button */
        .password-toggle {
            cursor: pointer;
            background-color: transparent;
            border: none;
            color: var(--primary-color);
            padding: 0 10px;
        }
        
        .password-toggle:hover {
            color: #fff;
        }
    </style>
</head>
<body>
    <!-- Glowing dots background -->
    <div class="dots" id="dots"></div>
    
    <div class="container login-container">
        <div class="card login-card">
            <div class="login-header">
                <h2><i class="fas fa-robot me-2"></i>Learn ML With Miner</h2>
                <p class="mb-0">Sign in to continue your learning journey</p>
            </div>
            <div class="card-body login-body">
               <!-- Change the form opening tag to this: -->
<form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
    <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
            <input type="email" name="email" class="form-control" id="email" placeholder="Enter your email" required>
        </div>
    </div>
    <div class="mb-4">
        <label for="password" class="form-label">Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" name="password" class="form-control" id="password" placeholder="Enter your password" required>
            <button type="button" class="password-toggle" id="togglePassword">
                <i class="far fa-eye"></i>
            </button>
        </div>
    </div>
    <!-- Add this login button section -->
    <div class="d-grid mb-3">
        <button type="submit" class="btn btn-login">
            <i class="fas fa-sign-in-alt me-2"></i>Sign In
        </button>
    </div>
</form>
<div class="text-center register-text">
    <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
</div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Background dots (existing code)
    const dotsContainer = document.getElementById('dots');
    const dotCount = 50;
    
    for (let i = 0; i < dotCount; i++) {
        const dot = document.createElement('div');
        dot.classList.add('dot');
        
        const size = Math.random() * 4 + 2;
        dot.style.width = `${size}px`;
        dot.style.height = `${size}px`;
        dot.style.left = `${Math.random() * 100}%`;
        dot.style.top = `${Math.random() * 100}%`;
        dot.style.opacity = Math.random() * 0.5 + 0.1;
        
        dotsContainer.appendChild(dot);
    }
    
    // Password toggle functionality (existing code)
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    
    togglePassword.addEventListener('click', function() {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        
        // Toggle eye icon
        if (type === 'password') {
            this.innerHTML = '<i class="far fa-eye"></i>';
        } else {
            this.innerHTML = '<i class="far fa-eye-slash"></i>';
        }
    });

    // ========== NEW VALIDATION CODE ==========
    const form = document.querySelector('form');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    // Email validation regex
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    
    // Password validation regex
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{6,}$/;

    // Real-time validation for email
    emailInput.addEventListener('input', function() {
        if (!emailRegex.test(this.value)) {
            this.setCustomValidity('Please enter a valid email (e.g., user@example.com)');
        } else {
            this.setCustomValidity('');
        }
    });

    // Real-time validation for password
    passwordInput.addEventListener('input', function() {
        if (!passwordRegex.test(this.value)) {
            this.setCustomValidity('Password must be 6+ chars with: 1 uppercase, 1 lowercase, 1 digit, and 1 special char (@$!%*?&)');
        } else {
            this.setCustomValidity('');
        }
    });

    // Form submission validation
    form.addEventListener('submit', function(e) {
        // Clear previous messages
        emailInput.setCustomValidity('');
        passwordInput.setCustomValidity('');

        // Validate email
        if (!emailRegex.test(emailInput.value)) {
            e.preventDefault();
            emailInput.setCustomValidity('Please enter a valid email (e.g., user@example.com)');
            emailInput.reportValidity();
            return;
        }

        // Validate password
        if (!passwordRegex.test(passwordInput.value)) {
            e.preventDefault();
            passwordInput.setCustomValidity('Password must be 6+ chars with: 1 uppercase, 1 lowercase, 1 digit, and 1 special char (@$!%*?&)');
            passwordInput.reportValidity();
            return;
        }
    });
});
</script>
   
</body>
</html>