<?php

namespace LoFIMS\Services;

use PDO;

class VerificationService
{
    private $db;
    private $emailService;
    private $smsService;
    
    public function __construct(PDO $db, EmailService $emailService = null, SMSService $smsService = null)
    {
        $this->db = $db;
        $this->emailService = $emailService;
        $this->smsService = $smsService;
    }
    
    /**
     * Generate email verification token
     */
    public function generateEmailVerification(int $userId, string $email): string
    {
        // Delete any existing tokens for this user
        $this->db->prepare("DELETE FROM email_verifications WHERE user_id = :user_id")
                 ->execute([':user_id' => $userId]);
        
        // Generate unique token
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        
        // Store verification record
        $sql = "INSERT INTO email_verifications (user_id, email, token) 
                VALUES (:user_id, :email, :token)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':email' => $email,
            ':token' => $hashedToken
        ]);
        
        return $token;
    }
    
    /**
     * Send email verification
     */
    public function sendEmailVerification(int $userId, string $email, string $username): bool
    {
        if (!$this->emailService) {
            return false;
        }
        
        // Generate token
        $token = $this->generateEmailVerification($userId, $email);
        
        // Send verification email
        return $this->emailService->sendVerificationEmail($email, $username, $token);
    }
    
    /**
     * Verify email token
     */
    public function verifyEmailToken(string $token): array
    {
        $hashedToken = hash('sha256', $token);
        
        $sql = "SELECT ev.*, u.username 
                FROM email_verifications ev
                JOIN users u ON ev.user_id = u.user_id
                WHERE ev.token = :token AND ev.expires_at > NOW() AND ev.verified_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':token' => $hashedToken]);
        
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$verification) {
            return ['success' => false, 'error' => 'Invalid or expired token'];
        }
        
        // Mark as verified
        $this->db->beginTransaction();
        
        try {
            // Update verification record
            $stmt = $this->db->prepare("
                UPDATE email_verifications 
                SET verified_at = NOW() 
                WHERE verification_id = :id
            ");
            $stmt->execute([':id' => $verification['verification_id']]);
            
            // Update user record
            $stmt = $this->db->prepare("
                UPDATE users 
                SET email_verified = 1, email = :email
                WHERE user_id = :user_id
            ");
            $stmt->execute([
                ':email' => $verification['email'],
                ':user_id' => $verification['user_id']
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'user_id' => $verification['user_id'],
                'username' => $verification['username'],
                'email' => $verification['email']
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate SMS verification code
     */
    public function generateSMSVerification(int $userId, string $phone): string
    {
        // Delete any existing codes for this user
        $this->db->prepare("DELETE FROM sms_verifications WHERE user_id = :user_id")
                 ->execute([':user_id' => $userId]);
        
        // Generate 6-digit code
        $code = sprintf('%06d', random_int(0, 999999));
        
        // Store verification record
        $sql = "INSERT INTO sms_verifications (user_id, phone, code) 
                VALUES (:user_id, :phone, :code)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':phone' => $phone,
            ':code' => $code
        ]);
        
        return $code;
    }
    
    /**
     * Send SMS verification
     */
    public function sendSMSVerification(int $userId, string $phone, string $username): bool
    {
        if (!$this->smsService) {
            return false;
        }
        
        // Generate code
        $code = $this->generateSMSVerification($userId, $phone);
        
        // Send verification SMS
        $result = $this->smsService->sendVerificationSMS($phone, $code);
        
        return $result['success'];
    }
    
    /**
     * Verify SMS code
     */
    public function verifySMSCode(int $userId, string $code): array
    {
        $sql = "SELECT sv.*, u.username 
                FROM sms_verifications sv
                JOIN users u ON sv.user_id = u.user_id
                WHERE sv.user_id = :user_id 
                AND sv.code = :code 
                AND sv.expires_at > NOW() 
                AND sv.verified_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':code' => $code
        ]);
        
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$verification) {
            return ['success' => false, 'error' => 'Invalid or expired code'];
        }
        
        // Mark as verified
        $this->db->beginTransaction();
        
        try {
            // Update verification record
            $stmt = $this->db->prepare("
                UPDATE sms_verifications 
                SET verified_at = NOW() 
                WHERE verification_id = :id
            ");
            $stmt->execute([':id' => $verification['verification_id']]);
            
            // Update user record
            $stmt = $this->db->prepare("
                UPDATE users 
                SET phone_verified = 1, phone = :phone
                WHERE user_id = :user_id
            ");
            $stmt->execute([
                ':phone' => $verification['phone'],
                ':user_id' => $verification['user_id']
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'user_id' => $verification['user_id'],
                'username' => $verification['username'],
                'phone' => $verification['phone']
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Resend email verification
     */
    public function resendEmailVerification(int $userId): bool
    {
        $stmt = $this->db->prepare("SELECT email, username FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        return $this->sendEmailVerification($userId, $user['email'], $user['username']);
    }
    
    /**
     * Resend SMS verification
     */
    public function resendSMSVerification(int $userId): bool
    {
        $stmt = $this->db->prepare("SELECT phone, username FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || empty($user['phone'])) {
            return false;
        }
        
        return $this->sendSMSVerification($userId, $user['phone'], $user['username']);
    }
    
    /**
     * Check if verification exists for user
     */
    public function hasPendingEmailVerification(int $userId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM email_verifications 
                WHERE user_id = :user_id AND expires_at > NOW() AND verified_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }
    
    public function hasPendingSMSVerification(int $userId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM sms_verifications 
                WHERE user_id = :user_id AND expires_at > NOW() AND verified_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }
}

