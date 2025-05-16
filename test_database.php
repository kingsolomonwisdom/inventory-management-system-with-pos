<?php
// Test script to check database connection and sales data
require_once 'config/config.php';

echo "<h1>Database Connection Test</h1>";

try {
    $conn = getConnection();
    echo "<p>Database connection successful!</p>";
    
    // Check tables
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "<h2>Database Tables:</h2>";
        echo "<ul>";
        while ($row = $result->fetch_row()) {
            echo "<li>" . $row[0] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>Error fetching tables: " . $conn->error . "</p>";
    }
    
    // Check sales data
    $result = $conn->query("SELECT COUNT(*) as count FROM sales");
    if ($result) {
        $salesCount = $result->fetch_assoc()['count'];
        echo "<h2>Sales Data:</h2>";
        echo "<p>Total sales records: " . $salesCount . "</p>";
        
        if ($salesCount > 0) {
            $result = $conn->query("SELECT SUM(total_amount) as total FROM sales");
            $totalAmount = $result->fetch_assoc()['total'];
            echo "<p>Total sales amount: " . formatCurrency($totalAmount) . "</p>";
            
            // Get the most recent sales
            $result = $conn->query("SELECT s.id, s.invoice_number, s.customer_name, s.total_amount, s.created_at, u.username 
                                   FROM sales s 
                                   JOIN users u ON s.user_id = u.id 
                                   ORDER BY s.created_at DESC LIMIT 5");
            
            if ($result->num_rows > 0) {
                echo "<h3>5 Most Recent Sales:</h3>";
                echo "<table border='1' cellpadding='5'>
                      <tr>
                        <th>ID</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Staff</th>
                      </tr>";
                
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . $row['invoice_number'] . "</td>";
                    echo "<td>" . ($row['customer_name'] ?: 'Walk-in Customer') . "</td>";
                    echo "<td>" . formatCurrency($row['total_amount']) . "</td>";
                    echo "<td>" . formatDateTime($row['created_at']) . "</td>";
                    echo "<td>" . $row['username'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No sales records found.</p>";
            }
        } else {
            echo "<p>No sales data found.</p>";
        }
    } else {
        echo "<p>Error checking sales: " . $conn->error . "</p>";
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 