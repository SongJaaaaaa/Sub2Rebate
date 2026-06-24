<?php
/**
 * 二维码访问端点
 * 提供经营码二维码的HTTP访问
 */

// 设置正确的内容类型
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// 加载配置
$config = require __DIR__ . '/config/alipay.php';

// 获取二维码类型参数
$type = $_GET['type'] ?? 'business';
$token = $_GET['token'] ?? '';

// 验证token（简单的安全验证）
$expectedToken = md5('qrcode_access_' . date('Y-m-d'));
if ($token !== $expectedToken) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Invalid token';
    exit;
}

try {
    switch ($type) {
        case 'business':
            // 经营码二维码
            $qrCodePath = $config['payment']['business_qr_mode']['qr_code_path'];
            
            if (!file_exists($qrCodePath) || filesize($qrCodePath) === 0) {
                header('Content-Type: image/svg+xml; charset=UTF-8');
                echo '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="220" viewBox="0 0 220 220"><rect width="220" height="220" fill="#f5f7fb"/><rect x="24" y="24" width="172" height="172" fill="#fff" stroke="#1677ff" stroke-width="3"/><text x="110" y="96" text-anchor="middle" font-size="18" fill="#1677ff">AliMPay</text><text x="110" y="124" text-anchor="middle" font-size="13" fill="#666">本地测试二维码</text><text x="110" y="148" text-anchor="middle" font-size="12" fill="#999">替换 qrcode/business_qr.png 后生效</text></svg>';
                exit;
            }
            
            // 读取并输出二维码文件
            $imageData = file_get_contents($qrCodePath);
            
            // 根据文件类型设置正确的Content-Type
            $imageInfo = getimagesizefromstring($imageData);
            if ($imageInfo) {
                header('Content-Type: ' . $imageInfo['mime']);
            }
            
            echo $imageData;
            break;
            
        default:
            header('HTTP/1.1 400 Bad Request');
            echo 'Invalid QR code type';
            break;
    }
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Error loading QR code: ' . $e->getMessage();
}
?>
