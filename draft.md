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

11. **Ad Serving and Tracking**:
    1. **Impression Tracking:** ✅
        - [x] Create a tracking endpoint API at `/api/v1/track.php` to record ad impressions.
        - [x] Create database model and methods for storing impression data.
        - [x] Add location and device detection to impression tracking.
    
    2. **Click Tracking:** ✅
        - [x] Create `Click.php` model for tracking click data.
        - [x] Create click tracking endpoint at `/api/v1/click.php`.
        - [x] Implement click redirection and tracking.
    
    3. **Ad Serving Logic:** ✅
        - [x] Create an ad serving endpoint at `/api/v1/serve.php` to deliver ads to websites.
        - [x] Implement ad selection based on position and basic targeting.
        - [x] Create a JavaScript client (`adclient.js`) for websites to embed ads.
        - [x] Implement impression and click tracking in the client.

12. **Admin Analytics Dashboard**:
    1. **Basic Analytics Views:** ✅
        - [x] Create `AnalyticsController.php` with dashboard action.
        - [x] Implement impression and click analytics methods.
        - [x] Create dashboard template with filtering options.
        - [x] Add charts and visualizations using Chart.js.
    
    2. **Advanced Analytics:**
        - [ ] Implement data aggregation for performance over time.
        - [ ] Add user segment analysis.
        - [ ] Create advertiser-specific reporting views.

13. **Geographic Targeting**:
    1. **Location Detection:** ✅
        - [x] Implement IP-based geolocation using the specified API: https://whois.pconline.com.cn/ipJson.jsp.
        - [x] Store location data with impressions and clicks.
    
    2. **Location Targeting:** ✅
        - [x] Add location targeting options in ad creation.
        - [x] Implement the targeting logic in ad serving algorithm.
        - [x] Create location analytics in the admin dashboard.

## Immediate Implementation Focus:

### Completed Tasks:

1. ✅ Created `AnalyticsController.php` with:
   - Dashboard display functionality
   - Summary metrics calculation
   - CSV export capability
   - Access control for admin/advertiser
   - Data filtering and aggregation

2. ✅ Created Analytics Dashboard Template:
   - Summary cards for key metrics
   - Time series charts for impressions and clicks
   - Geographic distribution map
   - Device distribution chart
   - Data tables for detailed metrics
   - Filtering controls

3. ✅ Implemented JavaScript Functionality:
   - Chart.js initialization
   - Dynamic data loading
   - Filter handling
   - Chart updates
   - Geographic visualization with Leaflet

4. ✅ Added Route Configuration:
   - Analytics dashboard routes
   - Data export routes
   - Access control middleware

5. ✅ Completed Integration:
   - Added analytics links to admin dashboard
   - Added analytics links to advertiser dashboard
   - Implemented data refresh functionality

6. ✅ Implemented Location Targeting:
   - Created `AdTargeting` model to handle targeting criteria
   - Added location targeting options to ad creation form
   - Updated ad serving logic to consider location targeting
   - Created database schema for ad targeting
   - Improved geolocation detection with fallback to Accept-Language header
   - Added targeting validation in ad serving API

7. ✅ Implemented Device Targeting:
   - Added device targeting options (Desktop, Mobile, Tablet) to ad creation form
   - Updated AdvertiserController to process device targeting data
   - Integrated with existing AdTargeting model and ad serving infrastructure
   - Ensured device detection works correctly in impression and click tracking

8. ✅ Implemented Time-based Targeting:
   - Added day-of-week selection to ad creation form
   - Added time range selection (start and end times) to ad creation form
   - Updated AdvertiserController to process time-based targeting data 
   - Enhanced AdTargeting model to handle day and time range checks
   - Updated ad serving API to include current day and time in targeting criteria
   - Implemented time range validation in the ad matching logic

9. ✅ Implemented Browser and OS Targeting:
   - Added browser selection options (Chrome, Firefox, Safari, IE, Opera) to ad creation form
   - Added OS selection options (Windows, Mac OS, Linux, Android, iOS) to ad creation form
   - Updated AdvertiserController to process browser and OS targeting data
   - Enhanced ad serving API to include browser and OS information in targeting criteria
   - Leveraged existing targeting infrastructure for browser and OS matching

