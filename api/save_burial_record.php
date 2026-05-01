<?php
header('Content-Type: application/json');

// Database connection
$conn = new mysqli("localhost", "root", "", "gravetrack_db");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get form data
$fullName = $_POST['fullName'] ?? '';
$dateOfBirth = $_POST['dateOfBirth'] ?? null;
$dateOfDeath = $_POST['dateOfDeath'] ?? null;
$dateOfBurial = $_POST['dateOfBurial'] ?? null;
$gender = $_POST['gender'] ?? '';
$contactPerson = $_POST['contactPerson'] ?? '';
$contactNumber = $_POST['contactNumber'] ?? '';
$address = $_POST['address'] ?? '';
$plotId = $_POST['plotId'] ?? null;
$burialType = $_POST['burialType'] ?? '';

// Debug - return received data
$debug = [
    'received' => $_POST,
    'fullName' => $fullName,
    'dateOfDeath' => $dateOfDeath,
    'dateOfBurial' => $dateOfBurial,
    'plotId' => $plotId
];

// Validate required fields
if (empty($fullName) || empty($dateOfDeath) || empty($dateOfBurial) || empty($plotId)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields (Full Name, Date of Death, Date of Burial, Plot)', 'debug' => $debug]);
    exit;
}

// Escape strings
$fullName = $conn->real_escape_string($fullName);
$gender = $conn->real_escape_string($gender);
$contactPerson = $conn->real_escape_string($contactPerson);
$contactNumber = $conn->real_escape_string($contactNumber);
$address = $conn->real_escape_string($address);
$burialType = $conn->real_escape_string($burialType);
$plotId = intval($plotId);

// Validate plotId is numeric
if ($plotId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid plot selected', 'debug' => $debug]);
    exit;
}

// Check if plot exists and is Vacant
$checkPlot = $conn->query("SELECT plot_id, status FROM plots WHERE plot_id = $plotId AND status = 'Vacant'");
if (!$checkPlot || $checkPlot->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Plot not found or not vacant', 'debug' => $debug]);
    exit;
}

// Test query to get column names
$testQuery = $conn->query("SELECT * FROM deceased LIMIT 1");
$testColumns = $testQuery ? array_keys($testQuery->fetch_assoc()) : [];

// Start transaction
$conn->begin_transaction();

try {
    // Insert into deceased table with CORRECT column names from user's table:
    // deceased_id, full_name, birth_date, date_of_death, date_of_burial, gender, address, plot_id, burial_type, created_by
    $sqlDeceased = "INSERT INTO deceased (full_name, birth_date, date_of_death, date_of_burial, gender, address, plot_id, burial_type) 
                    VALUES (
                        '$fullName', 
                        " . ($dateOfBirth ? "'$dateOfBirth'" : "NULL") . ", 
                        " . ($dateOfDeath ? "'$dateOfDeath'" : "NULL") . ", 
                        " . ($dateOfBurial ? "'$dateOfBurial'" : "NULL") . ", 
                        '$gender', 
                        '$address', 
                        $plotId,
                        '$burialType'
                    )";
    
    error_log("SQL Deceased: " . $sqlDeceased);
    
    if (!$conn->query($sqlDeceased)) {
        throw new Exception('Failed to insert deceased: ' . $conn->error . ' | Columns: ' . implode(',', $testColumns));
    }
    
    $deceasedId = $conn->insert_id;
    error_log("Inserted deceased ID: " . $deceasedId);
    
// Insert into contacts table (if contact person provided)
    if (!empty($contactPerson)) {
        $sqlContact = "INSERT INTO contacts (deceased_id, contact_person, contact_number) 
                       VALUES ($deceasedId, '$contactPerson', '$contactNumber')";
        
        error_log("SQL Contact: " . $sqlContact);
        $conn->query($sqlContact);
    }
    
    // Update plot status to Occupied
    $conn->query("UPDATE plots SET status = 'Occupied' WHERE plot_id = $plotId");
    
    // Commit
    $conn->commit();
    
    $message = "Burial record saved! ID: $deceasedId - Plot marked as Occupied";
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'deceased_id' => $deceasedId,
        'debug' => $debug,
        'test_columns' => $testColumns
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => $debug
    ]);
}

$conn->close();
?>
