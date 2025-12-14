<?php
// get_claim_details.php
session_start();
require_once __DIR__ . '/../config/config.php';

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    exit();
}

// Get claim ID
$claimId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$claimId || $claimId <= 0) {
    http_response_code(400);
    exit();
}

try {
    // Get claim details
    $sql = "SELECT 
                c.claim_id,
                c.lost_id,
                c.found_id,
                c.user_id,
                c.proof_photo,
                c.notes,
                c.status,
                c.approved_by,
                c.created_at,
                c.claimant_name,
                c.date_claimed,
                
                -- Lost Item Details
                l.item_name as lost_item_name,
                l.description as lost_description,
                l.photo as lost_photo,
                l.location_lost as lost_location,
                l.place_lost as lost_place,
                l.date_reported as lost_date,
                l.status as lost_status,
                
                -- Lost Item Reporter
                lu.user_id as lost_reporter_id,
                CONCAT(lu.first_name, ' ', lu.last_name) as lost_reporter_name,
                lu.email as lost_reporter_email,
                lu.student_id as lost_reporter_student_id,
                lu.contact_number as lost_reporter_phone,
                lu.course as lost_reporter_course,
                lu.year as lost_reporter_year,
                
                -- Found Item Details
                f.item_name as found_item_name,
                f.description as found_description,
                f.photo as found_photo,
                f.place_found as found_location,
                f.date_found as found_date,
                f.status as found_status,
                
                -- Finder Details
                fu.user_id as finder_id,
                CONCAT(fu.first_name, ' ', fu.last_name) as finder_name,
                fu.email as finder_email,
                fu.student_id as finder_student_id,
                fu.contact_number as finder_phone,
                fu.course as finder_course,
                fu.year as finder_year,
                
                -- Claimant Details
                u.user_id as claimant_user_id,
                CONCAT(u.first_name, ' ', u.last_name) as claimant_name,
                u.email as claimant_email,
                u.student_id as claimant_student_id,
                u.contact_number as claimant_phone,
                u.course as claimant_course,
                u.year as claimant_year,
                
                -- Approver Details (if any)
                a.user_id as approver_user_id,
                CONCAT(a.first_name, ' ', a.last_name) as approver_name,
                a.email as approver_email,
                
                -- Categories
                ic_lost.category_name as lost_category,
                ic_found.category_name as found_category
            FROM claims c
            LEFT JOIN lost_items l ON c.lost_id = l.lost_id
            LEFT JOIN found_items f ON c.found_id = f.found_id
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN users lu ON l.user_id = lu.user_id
            LEFT JOIN users fu ON f.user_id = fu.user_id
            LEFT JOIN users a ON c.approved_by = a.user_id
            LEFT JOIN item_categories ic_lost ON l.category_id = ic_lost.category_id
            LEFT JOIN item_categories ic_found ON f.category_id = ic_found.category_id
            WHERE c.claim_id = ? 
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$claimId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($claim) {
        header('Content-Type: application/json');
        echo json_encode($claim);
    } else {
        http_response_code(404);
    }
    
} catch(PDOException $e){
    error_log("Error in get_claim_details.php: " . $e->getMessage());
    http_response_code(500);
}
?>