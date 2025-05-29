<?php
session_start();
require 'db.php';

// Manually include PHPMailer files
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($email, $verify_code) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'minerhossainrimon1033@gmail.com'; // Your Gmail
        $mail->Password   = 'byls tjyr kxym ofda'; // Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('minerhossainrimon1033@gmail.com', 'Learn ML With Miner');
        $mail->addAddress($email);

        // Content
        $verification_link = "http://".$_SERVER['HTTP_HOST']."/verify.php?code=$verify_code";
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email';
        $mail->Body    = "
            <h2>Email Verification</h2>
            <p>Please click the button below to verify your email address:</p>
            <a href='$verification_link' style='background: #0066cc; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                Verify Email
            </a>
            <p>Or copy this link to your browser:<br>$verification_link</p>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

$success_message = '';
$show_form = true;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate inputs
    $name = trim($_POST["name"]);
    $email = filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirmPassword"];
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Full name is required";
    }
    if (!$email) {
        $errors[] = "Valid email is required";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    if (empty($errors)) {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors[] = "Email already registered";
        }
        $stmt->close();

        if (empty($errors)) {
            // Hash password and generate code
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $verify_code = md5(uniqid(rand(), true));
            $is_verified = 0;

            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, verification_code, is_verified) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $name, $email, $password_hash, $verify_code, $is_verified);

            if ($stmt->execute()) {
                if (sendVerificationEmail($email, $verify_code)) {
                    $success_message = "Registration successful! A verification email has been sent to $email. Please check your inbox and verify your email address before logging in.";
                    $show_form = false;
                } else {
                    // Delete user if email fails
                    $conn->query("DELETE FROM users WHERE email = '".$conn->real_escape_string($email)."'");
                    $errors[] = "Registration complete but email sending failed. Please contact us.";
                }
            } else {
                $errors[] = "Database error: ".$conn->error;
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
    <title>Register - Learn ML With Miner</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- START OF STYLE SECTION -->
<style>
    :root {
        --primary-color: #64ffda; /* Teal accent color */
        --secondary-color: #112240; /* Dark blue background */
        --text-color: #ccd6f6; /* Light text color */
        --dark-accent: #0a192f; /* Slightly darker blue */
        --placeholder-color: var(--primary-color); /* Updated Placeholder text color */
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

    .register-container {
        max-width: 500px;
        width: 100%;
        position: relative;
    }

    .register-card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        overflow: hidden;
        background-color: rgba(10, 25, 47, 0.8);
        backdrop-filter: blur(5px);
        border: 1px solid rgba(100, 255, 218, 0.1);
    }

    .register-header {
        background: linear-gradient(135deg, var(--dark-accent), rgba(100, 255, 218, 0.1));
        color: var(--primary-color);
        padding: 30px;
        text-align: center;
        border-bottom: 1px solid rgba(100, 255, 218, 0.1);
    }

    .register-header p {
        color: var(--primary-color); /* Changed */
        margin-top: 10px;
    }

    .register-body {
        padding: 30px;
        background-color: rgba(10, 25, 47, 0.6);
    }

    .form-label {
        color: var(--primary-color); /* NEW: Label text color */
    }

    .form-control {
        background-color: rgba(10, 25, 47, 0.8);
        border: 1px solid rgba(100, 255, 218, 0.2);
        color: var(--primary-color); /* NEW: Input text color */
    }

    .form-control::placeholder {
        color: var(--placeholder-color);
        opacity: 1;
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(100, 255, 218, 0.25);
        background-color: rgba(10, 25, 47, 0.9);
        color: var(--primary-color);
    }

    .input-group-text {
        background-color: rgba(10, 25, 47, 0.8);
        border: 1px solid rgba(100, 255, 218, 0.2);
        color: var(--primary-color);
    }

    .btn-register {
        background-color: var(--primary-color);
        border: none;
        padding: 10px;
        font-weight: 600;
        color: var(--dark-accent);
        transition: all 0.3s;
    }

    .btn-register:hover {
        background-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(100, 255, 218, 0.3);
    }

    /* Success message */
    .alert-success {
        background-color: rgba(25, 135, 84, 0.2);
        border-color: rgba(25, 135, 84, 0.3);
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

    /* NEW: Already have account text & link styling */
    .login-text,
    .text-decoration-none {
        color: var(--primary-color) !important;
    }
</style>
<!-- END OF STYLE SECTION -->

</head>
<body>
    <!-- Glowing dots background -->
    <div class="dots" id="dots"></div>
    
    <div class="container register-container">
        <div class="card register-card">
            <div class="register-header">
                <h2><i class="fas fa-robot me-2"></i>Learn ML With Miner</h2>
                <p class="mb-0"><?php echo $show_form ? 'Create a new account' : 'Check your email'; ?></p>
            </div>
            <div class="card-body register-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mb-4">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-1"><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success mb-4">
                        <p><?php echo $success_message; ?></p>
                        <p class="mb-0">Didn't receive the email? <a href="#" id="resend-email">Resend verification email</a></p>
                    </div>
                    <div class="text-center">
                        <a href="signin.php" class="btn btn-register">
                            <i class="fas fa-sign-in-alt me-2"></i>Proceed to Login
                        </a>
                    </div>
                <?php elseif ($show_form): ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="name" name="name" placeholder="Enter your full name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                                <button type="button" class="password-toggle" id="togglePassword">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
                                <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-register w-100 mb-3">
                            <i class="fas fa-user-plus me-2"></i>Register
                        </button>

                        <div class="text-center">
                            <p class="mb-0 login-text">Already have an account? <a href="signin.php" class="text-decoration-none">Login</a></p>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
       // Password toggle functionality and form validation
document.addEventListener('DOMContentLoaded', function() {
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const nameInput = document.getElementById('name');
    const form = document.querySelector('form');
    
    // Password toggle
    if (togglePassword) {
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
    }
    
    // Confirm password toggle
    if (toggleConfirmPassword) {
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            
            // Toggle eye icon
            if (type === 'password') {
                this.innerHTML = '<i class="far fa-eye"></i>';
            } else {
                this.innerHTML = '<i class="far fa-eye-slash"></i>';
            }
        });
    }
    
    // Resend email functionality
    const resendLink = document.getElementById('resend-email');
    if (resendLink) {
        resendLink.addEventListener('click', function(e) {
            e.preventDefault();
            // You would typically make an AJAX call here to resend the email
            alert('A new verification email has been sent. Please check your inbox.');
        });
    }
    
    // Name validation (no numbers allowed)
    if (nameInput) {
        nameInput.addEventListener('input', function() {
            const nameRegex = /^[a-zA-Z\s]*$/;
            if (!nameRegex.test(this.value)) {
                this.setCustomValidity('Name should not contain numbers or special characters');
                this.reportValidity();
            } else {
                this.setCustomValidity('');
            }
        });
    }
    
    // Password validation
    if (password) {
        password.addEventListener('input', function() {
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{6,}$/;
            if (!passwordRegex.test(this.value)) {
                this.setCustomValidity('Password must be at least 6 characters and contain at least one uppercase letter, one lowercase letter, one number, and one special character');
                this.reportValidity();
            } else {
                this.setCustomValidity('');
            }
        });
    }
    
    // Form submission validation
    if (form) {
        form.addEventListener('submit', function(e) {
            // Recheck all validations before submission
            if (nameInput && !/^[a-zA-Z\s]*$/.test(nameInput.value)) {
                e.preventDefault();
                alert('Name should not contain numbers or special characters');
                nameInput.focus();
                return;
            }
            
            if (password && !/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{6,}$/.test(password.value)) {
                e.preventDefault();
                alert('Password must be at least 6 characters and contain at least one uppercase letter, one lowercase letter, one number, and one special character');
                password.focus();
                return;
            }
            
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match');
                confirmPassword.focus();
                return;
            }
        });
    }
});
    </script>
</body>
</html>