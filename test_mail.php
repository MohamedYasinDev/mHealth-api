<?php
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'mohamedyaasiin86@gmail.com';
    $mail->Password = 'ymxl xjmz tyib tjct';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    $mail->setFrom('noreply@mhealth.com', 'mHealth');
    $mail->addAddress('mohamedyaasiin86@gmail.com', 'Test');
    $mail->Subject = 'Test';
    $mail->Body = 'This is a test email!';
    
    $mail->send();
    echo "SUCCESS! Email sent.";
} catch (Exception $e) {
    echo "FAILED: " . $mail->ErrorInfo;
}
?>