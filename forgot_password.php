<?php
// C:\xampp\htdocs\mHealth_api\forgot_password.php
// header("Content-Type: application/json");
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");

// if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
//     http_response_code(200);
//     exit();
// }

// include "conn.php";

// // ── PHPMailer manual load ─────────────────────────────────────────────────
// require __DIR__ . '/PHPMailer/Exception.php';
// require __DIR__ . '/PHPMailer/PHPMailer.php';
// require __DIR__ . '/PHPMailer/SMTP.php';

// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;

// // ── CONFIG ────────────────────────────────────────────────────────────────
// define('GMAIL_USER',     'mohamedyaasiin86@gmail.com');
// define('GMAIL_APP_PASS', 'xthrghpbthilfzev');
// define('APP_NAME',       'mHealth System');
// // ✅ Real IP — phone iyo laptop isla WiFi
// define('RESET_URL', 'http://192.168.100.2:8081/mHealth_api/reset_password_page.php');

// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     echo json_encode(["success" => false, "message" => "Method not allowed"]);
//     exit();
// }

// $data  = json_decode(file_get_contents("php://input"), true);
// $email = trim($data['email'] ?? '');

// if (empty($email)) {
//     echo json_encode(["success" => false, "message" => "Email address required"]);
//     exit();
// }

// // Check user exists
// $stmt = $connectNow->prepare("SELECT id, full_name FROM users WHERE email = ?");
// $stmt->bind_param("s", $email);
// $stmt->execute();
// $result = $stmt->get_result();

// if ($result->num_rows === 0) {
//     echo json_encode([
//         "success" => false,
//         "message" => "No account found with this email address."
//     ]);
//     exit();
// }

// $user   = $result->fetch_assoc();
// $userId = $user['id'];
// $name   = $user['full_name'] ?? 'User';

// // Delete old tokens
// $del = $connectNow->prepare("DELETE FROM password_resets WHERE user_id = ?");
// $del->bind_param("i", $userId);
// $del->execute();

// // Generate token
// $token     = bin2hex(random_bytes(32));
// $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

// // Save token
// $ins = $connectNow->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
// $ins->bind_param("iss", $userId, $token, $expiresAt);
// if (!$ins->execute()) {
//     echo json_encode(["success" => false, "message" => "Failed to generate reset token."]);
//     exit();
// }

// // ✅ Link-ka real IP isticmaala — browser walba ayuu ka furmi karaa
// $resetLink = RESET_URL . "?token=$token";

// // ── Send Email ────────────────────────────────────────────────────────────
// $mail = new PHPMailer(true);
// try {
//     $mail->isSMTP();
//     $mail->Host       = 'smtp.gmail.com';
//     $mail->SMTPAuth   = true;
//     $mail->Username   = GMAIL_USER;
//     $mail->Password   = GMAIL_APP_PASS;
//     $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
//     $mail->Port       = 587;
//     $mail->CharSet    = 'UTF-8';

//     $mail->setFrom(GMAIL_USER, APP_NAME);
//     $mail->addAddress($email, $name);
//     $mail->isHTML(true);
//     $mail->Subject = APP_NAME . ' — Password Reset Request';

//     $mail->Body = "
//     <html>
//     <body style='margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif'>
//       <table width='100%' cellpadding='0' cellspacing='0'>
//         <tr><td align='center' style='padding:40px 20px'>
//           <table width='520' cellpadding='0' cellspacing='0'
//                  style='background:#fff;border-radius:16px;overflow:hidden;
//                         box-shadow:0 4px 20px rgba(0,0,0,0.08)'>

//             <!-- Header -->
//             <tr>
//               <td style='background:linear-gradient(135deg,#6C63FF,#4F46E5);
//                          padding:36px 40px;text-align:center'>
//                 <div style='font-size:44px;margin-bottom:12px'>🔐</div>
//                 <h1 style='color:#fff;margin:0;font-size:24px;font-weight:700'>
//                   Password Reset
//                 </h1>
//                 <p style='color:rgba(255,255,255,0.75);margin:8px 0 0;font-size:14px'>
//                   mHealth Maternal Healthcare System
//                 </p>
//               </td>
//             </tr>

//             <!-- Body -->
//             <tr>
//               <td style='padding:40px'>
//                 <p style='font-size:16px;color:#1a1a2e;margin:0 0 10px'>
//                   Hello, <strong>$name</strong> 👋
//                 </p>
//                 <p style='font-size:14px;color:#555;line-height:1.7;margin:0 0 28px'>
//                   We received a request to reset your mHealth account password.
//                   Click the button below to set a new password.
//                   This link will expire in <strong>1 hour</strong>.
//                 </p>

