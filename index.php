<?php
session_start();
require "connexion.php";

// Process POST data first
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['ajouter'])) {
            $nom = $_POST['nom'];
            $prenom = $_POST['prenom'];
            $email = $_POST['email'];

            // First check if email already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            $emailExists = $checkStmt->fetchColumn();

            if ($emailExists) {
                $_SESSION['notification'] = [
                    'type' => 'error',
                    'message' => 'This email address is already registered!'
                ];
                header('Location: index.php');
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email) VALUES (?, ?, ?)");
            $stmt->execute([$nom, $prenom, $email]);
            
            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => 'User added successfully!'
            ];
            header('Location: index.php');
            exit;
        }

        if (isset($_POST['modifier'])) {
            $idU = $_POST['idU'];
            $nom = $_POST['nom'];
            $prenom = $_POST['prenom'];
            $email = $_POST['email'];

            // Check if email exists for other users (when modifying)
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $checkStmt->execute([$email, $idU]);
            $emailExists = $checkStmt->fetchColumn();

            if ($emailExists) {
                $_SESSION['notification'] = [
                    'type' => 'error',
                    'message' => 'This email address is already registered to another user!'
                ];
                header('Location: index.php?idUser='.$idU);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET nom=?, prenom=?, email=? WHERE id=?");
            $stmt->execute([$nom, $prenom, $email, $idU]);
            
            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => 'User updated successfully!'
            ];
            header('Location: index.php');
            exit;
        }
    } catch (PDOException $e) {
        // Catch any other database errors
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ];
        header('Location: index.php');
        exit;
    }
}

// Fetch data for editing
$id = $nom = $prenom = $email = null;
if (isset($_GET['idUser'])) {
    $id = $_GET['idUser'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_OBJ);
    if ($user) {
        $nom = $user->nom;
        $prenom = $user->prenom;
        $email = $user->email;
    }
}

// Delete user
if (isset($_GET['idUserDelete'])) {
    try {
        $id = $_GET['idUserDelete'];
        $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
        $stmt->execute([$id]);
        
        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'User deleted successfully!'
        ];
        header('Location: index.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Error deleting user: ' . $e->getMessage()
        ];
        header('Location: index.php');
        exit;
    }
}

// Pagination and Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 5; // Number of items per page

// Build the base query
$query = "SELECT * FROM users";
$countQuery = "SELECT COUNT(*) FROM users";
$params = [];
$where = [];

// Add search conditions if search term exists
if (!empty($search)) {
    $where[] = "(nom LIKE ? OR prenom LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

// Combine where conditions
if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
    $countQuery .= " WHERE " . implode(" AND ", $where);
}

// Get total number of records
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();

// Calculate total pages
$totalPages = ceil($totalRecords / $perPage);

// Adjust current page if out of bounds
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
}

// Add pagination to query
$offset = ($page - 1) * $perPage;
$query .= " LIMIT $offset, $perPage";

// Fetch paginated users
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        dark: {
                            100: '#1E293B',
                            200: '#0F172A',
                            300: '#0F172A',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
        }
        .sidebar {
            transition: all 0.3s ease;
        }
        .notification {
            animation: slideIn 0.3s ease-out, fadeOut 0.3s ease-out 3s forwards;
        }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes fadeOut {
            to { opacity: 0; visibility: hidden; }
        }
        /* Modal animations */
        #deleteModal {
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        #deleteModal.show {
            opacity: 1;
            pointer-events: auto;
        }
        #deleteModal > div {
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }
        #deleteModal.show > div {
            transform: translateY(0);
        }
        /* Add to your existing styles */
#theme-toggle {
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
}

#theme-toggle:focus {
    outline: 2px solid transparent;
    outline-offset: 2px;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
}

.dark #theme-toggle {
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
}

#theme-toggle div {
    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.27, 1.55);
}

/* Background transition for smooth theme switching */
body {
    transition: background-color 0.3s ease;
}

