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
            return $this->json([
                'status' => 'error',
                'message' => '充值金额必须在1-10000元之间'
            ], 400);
        }

        if ($quantity < 1 || $quantity > 100) {
            return $this->json([
                'status' => 'error',
                'message' => '生成数量必须在1-100之间'
            ], 400);
        }

        if ($prefix && !preg_match('/^[A-Z]{0,2}$/', $prefix)) {
            return $this->json([
                'status' => 'error',
                'message' => '前缀必须是2位大写字母'
            ], 400);
        }

        try {
            $keys = $this->keyModel->bulkGenerateKeys($amount, $quantity, $prefix);
            return $this->json([
                'status' => 'success',
                'message' => '激活码生成成功',
                'keys' => $keys
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
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
            $keys = $this->keyModel->getRecentKeys();
            return $this->json([
                'status' => 'success',
                'keys' => $keys
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
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
            $stats = $this->keyModel->getKeyStats();
            return $this->json([
                'status' => 'success',
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
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
            $keys = $this->keyModel->searchKeys('', null, 1000);
            
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
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 验证管理员权限
     */
    protected function ensureAdmin()
    {
        // 会话已经在 handleApiRequest 中启动，这里不需要再次启动
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
            $this->json([
                'status' => 'error',
                'message' => '需要管理员权限'
            ], 403);
            return false;
        }
        return true;
    }
} 