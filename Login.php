<?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // This is a security note: Only POST requests are allowed to ensure safe handling of data.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            "error" => "Invalid request method. Please use POST.",
            "instructions" => "Send a POST request with JSON body containing email and password."
        ]);
        exit();
    }

    $inData = getRequestInfo();

    // This step is to check if the JSON or data is valid
    if ($inData === null) {
        error_log("Raw input received: " . file_get_contents('php://input')); // Log dữ liệu nhận được
        returnWithError("Invalid JSON format or no data received");
        exit();
    }

    // This is to plug the data from JSON
    $email = $inData["email"] ?? null;
    $password = $inData["password"] ?? null;

    // This step is to check if there's anything missing
    if (empty($email) || empty($password)) {
        returnWithError("All fields are required");
        exit();
    }

    // This step is to connect with the database
    $conn = new mysqli("localhost", "testuser", "ProjectPass1s", "Projectdb");
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error); // Log lỗi kết nối database
        returnWithError("Connection failed: " . $conn->connect_error);
        exit();
    }

    // This step is to check the email
    $stmt = $conn->prepare("SELECT uid, username, name, university, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // This step is to compare the password directly without hashing
        if ($password === $row['password']) {
            returnWithInfo($row['uid'], $row['username'], $row['name'], $row['university']);
        } else {
            returnWithError("Invalid password");
        }
    } else {
        returnWithError("No user found");
    }

    $stmt->close();
    $conn->close();

    // This step is extracting JSON data from request
    function getRequestInfo()
    {
        $rawData = file_get_contents('php://input');
        error_log("Raw input received: " . $rawData);
        return json_decode($rawData, true);
    }

    // This step is to returing the result in JSON
    function sendResultInfoAsJson($obj)
    {
        header('Content-type: application/json');
        echo $obj;
    }

    // This is error function
    function returnWithError($err)
    {
        $retValue = '{"id":0,"firstName":"","lastName":"","error":"' . $err . '"}';
        sendResultInfoAsJson($retValue);
    }

    // This step means success
    function returnWithInfo($uid, $username, $name, $university)
    {
        $retValue = '{"id":' . $uid . ',"username":"' . $username . '","name":"' . $name . '","university":"' . $university . '","error":""}';
        sendResultInfoAsJson($retValue);
    }

?>
