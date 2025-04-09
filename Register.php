<?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // Only POST requests are allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            "error" => "Invalid request method. Please use POST.",
            "instructions" => "Send a POST request with JSON body containing username, password, name, email, and university."
        ]);
        exit();
    }

    $inData = getRequestInfo();

    // Validate JSON input
    if ($inData === null) {
        error_log("Raw input received: " . file_get_contents('php://input'));
        returnWithError("Invalid JSON format or no data received");
        exit();
    }

    // Extract and validate fields
    $password = $inData["password"] ?? null;
    $name = $inData["name"] ?? null;
    $email = $inData["email"] ?? null;
    $university = $inData["university"] ?? null;

    if (empty($password) || empty($name) || empty($email) || empty($university)) {
        returnWithError("All fields are required");
        exit();
    }

    // Connect to the database
    $conn = new mysqli("localhost", "testuser", "ProjectPass1s", "Projectdb");
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        returnWithError("Connection failed: " . $conn->connect_error);
        exit();
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT uid FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->fetch_assoc()) {
        returnWithError("Email is already registered");
        $stmt->close();
        $conn->close();
        exit();
    }

    $stmt->close();

    // Insert new user with blank username
    $stmt = $conn->prepare("INSERT INTO users (username, password, name, email, university) VALUES ('', ?, ?, ?, ?)");
    $stmt->bind_param("ssss", $password, $name, $email, $university);

    if ($stmt->execute()) {
        returnWithInfo($conn->insert_id, "", $name, $university);
    } else {
        error_log("Insert failed: " . $stmt->error);
        returnWithError("Failed to register user");
    }

    $stmt->close();
    $conn->close();

    // Extract JSON data from request
    function getRequestInfo()
    {
        $rawData = file_get_contents('php://input');
        error_log("Raw input received: " . $rawData);
        return json_decode($rawData, true);
    }

    // Send result as JSON
    function sendResultInfoAsJson($obj)
    {
        header('Content-type: application/json');
        echo $obj;
    }

    // Error response
    function returnWithError($err)
    {
        $retValue = '{"id":0,"username":"","name":"","university":"","error":"' . $err . '"}';
        sendResultInfoAsJson($retValue);
    }

    // Success response
    function returnWithInfo($uid, $username, $name, $university)
    {
        $retValue = '{"id":' . $uid . ',"username":"' . $username . '","name":"' . $name . '","university":"' . $university . '","error":""}';
        sendResultInfoAsJson($retValue);
    }

?>
