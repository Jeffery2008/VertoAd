<?php
namespace VertoAD\Core\Services\Channels;

interface NotificationChannelInterface {
    /**
     * 发送通知
     * @param array $notification 通知数据
     * @return bool 发送是否成功
     */
    public function send(array $notification): bool;
    
    /**
     * 获取渠道类型
     * @return string
     */
    public function getType(): string;
    
    /**
     * 检查渠道是否可用
     * @return bool
     */
    public function isAvailable(): bool;
    
    /**
     * 获取最后一次错误信息
     * @return string|null
     */
    public function getLastError(): ?string;
    
    /**
     * 验证通知数据
     * @param array $notification 通知数据
     * @return bool
     */
    public function validate(array $notification): bool;
} 