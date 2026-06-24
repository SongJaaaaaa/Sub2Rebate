<?php

namespace AliMPay\Utils;

class Logger
{
    private static $instance = null;
    private string $logDir;
    
    private function __construct()
    {
        $this->logDir = __DIR__ . '/../../logs';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function log(string $message, array $context = [], string $level = 'info'): void
    {
        $line = sprintf(
            "[%s] AliMPay.%s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '[]'
        );

        file_put_contents($this->logDir . '/' . $level . '.log', $line, FILE_APPEND);
        file_put_contents($this->logDir . '/debug.log', $line, FILE_APPEND);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log($message, $context, 'info');
    }
    
    public function error(string $message, array $context = []): void
    {
        $this->log($message, $context, 'error');
    }
    
    public function debug(string $message, array $context = []): void
    {
        $this->log($message, $context, 'debug');
    }
    
    public function warning(string $message, array $context = []): void
    {
        $this->log($message, $context, 'warning');
    }
    
    public function critical(string $message, array $context = []): void
    {
        $this->log($message, $context, 'critical');
    }
}
