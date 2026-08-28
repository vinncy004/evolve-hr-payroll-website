<?php
/**
 * Email Service Configuration
 * 
 * Handles all email operations using PHPMailer:
 * - Payslip delivery to employees
 * - Welcome emails for new organizations
 * - Password reset emails
 * - Notifications
 * backend/utils/EmailService.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            // Try to include from common locations
            $possible_paths = [
                __DIR__ . '/../../vendor/autoload.php',
                __DIR__ . '/../vendor/autoload.php',
                __DIR__ . '/phpmailer/autoload.php'
            ];
            
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    break;
                }
            }
        }
        
        $this->mailer = new PHPMailer(true);
        $this->configureSMTP();
    }
    
    /**
     * Configure SMTP settings
     * Update these with your actual SMTP credentials
     */
    private function configureSMTP() {
        try {
            // SMTP Configuration
            $this->mailer->isSMTP();
            $this->mailer->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com'; // Change to your SMTP host
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = getenv('SMTP_USERNAME') ?: 'your-email@gmail.com'; // Change to your email
            $this->mailer->Password   = getenv('SMTP_PASSWORD') ?: 'your-app-password'; // Change to your app password
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port       = getenv('SMTP_PORT') ?: 587;
            
            // Sender info
            $this->from_email = getenv('FROM_EMAIL') ?: 'noreply@yourcompany.com';
            $this->from_name  = getenv('FROM_NAME') ?: 'Payroll System';
            
            $this->mailer->setFrom($this->from_email, $this->from_name);
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log("Email configuration error: " . $e->getMessage());
        }
    }
    
    /**
     * Send payslip email to employee
     * 
     * @param string $to_email Employee's email address
     * @param string $employee_name Employee's full name
     * @param array $payroll_data Payroll details
     * @param string $pdf_path Optional PDF attachment path
     * @return array Result with success status and message
     */
    public function sendPayslip($to_email, $employee_name, $payroll_data, $pdf_path = null) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            $this->mailer->addAddress($to_email, $employee_name);
            $this->mailer->Subject = "Payslip for " . $payroll_data['period'];
            
            // Email body
            $body = $this->getPayslipEmailTemplate($employee_name, $payroll_data);
            $this->mailer->Body = $body;
            
            // Plain text alternative
            $this->mailer->AltBody = $this->getPayslipPlainText($employee_name, $payroll_data);
            
            // Attach PDF if provided
            if ($pdf_path && file_exists($pdf_path)) {
                $this->mailer->addAttachment($pdf_path, 'payslip.pdf');
            }
            
            $this->mailer->send();
            
            return [
                'success' => true,
                'message' => 'Payslip sent successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Failed to send payslip email: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send welcome email to new organization admin
     */
    public function sendWelcomeEmail($to_email, $admin_name, $organization_name, $username) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to_email, $admin_name);
            $this->mailer->Subject = "Welcome to Payroll System - " . $organization_name;
            
            $body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #1976d2; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .button { display: inline-block; padding: 10px 20px; background: #1976d2; 
                              color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                    .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Welcome to Payroll System!</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello {$admin_name},</h2>
                        <p>Congratulations! Your organization <strong>{$organization_name}</strong> has been successfully registered.</p>
                        <p><strong>Your Login Details:</strong></p>
                        <ul>
                            <li>Username: <strong>{$username}</strong></li>
                            <li>Login URL: <a href='http://localhost:5173'>Access Dashboard</a></li>
                        </ul>
                        <p>You can now:</p>
                        <ul>
                            <li>✓ Onboard employees</li>
                            <li>✓ Manage departments and positions</li>
                            <li>✓ Process payroll</li>
                            <li>✓ Generate reports</li>
                        </ul>
                        <p><a href='http://localhost:5173' class='button'>Login to Dashboard</a></p>
                        <p>Your trial period is valid for 30 days. After that, please subscribe to continue using the service.</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                        <p>&copy; 2025 Payroll System. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $this->mailer->Body = $body;
            $this->mailer->AltBody = "Welcome to Payroll System!\n\nYour organization {$organization_name} has been registered.\nUsername: {$username}\nLogin at: http://localhost:5173";
            
            $this->mailer->send();
            
            return ['success' => true, 'message' => 'Welcome email sent'];
            
        } catch (Exception $e) {
            error_log("Failed to send welcome email: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Send employee onboarding email with login credentials
     */
    public function sendEmployeeOnboardingEmail($to_email, $employee_name, $employee_number, 
                                                $username, $password, $organization_name) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to_email, $employee_name);
            $this->mailer->Subject = "Welcome to {$organization_name} - Your Account Details";
            
            $body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #1976d2; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .credentials { background: white; padding: 15px; border-left: 4px solid #1976d2; margin: 15px 0; }
                    .button { display: inline-block; padding: 10px 20px; background: #1976d2; 
                              color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                    .warning { background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 15px 0; }
                    .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Welcome to {$organization_name}!</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello {$employee_name},</h2>
                        <p>Your employee account has been successfully created. Here are your login credentials:</p>
                        
                        <div class='credentials'>
                            <p><strong>Employee Number:</strong> {$employee_number}</p>
                            <p><strong>Username:</strong> {$username}</p>
                            <p><strong>Temporary Password:</strong> {$password}</p>
                            <p><strong>Portal URL:</strong> <a href='http://localhost:5173'>Employee Portal</a></p>
                        </div>
                        
                        <div class='warning'>
                            <strong>⚠️ Security Notice:</strong> Please change your password after your first login.
                        </div>
                        
                        <p>Through the employee portal, you can:</p>
                        <ul>
                            <li>View your payslips</li>
                            <li>Apply for leave</li>
                            <li>Update your profile</li>
                            <li>View attendance records</li>
                        </ul>
                        
                        <p><a href='http://localhost:5173' class='button'>Access Employee Portal</a></p>
                    </div>
                    <div class='footer'>
                        <p>If you have any questions, please contact your HR department.</p>
                        <p>&copy; 2025 {$organization_name}. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $this->mailer->Body = $body;
            $this->mailer->AltBody = "Welcome to {$organization_name}!\n\nEmployee Number: {$employee_number}\nUsername: {$username}\nPassword: {$password}\n\nLogin at: http://localhost:5173";
            
            $this->mailer->send();
            
            return ['success' => true, 'message' => 'Onboarding email sent'];
            
        } catch (Exception $e) {
            error_log("Failed to send onboarding email: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get HTML template for payslip email
     */
    private function getPayslipEmailTemplate($employee_name, $payroll_data) {
        $gross_pay = number_format($payroll_data['gross_pay'], 2);
        $deductions = number_format($payroll_data['total_deductions'], 2);
        $net_pay = number_format($payroll_data['net_pay'], 2);
        $period = $payroll_data['period'];
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1976d2; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .payslip-table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; }
                .payslip-table th { background: #1976d2; color: white; padding: 12px; text-align: left; }
                .payslip-table td { padding: 10px; border-bottom: 1px solid #ddd; }
                .total-row { font-weight: bold; background: #f0f0f0; font-size: 1.1em; }
                .net-pay { background: #4caf50; color: white; font-size: 1.3em; padding: 15px; text-align: center; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Payslip - {$period}</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$employee_name},</h2>
                    <p>Your payslip for <strong>{$period}</strong> is ready. Please find the details below:</p>
                    
                    <table class='payslip-table'>
                        <tr>
                            <th colspan='2'>Earnings</th>
                        </tr>
                        <tr>
                            <td>Basic Salary</td>
                            <td style='text-align: right;'>KES {$gross_pay}</td>
                        </tr>
                        <tr class='total-row'>
                            <td>Gross Pay</td>
                            <td style='text-align: right;'>KES {$gross_pay}</td>
                        </tr>
                    </table>
                    
                    <table class='payslip-table'>
                        <tr>
                            <th colspan='2'>Deductions</th>
                        </tr>
                        <tr>
                            <td>Total Deductions (PAYE, NSSF, SHIF)</td>
                            <td style='text-align: right;'>KES {$deductions}</td>
                        </tr>
                    </table>
                    
                    <div class='net-pay'>
                        <strong>Net Pay: KES {$net_pay}</strong>
                    </div>
                    
                    <p style='margin-top: 20px;'>A detailed PDF payslip is attached to this email for your records.</p>
                    
                    <p><small>Note: This payslip is computer-generated and does not require a signature.</small></p>
                </div>
                <div class='footer'>
                    <p>This is a confidential document. Please keep it secure.</p>
                    <p>&copy; 2025 Payroll System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get plain text version of payslip
     */
    private function getPayslipPlainText($employee_name, $payroll_data) {
        return "
Payslip - {$payroll_data['period']}

Hello {$employee_name},

Your payslip for {$payroll_data['period']} is ready.

EARNINGS:
Gross Pay: KES " . number_format($payroll_data['gross_pay'], 2) . "

DEDUCTIONS:
Total Deductions: KES " . number_format($payroll_data['total_deductions'], 2) . "

NET PAY: KES " . number_format($payroll_data['net_pay'], 2) . "

A detailed PDF payslip is attached.

This is a confidential document. Please keep it secure.
        ";
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($to_email, $user_name, $reset_link) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to_email, $user_name);
            $this->mailer->Subject = 'Password Reset - Evolve Payroll';
            $this->mailer->isHTML(true);
            $this->mailer->Body = "
                <p>Hello " . htmlspecialchars($user_name) . ",</p>
                <p>We received a request to reset your password. Click the link below to set a new password:</p>
                <p><a href='" . htmlspecialchars($reset_link) . "'>Reset Password</a></p>
                <p>This link expires in 1 hour. If you did not request this, ignore this email.</p>
            ";
            $this->mailer->AltBody = "Reset your password: {$reset_link}";
            $this->mailer->send();
            return ['success' => true];
        } catch (Exception $e) {
            error_log('[EmailService] Password reset email failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Test email configuration
     */
    public function testConnection() {
        try {
            $this->mailer->smtpConnect();
            return [
                'success' => true,
                'message' => 'SMTP connection successful'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SMTP connection failed: ' . $e->getMessage()
            ];
        }
    }
}
?>
