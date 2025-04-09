<?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            "error" => "Invalid request method. Please use POST.",
            "instructions" => "Send a POST request with JSON body containing uid and eventID."
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

    if (empty($uid) || empty($eventID)) {
        returnWithError("Both uid and eventID are required");
        exit();
    }

    $conn = new mysqli("localhost", "testuser", "ProjectPass1s", "Projectdb");
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        returnWithError("Connection failed: " . $conn->connect_error);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM comments WHERE uid = ? AND eventID = ?");
    $stmt->bind_param("ii", $uid, $eventID);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        returnWithSuccess("Comment deleted successfully");
    } else {
        error_log("Delete failed: " . $stmt->error);
        returnWithError("Failed to delete comment or comment not found");
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
