<?php

namespace App\Controllers\Api;

use App\Models\KeyModel;
use App\Controllers\BaseController;

class KeyController extends BaseController
{
    private $keyModel;

    public function __construct()
    {
        parent::__construct();
        $this->keyModel = new KeyModel();
    }

    /**
     * 生成激活码
     */
    public function generate()
    {
        // 验证管理员权限
        if (!$this->ensureAdmin()) {
            return;
        }

        // 获取并验证输入参数
        $json = json_decode(file_get_contents('php://input'));
        $amount = $json->amount ?? 0;
        $quantity = $json->quantity ?? 1;
        $prefix = $json->prefix ?? '';

        // 验证输入
        if ($amount < 1 || $amount > 10000) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => '充值金额必须在1-10000元之间'
            ]);
            exit;
        }

        if ($quantity < 1 || $quantity > 100) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => '生成数量必须在1-100之间'
            ]);
            exit;
        }

        if ($prefix && !preg_match('/^[A-Z]{0,2}$/', $prefix)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => '前缀必须是2位大写字母'
            ]);
            exit;
        }

        try {
            $keys = $this->keyModel->generate($amount, $quantity);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $keys
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取最近生成的激活码
     */
    public function recent()
    {
        if (!$this->ensureAdmin()) {
            return;
        }

        try {
            $keys = $this->keyModel->getList(1, 10);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $keys
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取统计信息
     */
    public function stats()
    {
        if (!$this->ensureAdmin()) {
            return;
        }

        try {
            $stats = $this->keyModel->getStats();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 导出激活码
     */
    public function export()
    {
        if (!$this->ensureAdmin()) {
            return;
        }

        try {
            $keys = $this->keyModel->getList(1, 1000)['keys'];
            
            // 设置响应头
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="activation-keys.csv"');
            
            // 输出 BOM
            echo "\xEF\xBB\xBF";
            
            // 创建CSV内容
            $output = fopen('php://output', 'w');
            
            // 写入表头
            fputcsv($output, ['激活码', '金额', '生成时间', '状态']);
            
            // 写入数据
            foreach ($keys as $key) {
                fputcsv($output, [
                    $key['key_code'],
                    $key['amount'],
                    $key['created_at'],
                    $key['status'] === 'used' ? '已使用' : '未使用'
                ]);
            }
            
            fclose($output);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 验证管理员权限
     */
    protected function ensureAdmin()
    {
        // 会话已经在 handleApiRequest 中启动，这里不需要再次启动
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => '需要管理员权限'
            ]);
            exit;
            return false;
        }
        return true;
    }
} 