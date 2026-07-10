-- CampusNav Database Schema
-- Run this in phpMyAdmin (or via CLI) after creating a database called "campusnav"

CREATE TABLE floors (
    floor_id INT AUTO_INCREMENT PRIMARY KEY,
    floor_name VARCHAR(50) NOT NULL,       -- e.g. "Ground Floor", "Level 1"
    floor_order INT NOT NULL,              -- 0, 1, 2... for sorting/stacking
    building VARCHAR(50) NOT NULL DEFAULT 'Main Building',
    map_image VARCHAR(255),                -- filename in assets/maps/, e.g. "main_ground_left.png"
    wing VARCHAR(50)                       -- e.g. "Left Wing", "Central", "PADU"
);

CREATE TABLE nodes (
    node_id INT AUTO_INCREMENT PRIMARY KEY,
    floor_id INT NOT NULL,
    room_code VARCHAR(50),                 -- e.g. "MK-204"
    node_name VARCHAR(100),                -- e.g. "Chemistry Lab", "Stairs A", "Lift 1"
    node_type ENUM('room','junction','stairs','lift','entrance') NOT NULL,
    x_coord FLOAT NOT NULL,                -- position on the floor map image
    y_coord FLOAT NOT NULL,
    description TEXT,
    category VARCHAR(50),                  -- optional tag, e.g. "restroom", "surau"
    FOREIGN KEY (floor_id) REFERENCES floors(floor_id)
);

CREATE TABLE edges (
    edge_id INT AUTO_INCREMENT PRIMARY KEY,
    node_a INT NOT NULL,
    node_b INT NOT NULL,
    weight FLOAT NOT NULL,             -- distance/cost, used by Dijkstra
    FOREIGN KEY (node_a) REFERENCES nodes(node_id),
    FOREIGN KEY (node_b) REFERENCES nodes(node_id)
);

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    node_id INT,
    issue_type VARCHAR(50),
    description TEXT,
    status ENUM('pending','reviewed','resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (node_id) REFERENCES nodes(node_id)
);

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE search_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    query VARCHAR(255) NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- To create a test admin account, register normally through index.php with
-- role handling, OR run this PHP snippet once to generate a real bcrypt hash,
-- then paste it into an INSERT statement yourself:
--
--   <?php echo password_hash('yourpassword', PASSWORD_DEFAULT); ?>
--
-- Don't hardcode a hash you haven't generated yourself — it won't match.
