<?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            "error" => "Invalid request method. Please use POST.",
            "instructions" => "Send a POST request with JSON body containing uid, eventID, text, and rating."
        ]);
        exit();
    }

    $inData = getRequestInfo();

    if ($inData === null) {
        error_log("Raw input received: " . file_get_contents('php://input'));
        returnWithError("Invalid JSON format or no data received");
        exit();
    }

    $uid = $inData["uid"] ?? null;
    $eventID = $inData["eventID"] ?? null;
    $text = $inData["text"] ?? null;
    $rating = $inData["rating"] ?? null;

    if (empty($uid) || empty($eventID) || empty($text) || $rating === null) {
        returnWithError("All fields (uid, eventID, text, rating) are required");
        exit();
    }

    $conn = new mysqli("localhost", "testuser", "ProjectPass1s", "Projectdb");
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        returnWithError("Connection failed: " . $conn->connect_error);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO comments (uid, eventID, text, rating, commenttime) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiss", $uid, $eventID, $text, $rating);

    if ($stmt->execute()) {
        returnWithSuccess("Comment added successfully");
    } else {
        error_log("Insert failed: " . $stmt->error);
        returnWithError("Failed to add comment");
    }

    $stmt->close();
    $conn->close();

    function getRequestInfo()
    {
        $rawData = file_get_contents('php://input');
        error_log("Raw input received: " . $rawData);
        return json_decode($rawData, true);
    }

    function sendResultInfoAsJson($obj)
    {
        header('Content-type: application/json');
        echo $obj;
    }

    function returnWithError($err)
    {
        $retValue = '{"error":"' . $err . '"}';
        sendResultInfoAsJson($retValue);
    }

    function returnWithSuccess($msg)
    {
        $retValue = '{"message":"' . $msg . '","error":""}';
        sendResultInfoAsJson($retValue);
    }

?>
