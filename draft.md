# Draft Implementation Plan - Immediate Next Steps

Based on the structure.md and todo list.md, the immediate next steps to get the advertisement system started are:

1. **Database Setup:** ✅
    - [x] Review and finalize the database schema defined in structure.md, especially for ad_positions, advertisements, and ad_statistics tables.
    - [x] Create the database and tables in MySQL using the provided SQL schema. This might involve using a tool like phpMyAdmin or executing SQL commands directly.
    - [x] Configure the database connection in `config/database.php` with the correct credentials (host, username, password, database name).

2. **Basic MVC Framework Setup:** ✅
    - [x] Create core MVC files and directories if they are not already present:
        - `src/Models/` (for Model classes)
        - `src/Controllers/` (for Controller classes)
        - `templates/` (for View templates)
    - [x] Ensure the autoloader in `index.php` is correctly set up to load classes from `src/Controllers`, `src/Models`, `src/Services`, and `src/Utils` directories based on their namespaces (e.g., `App\Controllers`, `App\Models`, etc.).
    - [x] Create a basic `BaseController.php` in `src/Controllers/` that can be extended by other controllers to handle common functionalities.
    - [x] Create a basic `BaseModel.php` in `src/Models/` for common model functionalities and database interactions.

3. **Admin Authentication Setup:** ✅
    - [x] Implement basic admin authentication to protect the admin panel.
    - [x] Create an `AdminController.php` with a login action and a dashboard action.
    - [x] Create login and dashboard view templates in `templates/admin/`.
    - [x] Configure routes in `config/routes.php` for admin login and dashboard, pointing to the `AdminController`.

4. **Ad Position Management (Admin Panel - Basic CRUD):** ✅
    - [x] Create `AdPosition.php` model in `src/Models/` to interact with the `ad_positions` table. Implement basic CRUD operations (Create, Read, Update, Delete).
    - [x] Implement Ad Position management actions in `AdminController.php` (e.g., `listPositions`, `createPosition`, `editPosition`, `deletePosition`).
    - [x] Create view templates in `templates/admin/` for listing, creating, and editing ad positions.
    - [x] Configure routes in `config/routes.php` to map URLs like `/admin/positions`, `/admin/positions/create`, `/admin/positions/edit/{id}`, etc., to the corresponding actions in `AdminController`.

5. **Implement Admin Authentication Logic:** ✅
    - [x] Implement actual admin login authentication logic in `AdminController.php`, to verify admin credentials against the `users` table.
    - [x] Implement session management using PHP sessions to maintain admin login state.
    - [x] Secure admin routes in `AdminController.php` to only allow authenticated admin users to access them.
    - [x] Implement logout functionality in `AdminController.php`.

6. **Test Ad Position CRUD Operations:** ✅ 
    - [x] Access the Ad Positions list in the admin panel (`/admin/positions`).
    - [x] Test creating new ad positions, editing existing ones, and deleting ad positions through the admin panel UI.
    - [x] Verify that the CRUD operations are working correctly and data is being saved and retrieved from the `ad_positions` table.

7. **Implement Ad Position View in Admin Dashboard:** ✅
    - [x] Add a link to "Ad Positions" in the admin dashboard (`templates/admin/dashboard.php`).
    - [x] Ensure the link correctly points to the Ad Positions list page (`/admin/positions`).

8. **Admin User Creation during Installation:** ✅
    - [x] Modify `install.php` to include form fields for admin username, email, and password.
    - [x] Modify `api/v1/install_api.php` to handle admin user creation during installation, including password hashing and database insertion.
    - [x] Update the installation success message to inform the user about the admin credentials.

9. **Advertisement Management (Admin Panel - Basic CRUD):** ✅
    - [x] Create `Advertisement.php` model in `src/Models/` to interact with the `advertisements` table. Implement basic CRUD operations (Create, Read, Update, Delete).
    - [x] Implement Advertisement management actions in `AdminController.php` (e.g., `listAds`, `createAd`, `editAd`, `deleteAd`) (List Ads, Create Ad, Edit Ad, and Delete Ad actions are done).
    - [x] Create view templates in `templates/admin/` for listing, creating, and editing advertisements (List, Create, and Edit forms are done).
    - [x] Configure routes in `config/routes.php` to map URLs like `/admin/advertisements`, `/admin/advertisements/create`, `/admin/advertisements/edit/{id}`, etc., to the corresponding actions in `AdminController`.
    - [x] Add a link to "Advertisements" in the admin dashboard (`templates/admin/dashboard.php`).