/* Pulse animation for the toggle when theme changes */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.theme-change-pulse {
    animation: pulse 0.5s ease;
}
        
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-dark-200">
    <!-- Enhanced Notification System -->
    <?php if(isset($_SESSION['notification'])): ?>
    <div class="notification fixed top-6 right-6 z-50 px-6 py-4 rounded-lg shadow-lg bg-white dark:bg-dark-100 border-l-4 <?= $_SESSION['notification']['type'] === 'success' ? 'border-green-500' : 'border-red-500' ?> flex items-center">
        <i class="fas fa-<?= $_SESSION['notification']['type'] === 'success' ? 'check-circle text-green-500' : 'exclamation-circle text-red-500' ?> mr-3"></i>
        <div>
            <p class="text-gray-800 dark:text-white font-medium"><?= htmlspecialchars($_SESSION['notification']['message']) ?></p>
            <?php if(isset($_SESSION['notification']['details'])): ?>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1"><?= htmlspecialchars($_SESSION['notification']['details']) ?></p>
            <?php endif; ?>
        </div>
        <button onclick="this.parentElement.remove()" class="ml-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php unset($_SESSION['notification']); endif; ?>

    <!-- Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-dark-100 rounded-xl shadow-xl p-6 w-full max-w-md">
            <div class="flex justify-center mb-4">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                </div>
            </div>
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white">Confirm Deletion</h3>
                <button id="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p class="text-gray-600 dark:text-gray-300 mb-6">Are you sure you want to delete this user? This action cannot be undone.</p>
            <div class="flex justify-end space-x-3">
                <button id="cancelDelete" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 dark:text-white dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-dark-200 transition">
                    Cancel
                </button>
                <button id="confirmDelete" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 transition">
                    Delete User
                </button>
            </div>
        </div>
    </div>

    <!-- Dashboard Layout -->
    <div class="flex h-full">
        <!-- Sidebar -->
        <div class="sidebar w-64 bg-gradient-to-b from-primary-800 to-primary-900 text-white flex flex-col">
            <div class="p-6 flex items-center space-x-3">
                <div class="w-10 h-10 rounded-lg bg-primary-600 flex items-center justify-center">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <h1 class="text-xl font-bold">UserAdmin</h1>
            </div>
            <nav class="flex-1 px-4 space-y-2">
                <a href="index.php" class="block px-4 py-3 rounded-lg bg-primary-700 text-white font-medium">
                    <i class="fas fa-users mr-3"></i> Users
                </a>
                <a href="#" class="block px-4 py-3 rounded-lg text-primary-200 hover:bg-primary-700 hover:text-white transition">
                    <i class="fas fa-cog mr-3"></i> Settings
                </a>
                <a href="#" class="block px-4 py-3 rounded-lg text-primary-200 hover:bg-primary-700 hover:text-white transition">
                    <i class="fas fa-chart-bar mr-3"></i> Analytics
                </a>
            </nav>
            <!-- In your sidebar section where the theme toggle is located -->
<div class="p-4 border-t border-primary-700 flex items-center justify-between">
    <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-full bg-primary-600 flex items-center justify-center">
            <i class="fas fa-user"></i>
        </div>
        <div>
            <p class="font-medium">Admin User</p>
            <p class="text-xs text-primary-300">admin@example.com</p>
        </div>
    </div>
    <button id="theme-toggle" class="relative w-12 h-6 rounded-full p-1 transition-colors duration-500 bg-gray-200 dark:bg-dark-300 focus:outline-none">
        <!-- Track -->
        <div class="absolute inset-0 flex items-center justify-between px-1.5">
            <i class="fas fa-sun text-yellow-400 text-xs opacity-100 dark:opacity-0 transition-opacity duration-300"></i>
            <i class="fas fa-moon text-blue-300 text-xs opacity-0 dark:opacity-100 transition-opacity duration-300"></i>
        </div>
        <!-- Thumb -->
        <div class="w-4 h-4 rounded-full bg-white shadow-md transform transition-transform duration-500 translate-x-0 dark:translate-x-6"></div>
    </button>
