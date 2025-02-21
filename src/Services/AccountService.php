<?php

class AccountService {
    private $db;
    private $logger;
    private $validator;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
        $this->validator = new Validator();
    }

    /**
     * Get user profile information
     */
    public function getUserProfile($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                u.id,
                u.username,
                u.email,
                u.company_name,
                u.contact_name,
                u.phone,
                u.address,
                u.created_at,
                u.role,
                u.status,
                COUNT(DISTINCT a.id) as total_ads,
                COUNT(DISTINCT o.id) as total_orders
            FROM users u
            LEFT JOIN advertisements a ON u.id = a.user_id
            LEFT JOIN orders o ON u.id = o.user_id
            WHERE u.id = ?
            GROUP BY u.id
        ");

        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get account balance and stats
     */
    public function getAccountBalance($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                balance,
                total_spent,
                available_credit,
                last_deposit_date,
                last_deposit_amount,
                credit_limit
            FROM account_balances
            WHERE user_id = ?
        ");

        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get transaction history
     */
    public function getTransactionHistory($userId, $page = 1, $limit = 20, $startDate = null, $endDate = null, $type = null) {
        $offset = ($page - 1) * $limit;
        $params = [$userId];
        
        $sql = "
            SELECT 
                t.*,
                u.username
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            WHERE t.user_id = ?
        ";

        if ($startDate) {
            $sql .= " AND t.created_at >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $sql .= " AND t.created_at <= ?";
            $params[] = $endDate;
        }

        if ($type) {
            $sql .= " AND t.type = ?";
            $params[] = $type;
        }

        // Get total count
        $countStmt = $this->db->prepare(str_replace("SELECT t.*, u.username", "SELECT COUNT(*)", $sql));
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Get paginated results
        $sql .= " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'transactions' => $transactions
        ];
    }

    /**
     * Get orders
     */
    public function getOrders($userId, $page = 1, $limit = 20, $status = null) {
        $offset = ($page - 1) * $limit;
        $params = [$userId];
        
        $sql = "
            SELECT 
                o.*,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'id', oi.id,
                        'ad_position_id', oi.ad_position_id,
                        'start_date', oi.start_date,
                        'end_date', oi.end_date,
                        'price', oi.price
                    )
                ) as items
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = ?
        ";

        if ($status) {
            $sql .= " AND o.status = ?";
            $params[] = $status;
        }

        $sql .= " GROUP BY o.id";

        // Get total count
        $countSql = "SELECT COUNT(*) FROM orders WHERE user_id = ?";
        if ($status) {
            $countSql .= " AND status = ?";
        }
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Get paginated results
        $sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse JSON arrays
        foreach ($orders as &$order) {
            $order['items'] = json_decode($order['items'], true);
        }

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'orders' => $orders
        ];
    }

    /**
     * Get invoices
     */
    public function getInvoices($userId, $page = 1, $limit = 20, $status = null) {
        $offset = ($page - 1) * $limit;
        $params = [$userId];
        
        $sql = "
            SELECT 
                i.*,
                o.order_number,
                o.total_amount
            FROM invoices i
            JOIN orders o ON i.order_id = o.id
            WHERE i.user_id = ?
        ";

        if ($status) {
            $sql .= " AND i.status = ?";
            $params[] = $status;
        }

        // Get total count
        $countSql = "SELECT COUNT(*) FROM invoices WHERE user_id = ?";
        if ($status) {
            $countSql .= " AND status = ?";
        }
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Get paginated results
        $sql .= " ORDER BY i.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'invoices' => $invoices
        ];
    }

    /**
     * Process deposit
     */
    public function processDeposit($userId, $amount, $paymentMethod, $data) {
        try {
            $this->db->beginTransaction();

            // Process payment through payment gateway
            $paymentResult = $this->processPayment($amount, $paymentMethod, $data);
            if (!$paymentResult['success']) {
                throw new Exception($paymentResult['message']);
            }

            // Update account balance
            $stmt = $this->db->prepare("
                UPDATE account_balances 
                SET 
                    balance = balance + ?,
                    last_deposit_date = NOW(),
                    last_deposit_amount = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$amount, $amount, $userId]);

            // Record transaction
            $stmt = $this->db->prepare("
                INSERT INTO transactions (
                    user_id,
                    type,
                    amount,
                    payment_method,
                    status,
                    reference_id,
                    created_at
                ) VALUES (?, 'deposit', ?, ?, 'completed', ?, NOW())
            ");
            $stmt->execute([
                $userId, 
                $amount, 
                $paymentMethod,
                $paymentResult['reference_id']
            ]);

            $this->db->commit();

            return [
                'transaction_id' => $this->db->lastInsertId(),
                'reference_id' => $paymentResult['reference_id'],
                'amount' => $amount,
                'status' => 'completed'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Process withdrawal
     */
    public function processWithdrawal($userId, $amount, $bankInfo) {
        try {
            $this->db->beginTransaction();

            // Check available balance
            $balance = $this->getAccountBalance($userId);
            if ($balance['balance'] < $amount) {
                throw new Exception('Insufficient balance');
            }

            // Process withdrawal through payment gateway
            $withdrawalResult = $this->processWithdrawalRequest($amount, $bankInfo);
            if (!$withdrawalResult['success']) {
                throw new Exception($withdrawalResult['message']);
            }

            // Update account balance
            $stmt = $this->db->prepare("
                UPDATE account_balances 
                SET balance = balance - ?
                WHERE user_id = ?
            ");
            $stmt->execute([$amount, $userId]);

            // Record transaction
            $stmt = $this->db->prepare("
                INSERT INTO transactions (
                    user_id,
                    type,
                    amount,
                    payment_method,
                    status,
                    reference_id,
                    created_at
                ) VALUES (?, 'withdrawal', ?, 'bank_transfer', ?, ?, NOW())
            ");
            $stmt->execute([
                $userId, 
                $amount,
                $withdrawalResult['status'],
                $withdrawalResult['reference_id']
            ]);

            $this->db->commit();

            return [
                'transaction_id' => $this->db->lastInsertId(),
                'reference_id' => $withdrawalResult['reference_id'],
                'amount' => $amount,
                'status' => $withdrawalResult['status']
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Create order
     */
    public function createOrder($userId, $items, $data) {
        try {
            $this->db->beginTransaction();

            // Calculate total amount
            $totalAmount = 0;
            foreach ($items as $item) {
                if (!isset($item['ad_position_id'], $item['start_date'], $item['end_date'])) {
                    throw new Exception('Invalid order item');
                }
                
                // Get position price
                $stmt = $this->db->prepare("
                    SELECT price_per_day 
                    FROM ad_positions 
                    WHERE id = ?
                ");
                $stmt->execute([$item['ad_position_id']]);
                $position = $stmt->fetch();
                
                if (!$position) {
                    throw new Exception('Invalid ad position');
                }
                
                // Calculate days
                $startDate = new DateTime($item['start_date']);
                $endDate = new DateTime($item['end_date']);
                $days = $endDate->diff($startDate)->days + 1;
                
                $totalAmount += $position['price_per_day'] * $days;
            }

            // Check balance
            $balance = $this->getAccountBalance($userId);
            if ($balance['balance'] < $totalAmount) {
                throw new Exception('Insufficient balance');
            }

            // Create order
            $stmt = $this->db->prepare("
                INSERT INTO orders (
                    user_id,
                    order_number,
                    total_amount,
                    status,
                    created_at
                ) VALUES (?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $userId,
                $this->generateOrderNumber(),
                $totalAmount
            ]);
            $orderId = $this->db->lastInsertId();

            // Create order items
            foreach ($items as $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO order_items (
                        order_id,
                        ad_position_id,
                        start_date,
                        end_date,
                        price,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $orderId,
                    $item['ad_position_id'],
                    $item['start_date'],
                    $item['end_date'],
                    $position['price_per_day']
                ]);
            }

            // Update account balance
            $stmt = $this->db->prepare("
                UPDATE account_balances 
                SET balance = balance - ?
                WHERE user_id = ?
            ");
            $stmt->execute([$totalAmount, $userId]);

            // Record transaction
            $stmt = $this->db->prepare("
                INSERT INTO transactions (
                    user_id,
                    type,
                    amount,
                    payment_method,
                    status,
                    reference_id,
                    created_at
                ) VALUES (?, 'order', ?, 'balance', 'completed', ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $totalAmount,
                'ORDER-' . $orderId
            ]);

            $this->db->commit();

            return $this->getOrder($orderId);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Cancel order
     */
    public function cancelOrder($userId, $orderId, $reason = null) {
        try {
            $this->db->beginTransaction();

            // Get order
            $stmt = $this->db->prepare("
                SELECT * FROM orders 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$orderId, $userId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception('Order not found');
            }

            if ($order['status'] !== 'pending') {
                throw new Exception('Order cannot be cancelled');
            }

            // Update order status
            $stmt = $this->db->prepare("
                UPDATE orders 
                SET 
                    status = 'cancelled',
                    cancel_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reason, $orderId]);

            // Refund balance
            $stmt = $this->db->prepare("
                UPDATE account_balances 
                SET balance = balance + ?
                WHERE user_id = ?
            ");
            $stmt->execute([$order['total_amount'], $userId]);

            // Record refund transaction
            $stmt = $this->db->prepare("
                INSERT INTO transactions (
                    user_id,
                    type,
                    amount,
                    payment_method,
                    status,
                    reference_id,
                    created_at
                ) VALUES (?, 'refund', ?, 'balance', 'completed', ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $order['total_amount'],
                'REFUND-ORDER-' . $orderId
            ]);

            $this->db->commit();

            return $this->getOrder($orderId);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Request invoice
     */
    public function requestInvoice($userId, $orderId, $billingInfo) {
        try {
            $this->db->beginTransaction();

            // Validate order
            $stmt = $this->db->prepare("
                SELECT * FROM orders 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$orderId, $userId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception('Order not found');
            }

            // Check if invoice already exists
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM invoices 
                WHERE order_id = ?
            ");
            $stmt->execute([$orderId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Invoice already exists for this order');
            }

            // Create invoice
            $stmt = $this->db->prepare("
                INSERT INTO invoices (
                    user_id,
                    order_id,
                    invoice_number,
                    billing_info,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $userId,
                $orderId,
                $this->generateInvoiceNumber(),
                json_encode($billingInfo)
            ]);
            $invoiceId = $this->db->lastInsertId();

            $this->db->commit();

            return $this->getInvoiceById($invoiceId);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update user profile
     */
    public function updateUserProfile($userId, $data) {
        try {
            $this->db->beginTransaction();

            // Validate email if provided
            if (isset($data['email'])) {
                // Check if email is already in use by another user
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM users 
                    WHERE email = ? AND id != ?
                ");
                $stmt->execute([$data['email'], $userId]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Email is already in use');
                }
            }

            // Update user profile
            $allowedFields = [
                'email', 'company_name', 'contact_name', 
                'phone', 'address'
            ];
            
            $updates = [];
            $params = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                throw new Exception('No valid fields to update');
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $userId;
            
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->db->commit();

            return $this->getUserProfile($userId);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update password
     */
    public function updatePassword($userId, $currentPassword, $newPassword) {
        try {
            $this->db->beginTransaction();

            // Verify current password
            $stmt = $this->db->prepare("
                SELECT password FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($currentPassword, $user['password'])) {
                throw new Exception('Current password is incorrect');
            }

            // Validate new password
            if (strlen($newPassword) < 8) {
                throw new Exception('Password must be at least 8 characters long');
            }

            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("
                UPDATE users 
                SET 
                    password = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$hashedPassword, $userId]);

            $this->db->commit();

            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get order by ID
     */
    private function getOrder($orderId) {
        $stmt = $this->db->prepare("
            SELECT 
                o.*,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'id', oi.id,
                        'ad_position_id', oi.ad_position_id,
                        'start_date', oi.start_date,
                        'end_date', oi.end_date,
                        'price', oi.price
                    )
                ) as items
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.id = ?
            GROUP BY o.id
        ");

        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            $order['items'] = json_decode($order['items'], true);
        }
        
        return $order;
    }

    /**
     * Get invoice by ID
     */
    private function getInvoiceById($invoiceId) {
        $stmt = $this->db->prepare("
            SELECT 
                i.*,
                o.order_number,
                o.total_amount
            FROM invoices i
            JOIN orders o ON i.order_id = o.id
            WHERE i.id = ?
        ");

        $stmt->execute([$invoiceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber() {
        return 'ORD-' . date('Ymd') . '-' . substr(uniqid(), -6);
    }

    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber() {
        return 'INV-' . date('Ymd') . '-' . substr(uniqid(), -6);
    }

    /**
     * Process payment through payment gateway
     */
    private function processPayment($amount, $paymentMethod, $data) {
        // In a real application, this would integrate with a payment gateway
        // For now, we'll simulate a successful payment
        
        // Log payment attempt
        $this->logger->info('Payment processing', [
            'amount' => $amount,
            'method' => $paymentMethod,
            'data' => json_encode($data)
        ]);
        
        // Simulate payment processing
        return [
            'success' => true,
            'reference_id' => 'PAY-' . uniqid(),
            'transaction_id' => 'TXN' . time(),
            'status' => 'completed',
            'message' => 'Payment processed successfully'
        ];
    }

    /**
     * Process withdrawal request
     */
    private function processWithdrawalRequest($amount, $bankInfo) {
        // In a real application, this would integrate with a payment gateway
        // For now, we'll simulate a successful withdrawal request
        
        // Log withdrawal attempt
        $this->logger->info('Withdrawal processing', [
            'amount' => $amount,
            'bank_info' => json_encode($bankInfo)
        ]);
        
        // Simulate withdrawal processing
        return [
            'success' => true,
            'reference_id' => 'WDR-' . uniqid(),
            'status' => 'pending',
            'message' => 'Withdrawal request submitted successfully'
        ];
    }
}
