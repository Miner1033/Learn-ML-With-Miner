<?php
// ডাটাবেস কানেকশন
$servername = "localhost";
$username = "root";
$password = "";
$database = "Database";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errors = []; // সার্ভার সাইড এরর রাখার জন্য
$old_values = ['name' => '', 'email' => '', 'password' => '', 'task' => ''];

// Image upload directory
$upload_dir = "uploads/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// সার্ভার সাইড Validation ফাংশন
function validate_input($name, $email, $password, $is_update = false) {
    $errors = [];

    if (empty($name) || !preg_match("/^[a-zA-Z\s\-]{2,}$/", $name)) {
        $errors[] = "Name must contain only letters, spaces or dashes, and at least 2 characters.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Only validate password if it's provided (for insert) or if it's an update with new password
    if (!$is_update && empty($password)) {
        $errors[] = "Password is required.";
    }
    if (!empty($password) && !preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{6,}$/", $password)) {
        $errors[] = "Password must be at least 6 characters long, with uppercase, lowercase, number and special character.";
    }
    return $errors;
}

// Image upload function
function upload_image($file) {
    global $upload_dir;
    $errors = [];
    $image_path = '';
    
    if (isset($file) && $file['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Check file type
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed.";
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $errors[] = "File size should not exceed 5MB.";
        }
        
        if (empty($errors)) {
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $image_path = $target_path;
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }
    
    return ['errors' => $errors, 'path' => $image_path];
}

// Insert ইউজার
if (isset($_POST['insert_user'])) {
    $name = $conn->real_escape_string(trim($_POST['name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = trim($_POST['password']);
    $task = $conn->real_escape_string(trim($_POST['task']));

    $errors = validate_input($name, $email, $password, false);
    $old_values = ['name'=>$name, 'email'=>$email, 'password'=>$password, 'task'=>$task];

    // Handle image upload
    $image_result = upload_image($_FILES['image']);
    $errors = array_merge($errors, $image_result['errors']);
    $image_path = $image_result['path'];

    if (count($errors) === 0) {
        // Hash the password before storing
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, task, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $email, $hashed_password, $task, $image_path);
        
        if ($stmt->execute()) {
            header("Location: CRUD_home.php");
            exit();
        } else {
            $errors[] = "Failed to insert user.";
        }
        $stmt->close();
    }
}

// Update ইউজার
if (isset($_POST['update_user'])) {
    $id = intval($_POST['id']);
    $name = $conn->real_escape_string(trim($_POST['name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = trim($_POST['password']);
    $task = $conn->real_escape_string(trim($_POST['task']));

    $errors = validate_input($name, $email, $password, true);
    $old_values = ['name'=>$name, 'email'=>$email, 'password'=>$password, 'task'=>$task];

    // Handle image upload
    $image_result = upload_image($_FILES['image']);
    $errors = array_merge($errors, $image_result['errors']);
    $new_image_path = $image_result['path'];

    if (count($errors) === 0) {
        // Get current user data to handle image deletion
        $current_user = $conn->query("SELECT image FROM users WHERE id=$id LIMIT 1")->fetch_assoc();
        
        // Prepare update query
        if (!empty($password)) {
            // Update with password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            if (!empty($new_image_path)) {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=?, task=?, image=? WHERE id=?");
                $stmt->bind_param("sssssi", $name, $email, $hashed_password, $task, $new_image_path, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=?, task=? WHERE id=?");
                $stmt->bind_param("ssssi", $name, $email, $hashed_password, $task, $id);
            }
        } else {
            // Update without password
            if (!empty($new_image_path)) {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, task=?, image=? WHERE id=?");
                $stmt->bind_param("ssssi", $name, $email, $task, $new_image_path, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, task=? WHERE id=?");
                $stmt->bind_param("sssi", $name, $email, $task, $id);
            }
        }
        
        if ($stmt->execute()) {
            // Delete old image if new one is uploaded
            if (!empty($new_image_path) && !empty($current_user['image']) && file_exists($current_user['image'])) {
                unlink($current_user['image']);
            }
            header("Location: CRUD_home.php");
            exit();
        } else {
            $errors[] = "Failed to update user.";
        }
        $stmt->close();
    }
}

// Delete ইউজার
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Get user data to delete image file
    $user_data = $conn->query("SELECT image FROM users WHERE id=$delete_id LIMIT 1")->fetch_assoc();
    if ($user_data && !empty($user_data['image']) && file_exists($user_data['image'])) {
        unlink($user_data['image']);
    }
    
    $conn->query("DELETE FROM users WHERE id=$delete_id");
    header("Location: CRUD_home.php");
    exit();
}

// ইউজার লিস্ট
$result = $conn->query("SELECT id, name, email, password, task, image FROM users ORDER BY id DESC");

// Edit মোডের জন্য
$edit_user = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $res = $conn->query("SELECT * FROM users WHERE id=$edit_id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $edit_user = $res->fetch_assoc();
        $old_values = ['name'=>$edit_user['name'], 'email'=>$edit_user['email'], 'password'=>'', 'task'=>$edit_user['task']];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin CRUD - User Management</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@500&display=swap" rel="stylesheet" />
  <style>
    body {
      font-family: 'Roboto Mono', monospace;
      background-color: #0a192f;
      color: #61dafb;
      padding: 20px;
    }
    .logout-btn {
      float: right;
      margin-bottom: 15px;
    }
    table {
      background-color: #112240;
    }
    th, td {
      vertical-align: middle !important;
    }
    .user-image {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: 50%;
      border: 2px solid #00fff7;
    }
    .form-box {
      background-color: #112240;
      padding: 20px;
      border-radius: 12px;
      margin-top: 30px;
      max-width: 600px;
      color: #00fff7;
    }
    .form-box input, .form-box textarea, .form-box select {
      background-color: #0a192f;
      border: 1px solid #00fff7;
      color: #61dafb;
    }
    .form-box input[type="file"] {
      border: 1px solid #00fff7;
      padding: 8px;
    }
    .current-image {
      max-width: 100px;
      height: auto;
      border-radius: 8px;
      margin-top: 10px;
      border: 2px solid #00fff7;
    }
    .error-list {
      background: #ff0033;
      color: white;
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 15px;
    }
    .js-error {
      color: #ff6b6b;
      font-size: 0.9rem;
      margin-top: 5px;
    }
    .image-preview {
      max-width: 200px;
      max-height: 200px;
      margin-top: 10px;
      border: 2px solid #00fff7;
      border-radius: 8px;
      display: none;
    }
  </style>
</head>
<body>

<div class="container">
  <h2>Admin Dashboard - User List</h2>
  <a href="signin.php" class="btn btn-danger logout-btn">Logout</a>
  <a href="CRUD_home.php?add_user=1" class="btn btn-primary mb-3">Add New User</a>

  <div class="table-responsive">
    <table class="table table-bordered table-hover text-white">
      <thead>
        <tr>
          <th>ID</th>
          <th>Image</th>
          <th>Name</th>
          <th>Email</th>
          <th>Password</th>
          <th>Task</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result->num_rows > 0): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['id']) ?></td>
              <td>
                <?php if (!empty($row['image']) && file_exists($row['image'])): ?>
                  <img src="<?= htmlspecialchars($row['image']) ?>" alt="User Image" class="user-image">
                <?php else: ?>
                  <div class="user-image d-flex align-items-center justify-content-center" style="background-color: #0a192f; font-size: 12px;">No Image</div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['email']) ?></td>
              <td><small>********</small></td>
              <td><?= htmlspecialchars($row['task']) ?></td>
              <td>
                <a href="CRUD_home.php?edit_id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                <a href="CRUD_home.php?delete_id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure to delete this user?');">Delete</a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="7" class="text-center">No users found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- PHP এরর দেখাবে -->
  <?php if (!empty($errors)): ?>
    <div class="error-list">
      <ul>
        <?php foreach($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <!-- Edit ফর্ম -->
  <?php if ($edit_user): ?>
    <div class="form-box">
      <h4>Edit User ID: <?= htmlspecialchars($edit_user['id']) ?></h4>
      <form id="userForm" method="post" action="CRUD_home.php" enctype="multipart/form-data" onsubmit="return validateForm()">
        <input type="hidden" name="id" value="<?= htmlspecialchars($edit_user['id']) ?>" />

        <div class="mb-3">
          <label for="name" class="form-label">Name</label>
          <input required type="text" class="form-control" name="name" id="name" value="<?= htmlspecialchars($old_values['name']) ?>" />
          <div id="nameError" class="js-error"></div>
        </div>

        <div class="mb-3">
          <label for="email" class="form-label">Email</label>
          <input required type="email" class="form-control" name="email" id="email" value="<?= htmlspecialchars($old_values['email']) ?>" />
          <div id="emailError" class="js-error"></div>
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">Password (leave blank to keep current)</label>
          <input type="text" class="form-control" name="password" id="password" value="<?= htmlspecialchars($old_values['password']) ?>" />
          <div id="passwordError" class="js-error"></div>
        </div>

        <div class="mb-3">
          <label for="task" class="form-label">Task</label>
          <textarea class="form-control" name="task" id="task"><?= htmlspecialchars($old_values['task']) ?></textarea>
        </div>

        <div class="mb-3">
          <label for="image" class="form-label">Profile Image</label>
          <input type="file" class="form-control" name="image" id="image" accept="image/*" onchange="previewImage(this)" />
          <div id="imageError" class="js-error"></div>
          
          <?php if (!empty($edit_user['image']) && file_exists($edit_user['image'])): ?>
            <div class="mt-2">
              <small>Current Image:</small>
              <br>
              <img src="<?= htmlspecialchars($edit_user['image']) ?>" alt="Current Image" class="current-image">
            </div>
          <?php endif; ?>
          
          <img id="imagePreview" class="image-preview" alt="Image Preview">
        </div>

        <button type="submit" name="update_user" class="btn btn-success">Update User</button>
        <a href="CRUD_home.php" class="btn btn-secondary">Cancel</a>
      </form>
    </div>

  <!-- Insert ফর্ম -->
  <?php elseif (isset($_GET['add_user'])): ?>
    <div class="form-box">
      <h4>Add New User</h4>
      <form id="userForm" method="post" action="CRUD_home.php" enctype="multipart/form-data" onsubmit="return validateForm()">

        <div class="mb-3">
          <label for="name" class="form-label">Name</label>
          <input required type="text" class="form-control" name="name" id="name" value="<?= htmlspecialchars($old_values['name']) ?>" />
          <div id="nameError" class="js-error"></div>
        </div>

        <div class="mb-3">
          <label for="email" class="form-label">Email</label>
          <input required type="email" class="form-control" name="email" id="email" value="<?= htmlspecialchars($old_values['email']) ?>" />
          <div id="emailError" class="js-error"></div>
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <input required type="text" class="form-control" name="password" id="password" value="<?= htmlspecialchars($old_values['password']) ?>" />
          <div id="passwordError" class="js-error"></div>
        </div>

        <div class="mb-3">
          <label for="task" class="form-label">Task</label>
          <textarea class="form-control" name="task" id="task"><?= htmlspecialchars($old_values['task']) ?></textarea>
        </div>

        <div class="mb-3">
          <label for="image" class="form-label">Profile Image</label>
          <input type="file" class="form-control" name="image" id="image" accept="image/*" onchange="previewImage(this)" />
          <div id="imageError" class="js-error"></div>
          <img id="imagePreview" class="image-preview" alt="Image Preview">
        </div>

        <button type="submit" name="insert_user" class="btn btn-primary">Insert User</button>
        <a href="CRUD_home.php" class="btn btn-secondary">Cancel</a>
      </form>
    </div>
  <?php endif; ?>

</div>

<script>
function previewImage(input) {
  const preview = document.getElementById('imagePreview');
  const imageError = document.getElementById('imageError');
  
  if (input.files && input.files[0]) {
    const file = input.files[0];
    
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(file.type)) {
      imageError.innerText = "Only JPG, JPEG, PNG & GIF files are allowed.";
      input.value = '';
      preview.style.display = 'none';
      return;
    }
    
    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
      imageError.innerText = "File size should not exceed 5MB.";
      input.value = '';
      preview.style.display = 'none';
      return;
    }
    
    imageError.innerText = "";
    
    const reader = new FileReader();
    reader.onload = function(e) {
      preview.src = e.target.result;
      preview.style.display = 'block';
    }
    reader.readAsDataURL(file);
  } else {
    preview.style.display = 'none';
  }
}

function validateForm() {
  // Clear previous errors
  document.getElementById('nameError').innerText = "";
  document.getElementById('emailError').innerText = "";
  document.getElementById('passwordError').innerText = "";
  document.getElementById('imageError').innerText = "";

  let valid = true;

  const name = document.getElementById('name').value.trim();
  const email = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value.trim();
  const image = document.getElementById('image').files[0];

  // Name Validation: letters, space, dash, min 2 chars
  const nameRegex = /^[a-zA-Z\s\-]{2,}$/;
  if (!nameRegex.test(name)) {
    document.getElementById('nameError').innerText = "Name must contain only letters, spaces or dashes, minimum 2 characters.";
    valid = false;
  }

  // Email Validation
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(email)) {
    document.getElementById('emailError').innerText = "Please enter a valid email address.";
    valid = false;
  }

  // Password Validation: min 6 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
  // Only validate if password is not empty (for edit form)
  if (password.length > 0) {
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{6,}$/;
    if (!passwordRegex.test(password)) {
      document.getElementById('passwordError').innerText = "Password must be at least 6 characters and include uppercase, lowercase, number, and special character.";
      valid = false;
    }
  }

  // Image validation (client-side)
  if (image) {
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(image.type)) {
      document.getElementById('imageError').innerText = "Only JPG, JPEG, PNG & GIF files are allowed.";
      valid = false;
    }
    
    if (image.size > 5 * 1024 * 1024) {
      document.getElementById('imageError').innerText = "File size should not exceed 5MB.";
      valid = false;
    }
  }

  return valid;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php
$conn->close();
?>