</div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Top Navigation -->
            <header class="bg-white dark:bg-dark-100 shadow-sm">
                <div class="px-6 py-4 flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
                        <i class="fas fa-users mr-2 text-primary-600"></i> User Management
                    </h1>
                    <div class="flex items-center space-x-4">
                        
                        <button class="w-10 h-10 rounded-full bg-gray-100 dark:bg-dark-300 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-dark-200 transition">
                            <i class="fas fa-bell"></i>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white dark:bg-dark-100 rounded-xl shadow-sm p-6 border-l-4 border-primary-500">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-300">Total Users</p>
                                <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1"><?= $totalRecords ?></h3>
                            </div>
                            <div class="p-3 rounded-lg bg-primary-100 text-primary-600">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-dark-100 rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-300">Active</p>
                                <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1"><?= $totalRecords ?></h3>
                            </div>
                            <div class="p-3 rounded-lg bg-blue-100 text-blue-600">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-dark-100 rounded-xl shadow-sm p-6 border-l-4 border-green-500">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-300">New Today</p>
                                <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1">0</h3>
                            </div>
                            <div class="p-3 rounded-lg bg-green-100 text-green-600">
                                <i class="fas fa-user-plus"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-dark-100 rounded-xl shadow-sm p-6 border-l-4 border-indigo-500">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-300">Inactive</p>
                                <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1">0</h3>
                            </div>
                            <div class="p-3 rounded-lg bg-indigo-100 text-indigo-600">
                                <i class="fas fa-user-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Form and Table -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- User Form -->
                    <div class="lg:col-span-1">
                        <div class="bg-white dark:bg-dark-100 rounded-xl shadow-sm overflow-hidden">
                            <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-6 text-white">
                                <h2 class="text-xl font-bold">
                                    <?=isset($user) ? 'Edit User' : 'Add New User'?>
                                </h2>
                                <p class="opacity-90 text-primary-100 text-sm mt-1">
                                    <?=isset($user) ? 'Update user information' : 'Fill in the details below to add a new user'?>
                                </p>
                            </div>
                            
                            <form action="index.php" method="post" class="p-6 space-y-4">
                                <input type="hidden" name="idU" value="<?=isset($user)?$id:""?>">
                                
                                <div>
                                    <label for="nom" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-user text-gray-400"></i>
                                        </div>
                                        <input type="text" id="nom" name="nom" value="<?=isset($user)?$nom:""?>" 
                                            class="pl-10 w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-dark-200 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition" 
                                            placeholder="Doe" required>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="prenom" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Name</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-signature text-gray-400"></i>
                                        </div>
                                        <input type="text" id="prenom" name="prenom" value="<?=isset($user)?$prenom:""?>" 
                                            class="pl-10 w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-dark-200 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition" 
                                            placeholder="John" required>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-envelope text-gray-400"></i>
                                        </div>
                                        <input type="email" id="email" name="email" value="<?=isset($user)?$email:""?>" 
                                            class="pl-10 w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-dark-200 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent transition" 
                                            placeholder="john.doe@example.com" required>
                                    </div>
                                </div>
                                
                                <div class="pt-2">
                                    <?php if(isset($id)): ?>
                                        <button type="submit" name="modifier" 
                                            class="w-full bg-gradient-to-r from-primary-500 to-primary-600 text-white py-3 px-4 rounded-lg font-medium hover:from-primary-600 hover:to-primary-700 transition duration-300 shadow-md">
                                            <i class="fas fa-save mr-2"></i> Update User
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="ajouter" 
                                            class="w-full bg-gradient-to-r from-green-500 to-teal-500 text-white py-3 px-4 rounded-lg font-medium hover:from-green-600 hover:to-teal-600 transition duration-300 shadow-md">
                                            <i class="fas fa-plus-circle mr-2"></i> Add User
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- User Table -->
                    <div class="lg:col-span-2">
                        <div class="bg-white dark:bg-dark-100 rounded-xl shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">All Users</h2>
                                <div class="flex items-center space-x-3">
                                    <?php if(!empty($search)): ?>
                                        <div class="text-sm text-gray-500 dark:text-gray-300">
                                            Search results for: <span class="font-medium"><?= htmlspecialchars($search) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <form method="GET" action="index.php" class="relative">
                            <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" 
                                class="pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-dark-200 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            <?php if(!empty($search)): ?>
                                <a href="index.php" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </form>
                                    <a href="index.php" class="px-3 py-2 text-sm rounded-lg bg-primary-600 text-white hover:bg-primary-700 transition flex items-center">
                                        <i class="fas fa-sync-alt mr-2"></i> Reset
                                    </a>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-dark-200">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                User
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Email
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Status
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-dark-100 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php if(count($users) > 0): ?>
                                            <?php foreach($users as $user): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-dark-200 transition">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center">
                                                            <?= strtoupper(substr($user->prenom, 0, 1) . strtoupper(substr($user->nom, 0, 1)))?>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-medium text-gray-900 dark:text-white"><?=$user->prenom?> <?=$user->nom?></div>
                                                            <div class="text-sm text-gray-500 dark:text-gray-300">ID: <?=$user->id?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900 dark:text-white"><?=$user->email?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Active
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div class="flex justify-end space-x-3">
                                                        <a href="index.php?idUser=<?=$user->id?><?=!empty($search) ? '&search='.urlencode($search) : ''?><?=isset($_GET['page']) ? '&page='.$_GET['page'] : ''?>" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300 inline-flex items-center">
                                                            <i class="fas fa-edit mr-1"></i> Edit
                                                        </a>
                                                        <a href="index.php?idUserDelete=<?=$user->id?><?=!empty($search) ? '&search='.urlencode($search) : ''?><?=isset($_GET['page']) ? '&page='.$_GET['page'] : ''?>" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 inline-flex items-center delete-user-btn" data-user-name="<?= htmlspecialchars($user->prenom . ' ' . $user->nom) ?>">
                                                            <i class="fas fa-trash-alt mr-1"></i> Delete
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                    <?php if(!empty($search)): ?>
                                                        No users found matching your search criteria.
                                                    <?php else: ?>
                                                        No users found. Add your first user!
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if($totalPages > 1): ?>
                            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                <div class="text-sm text-gray-500 dark:text-gray-300">
                                    Showing <span class="font-medium"><?= (($page - 1) * $perPage) + 1 ?></span> to <span class="font-medium"><?= min($page * $perPage, $totalRecords) ?></span> of <span class="font-medium"><?= $totalRecords ?></span> results
                                </div>
                                <div class="flex space-x-2">
                                    <?php if($page > 1): ?>
                                        <a href="index.php?page=<?= $page - 1 ?><?=!empty($search) ? '&search='.urlencode($search) : ''?>" class="px-3 py-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-dark-200 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-dark-300 transition">
                                            Previous
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-dark-200 text-gray-700 dark:text-gray-300 opacity-50 cursor-not-allowed">
                                            Previous
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if($page < $totalPages): ?>
                                        <a href="index.php?page=<?= $page + 1 ?><?=!empty($search) ? '&search='.urlencode($search) : ''?>" class="px-3 py-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-dark-200 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-dark-300 transition">
                                            Next
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-dark-200 text-gray-700 dark:text-gray-300 opacity-50 cursor-not-allowed">
                                            Next
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Notification system
        document.querySelectorAll('[data-dismiss="notification"]').forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.notification').remove();
            });
        });

        // Delete confirmation modal system
        const deleteModal = document.getElementById('deleteModal');
        const deleteButtons = document.querySelectorAll('.delete-user-btn');
        const confirmDeleteBtn = document.getElementById('confirmDelete');
        const cancelDeleteBtn = document.getElementById('cancelDelete');
        const closeModalBtn = document.getElementById('closeModal');
        let currentDeleteLink = null;
        let currentUserName = null;

        // Show modal when delete button is clicked
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                currentDeleteLink = this.href;
                currentUserName = this.getAttribute('data-user-name');
                
                // Update modal message with user's name
                document.querySelector('#deleteModal p').textContent = 
                    `Are you sure you want to delete ${currentUserName}? This action cannot be undone.`;
                
                // Show modal with animation
                deleteModal.classList.add('show');
            });
        });

        // Close modal functions
        function closeModal() {
            deleteModal.classList.remove('show');
        }

        closeModalBtn.addEventListener('click', closeModal);
        cancelDeleteBtn.addEventListener('click', closeModal);

        // Confirm deletion
        confirmDeleteBtn.addEventListener('click', () => {
            if (currentDeleteLink) {
                window.location.href = currentDeleteLink;
            }
        });

        // Close modal when clicking outside
        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) {
                closeModal();
            }
        });

        // Auto-dismiss notifications after 5 seconds
        setTimeout(() => {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.remove();
            });
        }, 5000);

    // Enhanced Dark/Light mode toggle
