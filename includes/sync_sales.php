<?php


// Include configuration
require_once dirname(__DIR__) . '/config/config.php';

// Log function
function logMessage($message) {
    echo date('Y-m-d H:i:s') . " - $message" . PHP_EOL;
}

logMessage("Sales data synchronization started");

$conn = getConnection();
$conn->begin_transaction();

try {
    // 1. Fix any missing product_name in sale_items by filling from products table
    logMessage("Checking for missing product names...");
    $stmt = $conn->prepare("
        UPDATE sale_items si 
        JOIN products p ON si.product_id = p.id 
        SET si.product_name = p.name 
        WHERE si.product_name = '' OR si.product_name IS NULL
    ");
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    logMessage("Fixed $affected records with missing product names");

    // 2. Verify sale totals match the sum of their items
    logMessage("Checking sale totals against item sums...");
    $stmt = $conn->prepare("
        SELECT s.id, s.total_amount, SUM(si.price * si.quantity) as calculated_total
        FROM sales s
        JOIN sale_items si ON s.id = si.sale_id
        GROUP BY s.id
        HAVING ABS(s.total_amount - calculated_total) > 0.01
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $mismatchCount = $result->num_rows;
    
    if ($mismatchCount > 0) {
        logMessage("Found $mismatchCount sales with total amount discrepancies");
        
        // Update the sales totals to match item sums
        $stmt = $conn->prepare("
            UPDATE sales s
            JOIN (
                SELECT sale_id, SUM(price * quantity) as calculated_total
                FROM sale_items
                GROUP BY sale_id
            ) calc ON s.id = calc.sale_id
            SET s.total_amount = calc.calculated_total
            WHERE ABS(s.total_amount - calc.calculated_total) > 0.01
        ");
        $stmt->execute();
        $fixedCount = $stmt->affected_rows;
        $stmt->close();
        logMessage("Fixed $fixedCount sales records with incorrect totals");
    } else {
        logMessage("No sale total discrepancies found");
    }
    $stmt->close();
    
    // 3. Check for orphaned sale_items (with no parent sale)
    logMessage("Checking for orphaned sale items...");
    $stmt = $conn->prepare("
        SELECT COUNT(*) as orphan_count
        FROM sale_items si
        LEFT JOIN sales s ON si.sale_id = s.id
        WHERE s.id IS NULL
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $orphanCount = $row['orphan_count'];
    $stmt->close();
    
    if ($orphanCount > 0) {
        logMessage("Found $orphanCount orphaned sale items - cleaning up");
        $stmt = $conn->prepare("
            DELETE si FROM sale_items si
            LEFT JOIN sales s ON si.sale_id = s.id
            WHERE s.id IS NULL
        ");
        $stmt->execute();
        $cleanedCount = $stmt->affected_rows;
        $stmt->close();
        logMessage("Removed $cleanedCount orphaned sale items");
    } else {
        logMessage("No orphaned sale items found");
    }
    
    // Commit the transaction
    $conn->commit();
    logMessage("Sales data synchronization completed successfully");
    
} catch (Exception $e) {
    // Rollback the transaction on error
    $conn->rollback();
    logMessage("ERROR: " . $e->getMessage());
    exit(1);
} finally {
    $conn->close();
} 