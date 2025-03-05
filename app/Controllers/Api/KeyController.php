<?php

namespace App\Controllers\Api;

use App\Models\KeyModel;
use App\Controllers\BaseController;

class KeyController extends BaseController
{
    private $keyModel;

    public function __construct()
    {
        $this->keyModel = new KeyModel();
    }

    /**
     * 生成激活码
     */
    public function generate()
    {
        // 验证管理员权限
        if (!$this->ensureAdmin()) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => '无权限访问'
            ])->setStatusCode(403);
        }

        // 获取并验证输入参数
        $amount = $this->request->getJSON()->amount ?? 0;
        $quantity = $this->request->getJSON()->quantity ?? 0;
        $prefix = $this->request->getJSON()->prefix ?? '';

        // 验证输入
        if ($amount < 1 || $amount > 10000) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => '充值金额必须在1-10000元之间'
            ])->setStatusCode(400);
        }

        if ($quantity < 1 || $quantity > 100) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => '生成数量必须在1-100之间'
            ])->setStatusCode(400);
        }

        if ($prefix && !preg_match('/^[A-Z]{0,2}$/', $prefix)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => '前缀必须是2位大写字母'
            ])->setStatusCode(400);
        }

        try {
            $keys = $this->keyModel->generateKeys($amount, $quantity, $prefix);
            return $this->response->setJSON([
                'status' => 'success',
                'message' => '激活码生成成功',
                'keys' => $keys
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * 获取最近生成的激活码
     */
    public function recent()
    {
        if (!$this->ensureAdmin()) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => '无权限访问'
            ])->setStatusCode(403);
        }

        try {
            $keys = $this->keyModel->getRecentKeys();
            return $this->response->setJSON([
                'status' => 'success',
                'keys' => $keys
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * 获取统计信息
     */
    public function stats()
    {
        if (!$this->ensureAdmin()) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => '无权限访问'
            ])->setStatusCode(403);
        }

        try {
            $stats = $this->keyModel->getStats();
            return $this->response->setJSON([
                'status' => 'success',
                'today_generated' => $stats['today_generated'],
                'today_used' => $stats['today_used'],
                'unused_count' => $stats['unused_count'],
                'unused_amount' => $stats['unused_amount']
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * 导出激活码
     */
    public function export()
    {
        if (!$this->ensureAdmin()) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => '无权限访问'
            ])->setStatusCode(403);
        }

        try {
            $keys = $this->keyModel->getAllKeys();
            
            // 创建CSV内容
            $output = fopen('php://temp', 'w+');
            fputcsv($output, ['激活码', '金额', '生成时间', '状态']);
            
            foreach ($keys as $key) {
                fputcsv($output, [
                    chunk_split($key['code'], 5, '-'),
                    $key['amount'],
                    $key['created_at'],
                    $key['used'] ? '已使用' : '未使用'
                ]);
            }
            
            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);

            // 设置响应头
            return $this->response
                ->setHeader('Content-Type', 'text/csv')
                ->setHeader('Content-Disposition', 'attachment; filename="activation-keys.csv"')
                ->setBody($csv);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * 验证管理员权限
     */
    private function ensureAdmin()
    {
        session_start();
        return isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
} 