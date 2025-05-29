<?php
session_start();

// Logout functionality
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: signin.php");
    exit();
}

// If user not logged in
if (!isset($_SESSION["user_email"])) {
    header("Location: signin.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "Database";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_email = $_SESSION["user_email"];

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $new_password = trim($_POST["password"]);

    // Image Upload
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $unique_name = uniqid("IMG_", true) . "." . $file_ext;
        $target_file = $target_dir . $unique_name;

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($file_ext), $allowed_types)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = $target_file;
            } else {
                $error = "Image upload failed.";
            }
        } else {
            $error = "Invalid file type. Only JPG, JPEG, PNG & GIF are allowed.";
        }
    }

    if (empty($name) || empty($email)) {
        $error = "Name and Email are required!";
    } else {
        // Check if email is changed and if new email already exists in DB (excluding current user)
        if ($email !== $user_email) {
            $check_sql = "SELECT email FROM users WHERE email = ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $error = "This email is already taken by another user!";
            }
            $stmt_check->close();
        }

        if (!isset($error)) {
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET name = ?, email = ?, password = ?" . ($image_path ? ", image = ?" : "") . " WHERE email = ?";
                if ($image_path) {
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssss", $name, $email, $hashed_password, $image_path, $user_email);
                } else {
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssss", $name, $email, $hashed_password, $user_email);
                }
            } else {
                $sql = "UPDATE users SET name = ?, email = ?" . ($image_path ? ", image = ?" : "") . " WHERE email = ?";
                if ($image_path) {
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssss", $name, $email, $image_path, $user_email);
                } else {
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $name, $email, $user_email);
                }
            }

            if ($stmt->execute()) {
                $_SESSION["user_email"] = $email;
                $stmt->close();
                $conn->close();

                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
            } else {
                $error = "Update failed: " . $conn->error;
                $stmt->close();
            }
        }
    }
}