10. **Advertiser Panel - Basic Functionality**: ✅
    1. **Advertiser Dashboard:** ✅
        - [x] Create `AdvertiserController.php` if it doesn't exist.
        - [x] Implement `dashboard` action in `AdvertiserController.php`.
        - [x] Create `templates/advertiser/dashboard.php` view template.
        - [x] Configure route for `/advertiser/dashboard` in `config/routes.php`.
        - [x] Add a link to "Advertiser Dashboard" in the advertiser layout (`templates/advertiser/layout.php`).
    
    2. **Advertiser Ad Management (List Ads):** ✅
        - [x] Implement `listAds` action in `AdvertiserController.php`.
        - [x] Create `templates/advertiser/ads_list.php` view template.
        - [x] Configure route for `/advertiser/ads` in `config/routes.php`.
        - [x] Add a link to "My Ads" or "Ads" in the advertiser dashboard (`templates/advertiser/dashboard.php`).
    
    3. **Advertiser Ad Management (Create Ads):** ✅
        - [x] Implement `createAd` action in `AdvertiserController.php`.
        - [x] Create `templates/advertiser/ads_create.php` view template.
        - [x] Configure route for `/advertiser/create-ad` in `config/routes.php`.
        - [x] Add a link to "Create Ad" in the advertiser dashboard or in the "My Ads" list page.
        - [x] Implement redirection to canvas tool after ad creation.
        - [x] Implement canvas functionality for ad design.

## Next Steps

11. **Ad Serving and Tracking**:
    1. **Impression Tracking:**
        - [ ] Create a tracking endpoint API at `/api/v1/track` to record ad impressions.
        - [ ] Implement the impression tracking logic in `TrackingController.php`.
        - [ ] Create database model and methods for storing impression data.
        - [ ] Update ad serving code to trigger impression tracking.
    
    2. **Click Tracking:**
        - [ ] Add click tracking to track when users click on ads.
        - [ ] Create redirection mechanism through the tracking system.
        - [ ] Implement conversion attribution.
    
    3. **Ad Serving Logic:**
        - [ ] Create an ad serving endpoint at `/api/v1/serve` to deliver ads to websites.
        - [ ] Implement ad selection algorithm based on targeting criteria (location, device, time).
        - [ ] Create a JavaScript client for websites to embed ads.
        - [ ] Implement ad rotation and A/B testing capabilities.

12. **Admin Analytics Dashboard**:
    1. **Basic Analytics Views:**
        - [ ] Create analytics dashboard in admin panel.
        - [ ] Implement impression and click reports with filtering options.
        - [ ] Add charts and visualizations for key metrics.
    
    2. **Advanced Analytics:**
        - [ ] Implement data aggregation for performance over time.
        - [ ] Add user segment analysis.
        - [ ] Create advertiser-specific reporting views.

13. **Geographic Targeting**:
    1. **Location Detection:**
        - [ ] Implement IP-based geolocation using the specified API: https://whois.pconline.com.cn/ipJson.jsp.
        - [ ] Store location data with impressions and clicks.
    
    2. **Location Targeting:**
        - [ ] Add location targeting options in ad creation.
        - [ ] Implement the targeting logic in ad serving algorithm.
        - [ ] Create location analytics in the admin dashboard.

## Immediate Implementation Focus:

For the next development sprint, we'll focus on Item 11.1: Impression Tracking, which is the foundation for all analytics functionality. This will include:

1. **Database Schema for Impressions:** ✓
   - We already have the `impressions` table defined in our schema.

2. **Create Tracking API:**
   - [ ] Create `/api/v1/track.php` file for handling impression tracking requests.
   - [ ] Implement validation, security measures, and CORS policies.
   - [ ] Create request logging to prevent fraud and abuse.

3. **Create Tracking Model:**
   - [ ] Create `Impression.php` model for managing impression data.
   - [ ] Implement methods for creating and retrieving impression data.
   - [ ] Add aggregation methods for analytics.

4. **Update Ad Serving Code:**
   - [ ] Create the ad serving endpoint at `/api/v1/serve.php`.
   - [ ] Implement the logic to select appropriate ads based on position and targeting.
   - [ ] Include impression tracking pixel/script in served ads.

5. **JavaScript Client:**
   - [ ] Create client-side JavaScript for websites to embed (`adclient.js`).
   - [ ] Implement ad loading and rendering.
   - [ ] Add event tracking for impressions and clicks.

This sprint will focus on items 2-5 above, as we already have the database schema in place.
