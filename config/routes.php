<?php
/**
 * Route definitions for the ad server
 * Maps URLs to controller methods
 * 
 * Note: This file is being deprecated in favor of the new routing system
 * located in routes/web_routes.php, routes/admin_routes.php, etc.
 */

// Forward to the new routing system if available
if (function_exists('dispatchRoute')) {
    function dispatchRoute($uri) {
        // This function is only here for backward compatibility
        // All actual routing is now handled in the new Router class
        return false;
    }
}

/* 
// Legacy route definitions - kept for reference
$routes = [
    // Public routes
    '/' => ['controller' => 'HomeController', 'method' => 'index'],
    '/login' => ['controller' => 'AuthController', 'method' => 'showLogin'],
    '/register' => ['controller' => 'AuthController', 'method' => 'showRegister'],
    
    // Advertiser routes
    '/advertiser/dashboard' => ['controller' => 'AdvertiserController', 'method' => 'dashboard'],
    '/advertiser/ads' => ['controller' => 'AdvertiserController', 'method' => 'listAds'],
    '/advertiser/activate' => ['controller' => 'AdvertiserController', 'method' => 'showActivationPage'],
    '/advertiser/activation-success' => ['controller' => 'AdvertiserController', 'method' => 'showActivationSuccess'],
    '/advertiser/create-ad' => ['controller' => 'AdvertiserController', 'method' => 'createAd'],
    '/advertiser/edit-ad' => ['controller' => 'AdvertiserController', 'method' => 'editAd'],
    '/advertiser/canvas' => ['controller' => 'AdvertiserController', 'method' => 'adCanvas'],
    '/advertiser/account' => ['controller' => 'AdvertiserController', 'method' => 'accountSettings'],
    
    // Admin routes
    '/admin/login' => [
        ['controller' => 'AdminController', 'method' => 'showLogin'], // GET - display login form
        ['controller' => 'AdminController', 'method' => 'login', 'method_type' => 'POST']  // POST - handle login form submission
    ],
    '/admin/dashboard' => ['controller' => 'AdminController', 'method' => 'dashboard'],
    '/admin/positions' => ['controller' => 'AdminController', 'method' => 'listAdPositions'],
    '/admin/positions/create' => ['controller' => 'AdminController', 'method' => 'showCreateAdPositionForm'],
    '/admin/positions/edit' => ['controller' => 'AdminController', 'method' => 'showEditAdPositionForm'],
    '/admin/positions/update' => ['controller' => 'AdminController', 'method' => 'updateAdPosition'], // POST
    '/admin/positions/delete' => ['controller' => 'AdminController', 'method' => 'deleteAdPosition'], // POST
    '/admin/advertisements' => ['controller' => 'AdminController', 'method' => 'listAdvertisements'],
    '/admin/advertisements/create' => [
        ['controller' => 'AdminController', 'method' => 'showCreateAdForm'], // GET - display form
        ['controller' => 'AdminController', 'method' => 'createAd', 'method_type' => 'POST'] // POST - handle form submission
    ],
    '/admin/advertisements/edit' => ['controller' => 'AdminController', 'method' => 'showEditAdForm'], // GET - display edit form
    '/admin/advertisements/update' => ['controller' => 'AdminController', 'method' => 'updateAd', 'method_type' => 'POST'], // POST - handle edit form submission
    '/admin/advertisements/delete' => ['controller' => 'AdminController', 'method' => 'deleteAd', 'method_type' => 'POST'], // POST - handle delete action
    '/admin/keys' => ['controller' => 'KeyManagementController', 'method' => 'listKeys'],
    '/admin/generate-keys' => ['controller' => 'KeyManagementController', 'method' => 'generateKeys'],
    '/admin/key-batch' => ['controller' => 'KeyManagementController', 'method' => 'viewBatch'],
    '/admin/users' => ['controller' => 'AdminController', 'method' => 'listUsers'],
    '/admin/balances' => ['controller' => 'AdminController', 'method' => 'manageBalances'],
    '/admin/transactions' => ['controller' => 'AdminController', 'method' => 'userTransactions'],
    '/admin/keys/batch' => ['controller' => 'AdminController', 'method' => 'showBatchKeyGenerationForm'], // For displaying the form
    '/admin/keys/batch' => ['controller' => 'AdminController', 'method' => 'generateBatchKeys'], // For form submission (POST)
    '/admin/keys/single' => ['controller' => 'AdminController', 'method' => 'showSingleKeyGenerationForm'], // For displaying single key form
    '/admin/keys/single' => ['controller' => 'AdminController', 'method' => 'generateSingleKey'], // For single key generation (POST)
    '/admin/logout' => ['controller' => 'AdminController', 'method' => 'logout'], // Logout action

    // API routes (handled by API files directly)
    '/api/v1/activate' => null,
    '/api/v1/ads' => null,
    '/api/v1/positions' => null,
    '/api/v1/competition' => null,
    '/api/v1/serve' => null,
    '/api/v1/track' => null,
    '/api/v1/accounts' => null,
    '/api/v1/auth/login' => null,
    '/api/v1/auth/challenge' => null,

    // Analytics routes
    '/analytics' => ['controller' => 'AnalyticsController', 'method' => 'dashboard'],
    '/analytics/export-csv' => ['controller' => 'AnalyticsController', 'method' => 'exportCsv'],

    // Notification channel management routes
    '/admin/notification/channels' => 'NotificationChannelController@index',
    '/admin/notification/channels/update-status' => 'NotificationChannelController@updateStatus',
    '/admin/notification/channels/update-config' => 'NotificationChannelController@updateConfig',

    // Notification preference routes
    '/user/notification/preferences' => 'NotificationPreferenceController@index',
    '/user/notification/preferences/update' => 'NotificationPreferenceController@update',
    '/user/notification/preferences/bulk-update' => 'NotificationPreferenceController@bulkUpdate',
    '/user/notification/preferences/reset' => 'NotificationPreferenceController@resetToDefault',

    // User contact management routes
    '/user/contact' => ['controller' => 'UserContactController', 'method' => 'index'],
    '/user/contact/update-email' => ['controller' => 'UserContactController', 'method' => 'updateEmail', 'method_type' => 'POST'],
    '/user/contact/update-phone' => ['controller' => 'UserContactController', 'method' => 'updatePhone', 'method_type' => 'POST'],
    '/user/contact/verify-email' => ['controller' => 'UserContactController', 'method' => 'verifyEmail', 'method_type' => 'POST'],
    '/user/contact/verify-phone' => ['controller' => 'UserContactController', 'method' => 'verifyPhone', 'method_type' => 'POST'],
    '/user/contact/resend-code' => ['controller' => 'UserContactController', 'method' => 'resendVerificationCode', 'method_type' => 'POST'],
];
*/
