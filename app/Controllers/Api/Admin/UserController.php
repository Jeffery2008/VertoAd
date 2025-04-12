<?php

namespace App\Controllers\Api\Admin;

use App\Services\UserService;
use App\Core\BaseApiController;
use App\Core\Request;
use Exception;

class UserController extends BaseApiController
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * GET /api/admin/users
     * List users with filtering and pagination.
     */
    public function list(Request $request)
    {
        try {
            $page = filter_var($request->getQueryParam('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);
            $limit = filter_var($request->getQueryParam('limit', 20), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100, 'default' => 20]]);
            $role = filter_var($request->getQueryParam('role'), FILTER_SANITIZE_STRING);
            $searchTerm = filter_var($request->getQueryParam('search'), FILTER_SANITIZE_STRING);
            
             // Validate role
             if ($role && !in_array($role, ['admin', 'advertiser', 'publisher'])) {
                 return $this->jsonResponse(['error' => 'Invalid role filter.'], 400);
             }

            $result = $this->userService->getUsers($page, $limit, $role ?: null, $searchTerm ?: null);
            return $this->jsonResponse($result, 200);

        } catch (Exception $e) {
            error_log("List Users Exception: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'An internal error occurred while fetching users.'], 500);
        }
    }

    /**
     * POST /api/admin/users
     * Create a new user.
     */
    public function create(Request $request)
    {
         try {
            $input = $request->getJsonBody();
            
            $username = filter_var($input['username'] ?? '', FILTER_SANITIZE_STRING);
            $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $password = $input['password'] ?? '';
            $role = filter_var($input['role'] ?? '', FILTER_SANITIZE_STRING);

            if (empty($username) || empty($email) || empty($password) || empty($role)) {
                 return $this->jsonResponse(['error' => 'Missing required fields: username, email, password, role'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->jsonResponse(['error' => 'Invalid email format.'], 400);
            }
            if (!in_array($role, ['admin', 'advertiser', 'publisher'])) {
                 return $this->jsonResponse(['error' => 'Invalid role specified.'], 400);
            }
            // Add password strength check if desired

            $userId = $this->userService->createUser($username, $email, $password, $role);

            if ($userId) {
                return $this->jsonResponse(['message' => 'User created successfully', 'user_id' => $userId], 201);
            } else {
                // Service logs reason (duplicate user, etc.)
                return $this->jsonResponse(['error' => 'Failed to create user. Username or email might already exist.'], 409); // 409 Conflict for duplicate
            }
        } catch (Exception $e) {
            error_log("Create User Exception: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'An internal error occurred during user creation.'], 500);
        }
    }

    /**
     * PUT /api/admin/users/{id}
     * Update an existing user.
     */
    public function update(Request $request, int $id)
    {
         try {
            $input = $request->getJsonBody();
            if (empty($input)) {
                 return $this->jsonResponse(['error' => 'No update data provided.'], 400);
            }

            // Sanitize input data before passing to service
            $updateData = [];
            if(isset($input['username'])) $updateData['username'] = filter_var($input['username'], FILTER_SANITIZE_STRING);
            if(isset($input['email'])) $updateData['email'] = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
            if(isset($input['role'])) $updateData['role'] = filter_var($input['role'], FILTER_SANITIZE_STRING);
            if(isset($input['balance'])) $updateData['balance'] = filter_var($input['balance'], FILTER_VALIDATE_FLOAT);
            if(isset($input['password']) && !empty($input['password'])) $updateData['password'] = $input['password']; // Pass raw password
            
            // Perform validation on sanitized data if needed (e.g., role enum)
            // ...

            $success = $this->userService->updateUser($id, $updateData);

            if ($success) {
                return $this->jsonResponse(['message' => 'User updated successfully'], 200);
            } else {
                // Service logs reason
                return $this->jsonResponse(['error' => 'Failed to update user. Check input or logs.'], 400); // Or 404 if user not found
            }
        } catch (Exception $e) {
            error_log("Update User Exception (ID: {$id}): " . $e->getMessage());
            return $this->jsonResponse(['error' => 'An internal error occurred during user update.'], 500);
        }
    }

    /**
     * DELETE /api/admin/users/{id}
     * Delete a user.
     */
    public function delete(Request $request, int $id)
    {
        try {
            // Optional: Prevent deleting self or last admin?
            // $currentUserId = $this->authService->getCurrentUserId(); // Needs auth service injected
            // if ($id === $currentUserId) return $this->jsonResponse(['error' => 'Cannot delete self.'], 403);

            $success = $this->userService->deleteUser($id);

            if ($success) {
                return $this->jsonResponse(['message' => 'User deleted successfully'], 200); // Or 204
            } else {
                return $this->jsonResponse(['error' => 'Failed to delete user. User might not exist.'], 404);
            }
        } catch (Exception $e) {
             error_log("Delete User Exception (ID: {$id}): " . $e->getMessage());
             return $this->jsonResponse(['error' => 'An internal error occurred during user deletion.'], 500);
        }
    }

    // Inherit or define jsonResponse
    protected function jsonResponse(array $data, int $statusCode = 200) 
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit(); 
    }
} 