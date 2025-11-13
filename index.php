<?php 
    // Check if user is logged in (example logic)
    $is_logged_in = false; // Set based on your authentication logic
    $user_name = "John Doe"; // Set based on your user session
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finn Hustle - Your Platform</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f5f5f5;
        }

        /* Navbar Styles */
        nav {
            background-color: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo img {
            height: 40px;
            width: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .nav-center {
            flex: 1;
            display: flex;
            justify-content: center;
            gap: 30px;
        }

        .nav-center a {
            color: #555;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-center a:hover {
            color: #0066cc;
        }

        .user-profile {
            position: relative;
        }

        .user-btn {
            background-color: #0066cc;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s ease;
        }

        .user-btn:hover {
            background-color: #0052a3;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #666;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 200px;
            margin-top: 10px;
            display: none;
            z-index: 2000;
        }

        .dropdown-menu.active {
            display: block;
        }

        .dropdown-menu a,
        .dropdown-menu button {
            display: block;
            width: 100%;
            text-align: left;
            padding: 12px 16px;
            border: none;
            background: none;
            cursor: pointer;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.2s ease;
        }

        .dropdown-menu a:hover,
        .dropdown-menu button:hover {
            background-color: #f0f0f0;
        }

        .dropdown-menu a:first-child,
        .dropdown-menu button:first-child {
            border-radius: 8px 8px 0 0;
        }

        .dropdown-menu a:last-child,
        .dropdown-menu button:last-child {
            border-radius: 0 0 8px 8px;
        }

        .dropdown-divider {
            height: 1px;
            background-color: #e0e0e0;
            margin: 4px 0;
        }

        /* Main Container */
        .container {
            display: flex;
            height: calc(100vh - 70px);
            width: 100%;
        }

        /* Two-Column Layout */
        .column {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background-color: #f5f5f5;
        }

        .column:first-child {
            border-right: 1px solid #ddd;
            background: linear-gradient(135deg, #f5f7fa 0%, #ffffff 100%);
        }

        .column:last-child {
            background: linear-gradient(135deg, #ffffff 0%, #f5f7fa 100%);
        }

        .column-content {
            text-align: center;
            max-width: 100%;
        }

        .column-image {
            width: 250px;
            height: 250px;
            border-radius: 12px;
            object-fit: cover;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            background-color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 14px;
        }

        .column h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }

        .column p {
            color: #666;
            margin-bottom: 20px;
            max-width: 350px;
            line-height: 1.6;
        }

        .column-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .btn-primary {
            background-color: #0066cc;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0052a3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.3);
        }

        .btn-secondary {
            background-color: transparent;
            color: #0066cc;
            border: 2px solid #0066cc;
        }

        .btn-secondary:hover {
            background-color: #0066cc;
            color: white;
            transform: translateY(-2px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                height: auto;
            }

            .column {
                border-right: none;
                border-bottom: 1px solid #ddd;
                padding: 30px 20px;
            }

            .column:last-child {
                border-bottom: none;
            }

            .nav-center {
                display: none;
            }

            nav {
                padding: 0 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav>
        <div class="logo">
            <span>🚀</span>
            <span>Finn Hustle</span>
        </div>

        <div class="nav-center">
            <a href="#home">Home</a>
            <a href="#about">About</a>
            <a href="#services">Services</a>
            <a href="#contact">Contact</a>
        </div>

        <div class="user-profile">
            <button class="user-btn" onclick="toggleDropdown()">
                <div class="user-avatar"><?php echo substr($user_name, 0, 1); ?></div>
                <span><?php echo $is_logged_in ? $user_name : 'Menu'; ?></span>
                <span>▼</span>
            </button>

            <div class="dropdown-menu" id="dropdownMenu">
                <?php if ($is_logged_in): ?>
                    <a href="#profile">Profile</a>
                    <a href="#settings">Settings</a>
                    <a href="#dashboard">Dashboard</a>
                    <div class="dropdown-divider"></div>
                    <a href="#logout">Logout</a>
                <?php else: ?>
                    <a href="#login">Login</a>
                    <a href="#signup">Sign Up</a>
                    <a href="#about">About Us</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container">
        <!-- Left Column -->
        <div class="column">
            <div class="column-content">
                <div class="column-image">
                    <img src="https://via.placeholder.com/250x250?text=Feature+1" alt="Feature 1" style="width: 100%; height: 100%; border-radius: 12px;">
                </div>
                <h2>Feature One</h2>
                <p>Discover amazing capabilities with our first feature. Designed to enhance your productivity and streamline your workflow.</p>
                <div class="column-buttons">
                    <button class="btn btn-primary">Explore</button>
                    <button class="btn btn-secondary">Learn More</button>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="column">
            <div class="column-content">
                <div class="column-image">
                    <img src="https://via.placeholder.com/250x250?text=Feature+2" alt="Feature 2" style="width: 100%; height: 100%; border-radius: 12px;">
                </div>
                <h2>Feature Two</h2>
                <p>Unlock powerful tools designed for modern professionals. Experience seamless integration and exceptional performance across all platforms.</p>
                <div class="column-buttons">
                    <button class="btn btn-primary">Get Started</button>
                    <button class="btn btn-secondary">See Demo</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle dropdown menu
        function toggleDropdown() {
            const dropdownMenu = document.getElementById('dropdownMenu');
            dropdownMenu.classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userProfile = document.querySelector('.user-profile');
            const dropdownMenu = document.getElementById('dropdownMenu');
            
            if (!userProfile.contains(event.target)) {
                dropdownMenu.classList.remove('active');
            }
        });

        // Handle button clicks
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function() {
                console.log('Button clicked:', this.textContent);
                // Add your button logic here
            });
        });
    </script>
</body>
</html>

