# Current Implementation Status

## Completed Components (Assumed based on previous todolist)

1. Core System Structure
   - Basic routing system
   - Database schema design (Needs revision)
   - Model and controller architecture (Needs revision for new features)
   - Authentication system (Needs role enforcement)

2. Ad Management (Basis)
   - Basic Ad CRUD (Needs update for Quill/Duration)
   - Ad approval workflow (Concept exists)
   - Ad serving via iframe (Mechanism exists, content needs update)
   - View tracking system (For reporting)
   - ~~Basic click tracking~~ (Lower priority)

3. Ad Editor
   - ~~Canvas-based editor using Fabric.js~~ (To be replaced by Quill)
   - ~~Text, image, and shape tools~~ (Provided by Quill)
   - ~~Layer management~~ (N/A for Quill)
   - ~~Template system~~ (Re-evaluate with Quill)
   - ~~Save and preview functionality~~ (Needs update for Quill Delta)

4. Billing System (Basis - Needs Major Revision)
   - ~~Credit management~~ (Replaced/Adapted for CDKEY)
   - ~~View tracking and billing~~ (Billing changed to duration)
   - ~~Detailed statistics~~ (Needs update for duration/CDKEY)
   - ~~Publisher earnings tracking~~ (Needs review based on model)

## Next Implementation Phase: Core Functionality Revision

### 1. Ad Content System (Quill)
- Database Schema Updates:
  ```sql
  -- Modify 'ads' table
  ALTER TABLE ads
  ADD COLUMN content_quill_delta JSON, -- Store Quill content
  ADD COLUMN rendered_html_cache LONGTEXT, -- Optional cache for server-rendered HTML
  DROP COLUMN fabric_js_data; -- Or similar field for old editor
  ```
- Implementation Steps:
  1. Integrate Quill Editor into Advertiser frontend (Static HTML/JS).
  2. Update Ad creation/update API endpoint to accept Quill Delta JSON.
  3. Implement server-side PHP function/library to render Quill Delta to HTML.
  4. Modify Ad Serving API to fetch Delta, render to HTML (use cache if available), and return HTML for iframe.

### 2. Duration-Based Billing & CDKEY System
- Database Schema Updates:
  ```sql
  -- Modify 'ads' table
  ALTER TABLE ads
  ADD COLUMN start_datetime DATETIME NULL,
  ADD COLUMN end_datetime DATETIME NULL,
  ADD COLUMN purchased_duration_days INT DEFAULT 0; -- Or other unit (hours, etc.)

  -- New 'cdkeys' table
  CREATE TABLE cdkeys (
      id INT PRIMARY KEY AUTO_INCREMENT,
      key_string VARCHAR(255) UNIQUE NOT NULL,
      value_type ENUM('duration_days', 'credit') NOT NULL,
      value DECIMAL(10, 2) NOT NULL, -- Duration in days or credit amount
      is_redeemed BOOLEAN DEFAULT FALSE,
      redeemed_by_user_id INT NULL,
      redeemed_at DATETIME NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      expires_at DATETIME NULL,
      FOREIGN KEY (redeemed_by_user_id) REFERENCES users(id)
  );

  -- Optional: Modify 'users' table for credit balance if keeping dual system
  -- ALTER TABLE users
  -- ADD COLUMN credit_balance DECIMAL(10, 2) DEFAULT 0.00;
  ```

- Implementation Steps:
  1. Create Admin interface (Static HTML + API call) to generate CDKEYs (specify type, value, expiry).
  2. Create Advertiser interface (Static HTML + API call) to redeem CDKEYs.
  3. Implement API endpoint for CDKEY redemption:
     - Validate key (exists, not redeemed, not expired).
     - Mark key as redeemed.
     - Apply value: Update user's `credit_balance` or apply duration to a specific ad (requires mechanism to select ad or apply account-wide duration credit).
  4. Update Ad Serving logic: Only serve ads where `is_approved = true` AND `NOW()` BETWEEN `start_datetime` AND `end_datetime`.
  5. Implement mechanism to activate purchased duration (e.g., when an admin approves an ad or advertiser applies duration credit, set `start_datetime` and calculate `end_datetime`).

### 3. Role-Based Access Control (RBAC)
- Implementation Steps:
  1. Ensure `users` table has a `role` column (e.g., ENUM('admin', 'advertiser', 'publisher')).
  2. Implement middleware or checks in the API router/controllers to verify user role against required permissions for each endpoint.
     - Admins: User management, CDKEY generation, ad approval, system stats.
     - Advertisers: Ad management (CRUD), CDKEY redemption, view own stats.
     - Publishers: Site/Zone management, get ad code, view earnings (if applicable).

## Technical Requirements

1. Performance Optimization
   - Add caching for rendered Quill HTML.
   - Implement CDN for creative assets (images uploaded via Quill).
   - Optimize database queries (especially ad serving checks).
   - ~~Add Redis caching for ad serving~~ (May be less critical initially than Quill render cache)

