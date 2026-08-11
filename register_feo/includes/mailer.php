<?php
/**
 * Отправка email уведомлений (Mailer)
 */

// Защита от прямого доступа
if (!defined('ACCESS_GRANTED')) {
    http_response_code(403);
    die('Доступ запрещён');
}

/**
 * Класс для отправки email через SMTP или mail()
 */
class Mailer {
    private $smtpHost;
    private $smtpPort;
    private $smtpSecurity;
    private $smtpUser;
    private $smtpPass;
    private $smtpFrom;
    
    public function __construct() {
        $this->smtpHost = defined('SMTP_HOST') ? SMTP_HOST : '';
        $this->smtpPort = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $this->smtpSecurity = defined('SMTP_SECURITY') ? SMTP_SECURITY : 'tls';
        $this->smtpUser = defined('SMTP_USER') ? SMTP_USER : '';
        $this->smtpPass = defined('SMTP_PASS') ? SMTP_PASS : '';
        $this->smtpFrom = defined('SMTP_FROM') ? SMTP_FROM : 'noreply@local';
    }
    
    /**
     * Отправка email
     */
    public function send($to, $subject, $body, $altBody = '') {
        if (empty($this->smtpHost)) {
            return $this->sendViaMail($to, $subject, $body);
        }
        
        return $this->sendViaSmtp($to, $subject, $body, $altBody);
    }
    
    /**
     * Отправка через PHP mail()
     */
    private function sendViaMail($to, $subject, $body) {
        $headers = [
            'From: ' . $this->smtpFrom,
            'Reply-To: ' . $this->smtpFrom,
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8'
        ];
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    /**
     * Отправка через SMTP (сокеты)
     */
    private function sendViaSmtp($to, $subject, $body, $altBody = '') {
        try {
            $socket = fsockopen(
                ($this->smtpSecurity === 'ssl' ? 'ssl://' : '') . $this->smtpHost,
                $this->smtpPort,
                $errno,
                $errstr,
                30
            );
            
            if (!$socket) {
                logError("SMTP connection failed: $errno - $errstr");
                return false;
            }
            
            stream_set_timeout($socket, 5);
            
            $this->readResponse($socket);
            $this->sendCommand($socket, "EHLO " . $_SERVER['HTTP_HOST']);
            
            if ($this->smtpSecurity === 'tls') {
                $this->sendCommand($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendCommand($socket, "EHLO " . $_SERVER['HTTP_HOST']);
            }
            
            if (!empty($this->smtpUser)) {
                $this->sendCommand($socket, "AUTH LOGIN");
                $this->sendCommand($socket, base64_encode($this->smtpUser));
                $this->sendCommand($socket, base64_encode($this->smtpPass));
            }
            
            $this->sendCommand($socket, "MAIL FROM:<" . $this->smtpFrom . ">");
            $this->sendCommand($socket, "RCPT TO:<" . $to . ">");
            $this->sendCommand($socket, "DATA");
            
            $message = $this->buildMessage($to, $subject, $body, $altBody);
            fwrite($socket, $message . "\r\n.\r\n");
            $this->readResponse($socket);
            
            $this->sendCommand($socket, "QUIT");
            fclose($socket);
            
            return true;
        } catch (Exception $e) {
            logError('SMTP error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Построение сообщения
     */
    private function buildMessage($to, $subject, $body, $altBody = '') {
        $boundary = uniqid('np');
        
        $headers = "From: {$this->smtpFrom}\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        
        $message = "--{$boundary}\r\n";
        
        if (!empty($altBody)) {
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $altBody . "\r\n\r\n";
        }
        
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $message .= quoted_printable_encode($body) . "\r\n\r\n";
        $message .= "--{$boundary}--";
        
        return $headers . "\r\n\r\n" . $message;
    }
    
    /**
     * Отправка команды SMTP
     */
    private function sendCommand($socket, $command) {
        fwrite($socket, $command . "\r\n");
        return $this->readResponse($socket);
    }
    
    /**
     * Чтение ответа SMTP
     */
    private function readResponse($socket) {
        $response = fgets($socket, 512);
        return trim($response);
    }
}

/**
 * Helper функция для отправки уведомлений
 */
function sendNotification($to, $subject, $body) {
    $mailer = new Mailer();
    return $mailer->send($to, $subject, $body);
}
