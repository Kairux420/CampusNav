# CampusNav

CampusNav is an intelligent, interactive indoor navigation web application designed to help students, faculty, and visitors effortlessly navigate complex campus buildings.

## Features

- **Interactive Maps**: High-resolution, zoomable floor plans using Leaflet.js with a custom coordinate system.
- **Intelligent Search**: Quickly find rooms, labs, cafeterias, and staff offices. Filter by building, wing, and floor.
- **Smart Routing**: Generates step-by-step navigational routes including floor transitions (stairs, elevators).
- **AI Assistant**: Integrated Gemini AI chatbot that allows users to ask natural language questions like "Where is the nearest restroom?" or "I'm looking for Mr. John's office."
- **Admin Dashboard**: Secure admin area to visually add, edit, and link navigation nodes directly on the map via a drag-and-drop editor.
- **Issue Reporting**: Users can report inaccuracies or map issues directly to administrators.

## Technologies Used

- **Frontend**: HTML5, Vanilla JavaScript, CSS3 (Custom Glassmorphism Design System)
- **Map Rendering**: Leaflet.js (L.CRS.Simple for custom image overlays)
- **Backend**: PHP 8+
- **Database**: MySQL (PDO)
- **AI Integration**: Google Gemini API via cURL

## Installation & Setup

1. **Clone the repository** to your local web server directory (e.g., `htdocs` for XAMPP).
2. **Database Setup**:
   - Create a MySQL database (e.g., `campusnav`).
   - Import the provided `schema.sql` (if available) or construct the database using the internal tools.
3. **Configuration**:
   - Open `config/db.php` and update your database credentials.
   - Open `includes/gemini_helper.php` and insert your Gemini API key.
4. **Access the Application**:
   - Navigate to `http://localhost/campusnav` in your web browser.

## Default Roles

- **Guest**: View-only access to maps and routing.
- **Student/User**: Standard access, able to save profile settings and report issues.
- **Admin**: Full access to the Admin Dashboard (`/admin`), Node Editor, and Analytics. (You can upgrade a user to admin via the database).

## License

This project is for educational and campus utility purposes. All rights reserved.