10. ✅ Implemented Ad Review System:
    - Created database schema for ad reviews, violation types, and review logs
    - Implemented `AdReview`, `ViolationType`, and `AdReviewLog` models
    - Created `AdReviewService` to handle review workflow
    - Added review functionality to `AdminController`
    - Created templates for pending reviews, individual review, review history, and violation type management
    - Implemented approval and rejection workflows with comments and violation categorization
    - Added audit logging for all review actions
    - Integrated the review system with the existing ad management functionality

11. ✅ Created Performance Optimization Foundation:
    - Created the SQL script with database indexes and optimizations (`sql/performance_optimizations.sql`)
    - Implemented the `Cache` utility class for file-based caching (`src/Utils/Cache.php`)
    - Added the `system_config` table for storing cache configuration
    - Added database query optimizations with proper indexes
    - Created materialized views and summary tables for analytics

## Recently Completed Tasks

### Error Tracking System
- ✅ Created database migration for error tracking tables (`error_logs`, `error_categories`, `error_notification_subscriptions`, `error_notifications`)
- ✅ Implemented `ErrorLog` model with methods for logging, retrieving, and managing error logs
- ✅ Created `ErrorLogger` utility class for centralized error logging across the application
- ✅ Added `ErrorNotifier` utility for sending error notifications to subscribed users
- ✅ Implemented `ErrorController` to handle error management in the admin area
- ✅ Created admin templates for error dashboard, error logs list, error detail view
- ✅ Added navigation links in the admin sidebar for the Error Tracking System

### Project Renaming and Namespace Updates
- ✅ Created `update_namespace.php` script to automate the process of updating namespaces and references
- ✅ Identified all uses of the old namespace (`HFI\UtilityCenter`) across the codebase
- ✅ Updated TODO list to include namespace migration tasks
- ✅ Completed: Converting all namespace references to the new `VertoAD\Core` namespace
- ✅ Completed: Updating JavaScript references and files from `HFI` prefix to `VertoAD`
- ✅ Completed: Renaming configuration values and application name references

## Next Steps

### ✅ Namespace Migration Completed
- ✅ Run the `update_namespace.php` script to automatically update references
- ✅ Test key components after namespace update:
  - Auth and security services
  - API endpoints
  - JavaScript functionality 
  - Admin and advertiser interfaces
- ✅ Fix any issues arising from the namespace changes
- ✅ Update documentation to reflect the new project name

### Implement Advanced Analytics and Conversion Tracking
- Continue development of conversion tracking functionality
- Implement user segmentation and audience analysis
- Develop ROI calculation and performance metrics
- Create automated insight generation

### Performance Optimization
- Integrate caching mechanisms with the ad serving API
- Implement caching for analytics data
- Optimize database queries with proper indexes
- Add HTTP caching headers and response compression

## Revised Implementation Plan - Implement Remaining Features Before Cache Integration

Based on the review of structure.md and todo list.md, the following features need to be implemented before focusing on cache integration:

### 1. Error Tracking System: (First Priority)
- [ ] **Create Error Logger and Database Structure:**
  - [ ] Create `ErrorLog` model in `src/Models/` for error storage
  - [ ] Create database table `error_logs` with fields for error type, message, file, line, stack trace, user info, timestamp, etc.
  - [ ] Implement `ErrorLogger` class in `src/Utils/` with static methods for logging different types of errors
  - [ ] Create global error handler to catch and log PHP errors and exceptions
  - [ ] Implement custom exception classes for different error categories

- [ ] **Implement Error Dashboard:**
  - [ ] Create `ErrorController.php` for error management
  - [ ] Create templates for error dashboard with filters and search functionality
  - [ ] Implement visualizations for error trends
  - [ ] Add features to mark errors as resolved or ignored
  - [ ] Implement notification system for critical errors (email, SMS, etc.)

- [ ] **Error Reporting API:**
  - [ ] Create API endpoint at `/api/v1/error-report.php` for client-side error reporting
  - [ ] Implement client-side JavaScript for capturing and reporting errors
  - [ ] Add rate limiting to prevent API abuse
  - [ ] Implement error categorization and priority assignment

### 2. Pricing and Billing System: (Second Priority)
- [ ] **Implement Pricing Models:**
  - [ ] Create `PricingModel` class in `src/Models/` with different pricing strategies (CPM, CPC, time-based, etc.)
  - [ ] Create admin interface to configure pricing for ad positions
  - [ ] Implement time-based pricing variations (peak hours, weekends, etc.)
  - [ ] Add support for discounts and promotional pricing