//                 <!-- Reset Button -->
//                 <table width='100%' cellpadding='0' cellspacing='0'>
//                   <tr>
//                     <td align='center' style='padding:0 0 28px'>
//                       <a href='$resetLink'
//                          style='display:inline-block;
//                                 background:linear-gradient(135deg,#6C63FF,#4F46E5);
//                                 color:#fff;padding:16px 44px;border-radius:12px;
//                                 text-decoration:none;font-size:16px;font-weight:700;
//                                 letter-spacing:0.3px'>
//                         🔑 Reset My Password
//                       </a>
//                     </td>
//                   </tr>
//                 </table>

//                 <!-- Warning -->
//                 <div style='background:#fff8f0;border-left:4px solid #f59e0b;
//                             padding:14px 16px;border-radius:0 8px 8px 0;margin-bottom:20px'>
//                   <p style='margin:0;font-size:13px;color:#92400e'>
//                     ⚠️ If you did not request this, please ignore this email.
//                     Your password will remain unchanged.
//                   </p>
//                 </div>

//                 <!-- Fallback link -->
//                 <p style='font-size:11px;color:#bbb;text-align:center;word-break:break-all'>
//                   Button not working? Copy this link:<br>
//                   <a href='$resetLink' style='color:#6C63FF'>$resetLink</a>
//                 </p>
//               </td>
//             </tr>

//             <!-- Footer -->
//             <tr>
//               <td style='background:#f9fafb;padding:20px 40px;text-align:center;
//                          border-top:1px solid #eee'>
//                 <p style='margin:0;font-size:12px;color:#aaa'>
//                   &copy; 2026 mHealth System &bull; Maternal Healthcare Platform
//                 </p>
//               </td>
//             </tr>

//           </table>
//         </td></tr>
//       </table>
//     </body>
//     </html>";

//     $mail->AltBody = "Hello $name,\n\nReset your password here:\n$resetLink\n\nExpires in 1 hour.\n\nmHealth System";

//     $mail->send();

//     echo json_encode([
//         "success" => true,
//         "message" => "Password reset link sent to your email. Please check your inbox."
//     ]);

// } catch (Exception $e) {
//     error_log("PHPMailer Error: " . $mail->ErrorInfo);
//     echo json_encode([
//         "success" => false,
//         "message" => "Failed to send email: " . $mail->ErrorInfo
//     ]);
// }



// C:\xampp\htdocs\mHealth_api\forgot_password.php

header("Content-Type: application/json");

header("Access-Control-Allow-Origin: *");

header("Access-Control-Allow-Methods: POST, OPTIONS");

header("Access-Control-Allow-Headers: Content-Type");



if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);

    exit();

}



include "conn.php";



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode(["success" => false, "message" => "Method not allowed"]);

    exit();

}



$data  = json_decode(file_get_contents("php://input"), true);

$email = trim($data['email'] ?? '');



if (empty($email)) {

    echo json_encode(["success" => false, "message" => "Email address required"]);

    exit();

}



// Check user exists

$stmt = $connectNow->prepare("SELECT id, full_name FROM users WHERE email = ?");

$stmt->bind_param("s", $email);

$stmt->execute();

$result = $stmt->get_result();



if ($result->num_rows === 0) {

    // Security: same response whether email exists or not

    echo json_encode([

        "success" => true,

        "message" => "If this email is registered, a reset link has been sent."

    ]);

    exit();

}



$user   = $result->fetch_assoc();

$userId = $user['id'];

$name   = $user['full_name'] ?? 'User';



// Delete old tokens

$del = $connectNow->prepare("DELETE FROM password_resets WHERE user_id = ?");

$del->bind_param("i", $userId);

$del->execute();



// Generate token

$token     = bin2hex(random_bytes(32));

$expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));



// Save token

$ins = $connectNow->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");

$ins->bind_param("iss", $userId, $token, $expiresAt);



if (!$ins->execute()) {

    echo json_encode(["success" => false, "message" => "Failed to generate reset token. Please try again."]);

    exit();

}



// Return token directly to Flutter (no email needed)

// Flutter will navigate to ResetPasswordScreen with this token

echo json_encode([

    "success"  => true,

    "message"  => "Reset token generated successfully.",

    "token"    => $token,

    "name"     => $name,

    "expires"  => $expiresAt

]);