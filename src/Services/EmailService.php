<?php
namespace Services;

class EmailService
{
    public static function sendLostItemConfirmation($userEmail, $userName, $itemData)
    {
        $subject = "✅ Lost Item Reported - Reference #{$itemData['id']}";
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .item-details { background: white; padding: 15px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Lost Item Reported Successfully</h2>
                </div>
                <div class='content'>
                    <p>Hello {$userName},</p>
                    <p>Your lost item has been reported in LoFIMS system.</p>
                    
                    <div class='item-details'>
                        <h3>Item Details:</h3>
                        <p><strong>Item:</strong> {$itemData['name']}</p>
                        <p><strong>Place Lost:</strong> {$itemData['place']}</p>
                        <p><strong>Reference ID:</strong> #{$itemData['id']}</p>
                    </div>
                    
                    <p>We'll notify you if potential matches are found.</p>
                    <p>Best regards,<br>LoFIMS Team</p>
                </div>
            </div>
        </body>
        </html>";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: LoFIMS System <lofims.system@gmail.com>\r\n";
        $headers .= "Reply-To: LoFIMS Support <lofims.system@gmail.com>\r\n";
        
        return mail($userEmail, $subject, $message, $headers);
    }
    
    public static function sendFoundItemConfirmation($userEmail, $userName, $itemData)
    {
        // Similar function for found items
        $subject = "✅ Found Item Reported - Reference #{$itemData['id']}";
        // ... create HTML email
    }
    
    public static function sendMatchNotification($userEmail, $userName, $lostItem, $foundItems)
    {
        // Send notification when matches are found
        $subject = "🎯 Potential Matches Found for Your Lost Item";
        // ... create HTML email with match details
    }
}
?>
