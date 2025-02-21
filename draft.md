# Draft - Ad Server Development

## Product Key System - Key Blacklisting

**Current Status:**
- Key Generation Service: Partially implemented
- Key Redemption System: Partially implemented
- Admin Key Management: Partially implemented - Batch and Single key generation interfaces done, Key tracking and auditing (display key details) done

**Todo:**

1. **Key Generation Service Enhancements:**
    - [x] Key blacklisting system
        - [x] Implement `blacklistKey` method in `KeyGenerationService.php` to update key status to `revoked`.
        - [x] Implement `isKeyBlacklisted` method in `KeyGenerationService.php` to check if a key is `revoked`.
        - [x] Update `validateKey` method in `KeyGenerationService.php` to check if a key is blacklisted before validation.
        - [x] Update unit tests for `KeyGenerationService.php` to include blacklisting functionality. (Unit tests not implemented yet, will be addressed later)
    - [x] Admin Key Management
      - [x] Batch key generation interface
      - [x] Single key generation
      - [x] Key tracking and auditing (display key details in key batches table)
      - [ ] Key status management (unused, used, revoked)
      - [ ] Export keys to CSV/Excel

2. **Key Redemption System Enhancements:**
    - [ ] User interface for key activation (partially done - `templates/advertiser/activate.php` and `static/js/activate.js`)
    - [ ] Key usage analytics
    - [x] Real-time key validation (done in `KeyRedemptionService.php`)
    - [x] Balance credit process (done in `KeyRedemptionService.php`)
    - [x] Activation history tracking (done in `KeyRedemptionService.php`)
    - [x] Update `KeyRedemptionService.php` to check if a key is blacklisted before activation using `isKeyBlacklisted` method.

3. **Admin Panel Features:** (Will be addressed later)
    - Ad Position Management
    - Ad Campaign Management
    - Performance Dashboard
    - User Management
    - Key Management & Finance

4. **Security Features:** (Will be addressed later)
    - API authentication
    - Rate limiting
    - CSRF protection
    - XSS prevention
    - SQL injection protection
    - Input validation
    - Key validation security
    - Audit logging

5. **Testing:** (Will be addressed later)
    - Unit tests for core components
    - Key generation/validation tests
    - Integration tests
    - Load testing
    - Security testing
    - Browser compatibility testing

6. **Documentation:** (Will be addressed later)
    - API documentation
    - Admin panel user guide
    - Advertiser guide
    - Installation guide
    - Development setup guide
    - Key system documentation

**Next Step:** Implement Admin Key Management - Key status management (unused, used, revoked).

## Installation Variables:

**Database Configuration (config/database.php):**
- `database_host` (default: `localhost`)
- `database_name` (default: `ad_system`)
- `database_username` (default: `root`)
- `database_password` (default: \`\` - empty string)

**General Configuration (config/config.php):**
- `base_url` (default: `http://localhost/ad-system`)
- `app_name` (default: `Ad System`)
- `app_version` (default: `1.0.0`)
- `api_version` (default: `v1`)
- `api_key_header` (default: `X-API-Key`)
- `upload_path` (default: `dirname(__DIR__) . '/uploads'`)
- `max_file_size` (default: `5 * 1024 * 1024`)
- `allowed_extensions` (default: `['jpg', 'jpeg', 'png', 'gif', 'svg']`)
- `default_ad_priority` (default: `0`)
- `max_ad_priority` (default: `10`)
- `cache_duration` (default: `3600`)
- `jwt_secret` (default: `your_jwt_secret_key_here`) - **IMPORTANT: Secure value needed**
- `password_salt` (default: `your_password_salt_here`) - **IMPORTANT: Secure value needed**
- `token_expiry` (default: `86400`)