// Fetch user data
$sql = "SELECT name, email, task, image FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
} else {
    header("Location: ?action=logout");
    exit();
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Learn ML With Miner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    :root {
        --primary: #00ff9d;
        --secondary: #00b4ff;
        --dark: #0a192f;
        --light: #ccd6f6;
        --alert: #ff2d75;
        --success: #00ff9d;
        --placeholder:rgb(24, 228, 181);
    }
    
    body {
        background-color: var(--dark);
        font-family: 'Courier New', monospace;
        color: var(--light);
        margin: 0;
        padding: 0;
        background-image: 
            radial-gradient(circle at 25% 25%, rgba(0, 255, 157, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 75% 75%, rgba(0, 180, 255, 0.1) 0%, transparent 50%);
    }
    
    .profile-container {
        max-width: 600px;
        margin: 50px auto;
        padding: 30px;
        border: 1px solid var(--primary);
        border-radius: 5px;
        box-shadow: 0 0 20px rgba(0, 255, 157, 0.3),
                    0 0 40px rgba(0, 180, 255, 0.2);
        background-color: rgba(10, 25, 47, 0.9);
        position: relative;
        overflow: hidden;
    }
    
    .profile-container::before {
        content: "";
        position: absolute;
        top: -2px;
        left: -2px;
        right: -2px;
        bottom: -2px;
        background: linear-gradient(45deg, var(--primary), var(--secondary), var(--primary));
        z-index: -1;
        animation: borderAnimation 4s linear infinite;
        background-size: 400%;
        border-radius: 5px;
    }
    
    @keyframes borderAnimation {
        0% { background-position: 0 0; }
        50% { background-position: 100% 0; }
        100% { background-position: 0 0; }
    }
    
    .profile-header h2 {
        color: var(--primary);
        text-align: center;
        margin-bottom: 30px;
        font-size: 28px;
        text-transform: uppercase;
        letter-spacing: 2px;
        text-shadow: 0 0 10px rgba(0, 255, 157, 0.5);
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        color: var(--secondary);
        font-weight: bold;
        letter-spacing: 1px;
    }
    
    .form-control {
        width: 100%;
        padding: 12px 15px;
        margin-bottom: 20px;
        background-color: rgba(10, 25, 47, 0.7);
        border: 1px solid var(--secondary);
        border-radius: 3px;
        color: var(--light);
        font-family: 'Courier New', monospace;
        transition: all 0.3s ease;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 10px rgba(0, 255, 157, 0.3);
    }
    
    .form-control[readonly] {
        background-color: rgba(10, 25, 47, 0.5);
        color: var(--placeholder);
        border-color: #1e2a4a;
    }
    
    /* Placeholder Styling */
    .form-control::placeholder {
        color: var(--placeholder);
        opacity: 1;
        font-style: italic;
    }
    
    .form-control::-webkit-input-placeholder {
        color: var(--placeholder);
        font-style: italic;
    }
    
    .form-control::-moz-placeholder {
        color: var(--placeholder);
        font-style: italic;
    }
    
    .form-control:-ms-input-placeholder {
        color: var(--placeholder);
        font-style: italic;
    }
    
    .form-control:-moz-placeholder {
        color: var(--placeholder);
        font-style: italic;
    }
    
    .button-group {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .btn {
        padding: 12px 20px;
        border: none;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-grow: 1;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .btn-primary {
        background-color: var(--primary);
        color: var(--dark);
    }
    
    .btn-primary:hover {
        background-color: #00e68a;
        box-shadow: 0 0 15px rgba(0, 255, 157, 0.5);
    }
    
    .btn-success {
        background-color: var(--secondary);
        color: var(--dark);
    }
    
    .btn-success:hover {
        background-color: #0099cc;
        box-shadow: 0 0 15px rgba(0, 180, 255, 0.5);
    }
    
    .btn-danger {
        background-color: var(--alert);
        color: white;
    }
    
    .btn-danger:hover {
        background-color: #e6005c;
        box-shadow: 0 0 15px rgba(255, 45, 117, 0.5);
    }
    
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 3px;
        font-weight: bold;
    }
    
    .alert-danger {
        background-color: rgba(255, 45, 117, 0.2);
        border-left: 4px solid var(--alert);
        color: var(--alert);
    }
    
    .alert-success {
        background-color: rgba(0, 255, 157, 0.2);
        border-left: 4px solid var(--success);
        color: var(--success);
    }
    
    .image-upload-container {
        margin-bottom: 20px;
    }
    
    .profile-image {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--primary);
        margin-bottom: 10px;
    }
    
    .image-preview-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .file-input-label {
        display: inline-block;
        padding: 8px 15px;
        background-color: var(--secondary);
        color: var(--dark);
        border-radius: 3px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: bold;
        text-align: center;
    }
    
    .file-input-label:hover {
        background-color: #0099cc;
        box-shadow: 0 0 10px rgba(0, 180, 255, 0.5);
    }
    
    .file-input {
        display: none;
    }
    
    @media (max-width: 768px) {
        .profile-container {
            margin: 20px;
            padding: 20px;
        }
        
        .button-group {
            flex-direction: column;
        }
    }
</style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <h2>Your Profile</h2>
        </div>

        <div class="profile-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="alert alert-success">Profile updated successfully!</div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="image-upload-container">
                    <div class="image-preview-container">
                        <?php if (!empty($user['image'])): ?>
                            <img src="<?php echo htmlspecialchars($user['image']); ?>" alt="Profile Image" class="profile-image">
                        <?php else: ?>
                            <img src="profile.jpg" alt="Profile Image" class="profile-image">
                        <?php endif; ?>
                        <label for="image-upload" class="file-input-label">
                            <i class="fas fa-camera me-2"></i>Change Photo
                        </label>
                        <input type="file" id="image-upload" name="image" accept="image/*" class="file-input">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="name" class="form-label">Full Name</label>
                    <input
                        type="text"
                        class="form-control"
                        id="name"
                        name="name"
                        value="<?php echo htmlspecialchars($user['name']); ?>"
                        required
                    />
                </div>

                <div class="mb-4">
                    <label for="email" class="form-label">Email Address</label>
                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        value="<?php echo htmlspecialchars($user['email']); ?>"
                        required
                    />
                </div>

                <div class="mb-4">
                    <label for="task" class="form-label">Current Task</label>
                    <input
                        type="text"
                        class="form-control"
                        id="task"
                        name="task"
                        value="<?php echo htmlspecialchars($user['task'] ?? ''); ?>"
                        readonly
                    />
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        placeholder="Enter new password to change (leave blank to keep current)"
                    />
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Profile
                    </button>
                    <a href="overview.html" class="btn btn-success">
                        <i class="fas fa-chart-line me-2"></i>My course
                    </a>
                    <a href="?action=logout" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Preview image before upload
        document.getElementById('image-upload').addEventListener('change', function(e) {
            const preview = document.querySelector('.profile-image');
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>