const themeToggle = document.getElementById('theme-toggle');
const htmlElement = document.documentElement;

// Function to set theme with animation
function setTheme(theme) {
    if (theme === 'dark') {
        htmlElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
        
        // Add animation class
        htmlElement.classList.add('theme-transition');
        setTimeout(() => {
            htmlElement.classList.remove('theme-transition');
        }, 500);
    } else {
        htmlElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
        
        // Add animation class
        htmlElement.classList.add('theme-transition');
        setTimeout(() => {
            htmlElement.classList.remove('theme-transition');
        }, 500);
    }
}

// Check for saved user preference or system preference
function checkThemePreference() {
    const savedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    if (savedTheme) {
        setTheme(savedTheme);
    } else if (systemPrefersDark) {
        setTheme('dark');
    } else {
        setTheme('light');
    }
}

// Initialize theme
checkThemePreference();

// Listen for toggle button click
themeToggle.addEventListener('click', () => {
    const isDark = htmlElement.classList.contains('dark');
    setTheme(isDark ? 'light' : 'dark');
});

// Watch for system theme changes
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
    if (!localStorage.getItem('theme')) {
        setTheme(e.matches ? 'dark' : 'light');
    }
});

// Add smooth transition for theme change
const style = document.createElement('style');
style.textContent = `
    .theme-transition * {
        transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
    }
`;
document.head.appendChild(style);
        // Focus search input when pressing Ctrl+K
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                }
            }
        });
    </script>
</body>
</html>