- [ ] **Order and Billing System:**
  - [ ] Create `Order` and `Invoice` models
  - [ ] Implement order creation process for advertisers
  - [ ] Create order management interface in admin panel
  - [ ] Add invoice generation functionality
  - [ ] Implement payment processing or integration with payment gateways

- [ ] **Budget Management:**
  - [ ] Add budget tracking for advertisers
  - [ ] Implement automatic campaign pausing when budget is depleted
  - [ ] Create alerts for budget thresholds
  - [ ] Add budget forecasting tools

### 3. Security Enhancements: (Third Priority)
- [ ] **CSRF Protection:**
  - [ ] Implement CSRF token generation and validation
  - [ ] Add CSRF tokens to all forms and AJAX requests
  - [ ] Create middleware for CSRF validation

- [ ] **API Security:**
  - [ ] Implement API key authentication system
  - [ ] Create API key management interface in admin panel
  - [ ] Add rate limiting for API endpoints
  - [ ] Implement request logging and monitoring

- [ ] **Data Protection:**
  - [ ] Add data encryption for sensitive information
  - [ ] Implement secure cookie handling
  - [ ] Add input validation and sanitization
  - [ ] Create security audit logs

### 4. Advanced Analytics Capabilities: (Fourth Priority)
- [ ] **Conversion Tracking:**
  - [ ] Create `Conversion` model and database table
  - [ ] Implement conversion tracking pixel or JavaScript
  - [ ] Add conversion attribution logic
  - [ ] Create conversion reporting interface

- [ ] **User Segmentation:**
  - [ ] Implement user segmentation based on behavior
  - [ ] Create audience insights dashboard
  - [ ] Add demographic analysis tools
  - [ ] Implement A/B testing analysis

- [ ] **ROI and Performance Metrics:**
  - [ ] Add ROI calculation for campaigns
  - [ ] Implement performance forecasting tools
  - [ ] Create benchmark comparisons
  - [ ] Add automated insights and recommendations

### 5. Cache Mechanism Integration: (After Above Features)
- [ ] **Ad Serving API Cache:**
  - [ ] Integrate Cache utility with serve.php
  - [ ] Implement position-based ad caching
  - [ ] Add targeting criteria caching
  - [ ] Ensure proper cache invalidation

- [ ] **Analytics Data Caching:**
  - [ ] Cache dashboard summary statistics
  - [ ] Implement report caching with TTL
  - [ ] Add cache warming for common queries
  - [ ] Create cache management tools

- [ ] **API Response Optimization:**
  - [ ] Add HTTP caching headers
  - [ ] Implement response compression
  - [ ] Optimize JSON serialization

## Immediate Next Steps Detail:

### Phase 1: Error Tracking System Implementation
1. **Week 1: Error Logger and Database Setup**
   - Create database schema for error_logs
   - Implement ErrorLog model
   - Create ErrorLogger utility
   - Set up global error handlers

2. **Week 2: Error Dashboard Development**
   - Create ErrorController with management actions
   - Develop dashboard template with filtering
   - Implement visualizations for error trends
   - Add error detail views

3. **Week 3: Client-side Error Reporting**
   - Create API endpoint for error reporting
   - Develop JavaScript client for error capturing
   - Implement rate limiting and authentication
   - Add browser and device information capture

### Phase 2: Pricing and Billing System Implementation
1. **Week 4-5: Pricing Models and Configuration**
   - Create PricingModel class hierarchy
   - Implement admin interface for pricing configuration
   - Develop pricing calculation engine
   - Add support for discounts and promotions

2. **Week 6-7: Order and Invoice Management**
   - Create Order and Invoice models
   - Implement order workflow
   - Develop admin interface for order management
   - Create invoice generation functionality

3. **Week 8: Budget Management**
   - Implement advertiser budget tracking
   - Add automatic campaign controls
   - Create budget alerts and notifications
   - Develop budget forecasting tools

The immediate focus will be on implementing the Error Tracking System, starting with the ErrorLogger, database structure, and global error handlers. This will provide a foundation for better system reliability and monitoring before moving on to the Pricing and Billing System.
