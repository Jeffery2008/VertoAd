<?php

/**
 * Security Routes
 * Routes for security features like API key management, CSRF tokens, etc.
 */

// API Key Management
$router->get('/settings/api-keys', 'SecurityController@showApiKeysPage');
$router->post('/api/v1/security/api-keys', 'SecurityController@createApiKey');
$router->put('/api/v1/security/api-keys/{keyId}', 'SecurityController@updateApiKey');
$router->delete('/api/v1/security/api-keys/{keyId}', 'SecurityController@deleteApiKey');
$router->post('/api/v1/security/api-keys/{keyId}/revoke', 'SecurityController@revokeApiKey');
$router->get('/api/v1/security/api-keys/{keyId}/stats', 'SecurityController@getApiKeyStats');

// Session Management
$router->get('/settings/sessions', 'SecurityController@showSessionsPage');
$router->delete('/api/v1/security/sessions/{sessionId}', 'SecurityController@revokeSession');
$router->delete('/api/v1/security/sessions', 'SecurityController@revokeAllSessions');

// Security Audit
$router->get('/admin/security/audit-log', 'SecurityController@showAuditLogPage');

// CSRF Tokens
$router->get('/api/v1/security/csrf-token', 'SecurityController@generateCsrfToken');
$router->get('/api/v1/security/csrf-token/{pageId}', 'SecurityController@generateCsrfToken');

// Proof of Work
$router->get('/api/v1/security/pow-challenge', 'SecurityController@generatePowChallenge');
$router->post('/api/v1/security/pow-verify', 'SecurityController@verifyPowSolution');

// OAuth2 Client Management
$router->get('/settings/oauth-clients', 'AuthController@showOAuthClientsPage');
$router->post('/api/v1/auth/oauth/clients', 'AuthController@createOAuthClient');
$router->delete('/api/v1/auth/oauth/clients/{clientId}', 'AuthController@deleteOAuthClient');

// OAuth2 Authorization Endpoints
$router->get('/oauth/authorize', 'AuthController@authorizeOAuthClient');
$router->post('/oauth/token', 'AuthController@generateOAuthToken');
$router->post('/oauth/revoke', 'AuthController@revokeOAuthToken'); 