2. Security Measures
   - Add rate limiting to API endpoints (especially redemption, login).
   - Implement CSRF protection for frontend forms interacting with API.
   - Input validation for all API inputs (Quill Delta, CDKEYs, user data, etc.).
   - Enforce Role-Based Access Control consistently.
   - Secure iframe communication (standard best practices, e.g., sandbox attribute).
   - ~~Implement request signing~~ (Potentially overkill for initial phase)
   - ~~Add fraud detection~~ (Defer to later phase)

3. Monitoring
   - Add performance metrics for ad serving and API response times.
   - Implement error tracking (e.g., Sentry, Monolog).
   - Create alert system (e.g., expiring keys, critical errors).

## Immediate Tasks

1.  **Refactor Database Schema:** Apply changes to `ads`, `users` (if needed), create `cdkeys` table.
2.  **Implement CDKEY Generation:** Backend logic and Admin API endpoint.
3.  **Implement CDKEY Redemption:** Backend logic and Advertiser API endpoint.
4.  **Integrate Quill Editor:** Basic integration into Advertiser Ad Creation UI mockup (Static HTML/JS).
5.  **Implement Quill Delta -> HTML Rendering:** Server-side PHP function/library.
6.  **Update Ad Serving Logic:** Implement duration checks.
7.  **Implement Basic RBAC:** Middleware/checks for API endpoints.

## API Updates Needed

1. Ad CRUD API:
```php
// POST /api/ads (Create)
// PUT /api/ads/{id} (Update)
// Requires Advertiser Role
{
    "name": "My Awesome Ad",
    "content_quill_delta": { ...Quill JSON... },
    "target_url": "https://example.com"
    // Note: Duration is applied separately via CDKEY redemption or admin action
}

// GET /api/ads/{id} (Read)
// Requires Advertiser Role (own ads) or Admin Role
// Returns ad details including Quill Delta

// DELETE /api/ads/{id}
// Requires Advertiser Role (own ads) or Admin Role

// POST /api/admin/ads/{id}/approve
// Requires Admin Role
// Sets is_approved = true, potentially sets start_datetime/end_datetime if duration was pre-applied

// POST /api/admin/ads/{id}/reject
// Requires Admin Role
// Sets is_approved = false

// GET /api/serve/ad/{zone_id} (?)
// Public endpoint? Needs more thought on zone mapping
// Returns pre-rendered HTML for an active, approved ad for the zone
```

2. CDKEY API:
```php
// POST /api/admin/cdkeys (Admin - Generate)
// Requires Admin Role
{
    "count": 10,
    "value_type": "duration_days", // or 'credit'
    "value": 30, // 30 days or 30 credits
    "expires_at": "2024-12-31T23:59:59Z" // Optional
}
// Returns list of generated keys

// GET /api/admin/cdkeys (Admin - List/Search)
// Requires Admin Role

// POST /api/advertiser/redeem (Advertiser - Redeem)
// Requires Advertiser Role
{
    "key_string": "ABCDE-FGHIJ-KLMNO-PQRST-UVWXY",
    "apply_to_ad_id": 123 // Optional: If value_type is duration, apply directly to this ad
}
// Returns success/failure and updated balance/duration status
```

3. User Management API (Admin):
```php
// GET /api/admin/users
// POST /api/admin/users
// PUT /api/admin/users/{id}
// DELETE /api/admin/users/{id}
// Requires Admin Role
// Manage user details including roles, status
```

4. Authentication API:
```php
// POST /api/login
{
  "email": "user@example.com",
  "password": "password123"
}
// Returns JWT token or session info

// POST /api/register
// ... user details ...

// POST /api/logout
```

## Next Steps

1.  Implement Database Schema changes in the actual database.
2.  Develop backend PHP classes/functions for CDKEY logic (generation, validation, redemption).
3.  Build API endpoints (routing, controllers) for CDKEYs (Admin generate, Advertiser redeem).
4.  Set up a basic static HTML page for Ad creation with Quill.js integrated.
5.  Write the PHP function to render Quill Delta JSON to safe HTML.
6.  Implement the core Ad Serving logic retrieving and rendering an active ad.
7.  Add basic API authentication and role checking middleware.

## Current Focus

1.  **Database Schema Implementation & Migration**
2.  **CDKEY System Backend Logic & API Endpoints**
3.  **Quill Integration (Frontend Editor & Backend Rendering Function)**
4.  **Basic API Authentication & RBAC Middleware**

## Notes

- Ad editor will use Quill.js.
- Ad content stored as Quill Delta JSON.
- Ad serving renders Delta to HTML server-side and delivers via iframe.
- Billing is primarily duration-based, activated via CDKEY redemption or Admin approval/action.
- Admin/Advertiser/Publisher interfaces are static HTML/JS calling the PHP API.
- Roles (Admin, Advertiser, Publisher) control API access.
- View tracking (impressions) is for reporting, not direct billing.
- Timestamps should ideally be stored in UTC.
- Focus on native PHP for the API backend. 