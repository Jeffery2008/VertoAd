# VertoAD2 - Easily Manage Your Website Ads 🚀

VertoAD2 is a simple and easy-to-use ad management system designed for website owners. It helps you easily manage ads on your website, view ad performance, manage users, and check error reports.

## Key Features ✨

-   **Easy Ad Management:** Create, manage, and publish ads with simple operations. 🖱️
-   **Real-time Performance Tracking:** Understand your ads' impressions, clicks, and revenue through detailed statistics. 📈
-   **Convenient User Management:** Easily add, modify, and delete user accounts. 👤
-   **Activation Key Control:** Generate and manage activation keys to ensure system security. 🔑
-   **Error Monitoring:** Stay informed about system errors and resolve them quickly. ⚠️
-   **API (Advanced):** If needed, you can integrate VertoAD2 with other systems through the API. 🔗

## Features

-   **Publisher Dashboard:**
    -   Manage Ad Placements: Easily create, edit, and organize your ad placements.
    -   View Detailed Statistics: Understand ad impressions, clicks, and revenue.
-   **Admin Dashboard:**
    -   Manage User Accounts: Easily add, modify, and delete users.
    -   Generate and Manage Activation Keys: Securely control system access.
    -   Monitor System Errors and Reports: Quickly discover and resolve issues.
-   **API (Advanced):**
    -   If you are a technical user, you can use the API.
    -   Includes the following:
        -   Login status check, logout
        -   Administrator data and user management
        -   Error reporting

## System Structure (If You're Interested) 🏗️

VertoAD2 uses a clear structure for easy understanding and maintenance.

-   `app/`: Core code.
    -   `Config/`: Configuration files.
    -   `Controllers/`: Handles user requests.
    -   `Core/`: Core classes.
    -   `Models/`: Data models.
    -   `Views/`: Web page templates.
-   `public/`: Publicly accessible files.
    -   `index.php`: System entry point.
    -   `ad-editor.html`, `embed.js`: Frontend code related to ad management.
    -   `admin/`, `publisher/`: Resources for the admin and publisher dashboards.
-   `tests/`: Test code.
-   `data/`: Database installation file (`ad_system.sql`).
-   `logs/`: System logs.
-   `config/`: Configuration files.

## Installation 💾

Installing VertoAD2 is very simple! Detailed installation instructions are in the `public/install.php` file.

## Getting Started 🏁

1.  **Log in:** Access the admin or publisher dashboard using your credentials.
2.  **Create a Placement:** (Publisher) Go to the "Placements" section and create a new ad placement.
3.  **Get the Code:** Copy the provided code snippet.
4.  **Embed the Code:** Paste the code into your website where you want the ad to appear.
5.  **Manage Users:** (Admin) Add and manage user accounts in the "Users" section.

## Technology (If You're Interested) 💻

-   **PHP:** The main server-side language.
-   **MySQL:** The database used to store data.
-   **HTML/CSS/JavaScript:** Used to create web page interfaces.
-   **RESTful API:** For programmatic access (advanced).
