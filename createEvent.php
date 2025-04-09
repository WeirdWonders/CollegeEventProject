<?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            "error" => "Invalid request method. Please use POST.",
            "instructions" => "Send a POST request with JSON body containing event details."
        ]);
        exit();
    }

    $inData = getRequestInfo();

    if ($inData === null) {
        error_log("Raw input received: " . file_get_contents('php://input'));
        returnWithError("Invalid JSON format or no data received");
        exit();
    }

    $eventName = $inData["eventName"] ?? null;
    $eventtime = $inData["eventtime"] ?? null;
    $Lname = $inData["Lname"] ?? null;
    $descript = $inData["descript"] ?? null;
    $phoneNo = $inData["phoneNo"] ?? null;
    $email = $inData["email"] ?? null;
    $eventType = $inData["eventType"] ?? null; // private, rso, or public
    $typeId = $inData["typeId"] ?? null; // aid for private/public, rid for rso
    $university = $inData["university"] ?? null; 

    if (empty($eventName) || empty($eventtime) || empty($Lname) || empty($eventType) || empty($typeId) || empty($university)) {
        returnWithError("All fields are required");
        exit();
    }

    $conn = new mysqli("localhost", "testuser", "ProjectPass1s", "Projectdb");
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        returnWithError("Connection failed: " . $conn->connect_error);
        exit();
    }

    // Check if location exists, if not, create it
    $locationCheckStmt = $conn->prepare("SELECT Lname FROM location WHERE Lname = ?");
    $locationCheckStmt->bind_param("s", $Lname);
    $locationCheckStmt->execute();
    $locationCheckStmt->store_result();

    if ($locationCheckStmt->num_rows === 0) {
        $locationCheckStmt->close();
        $createLocationStmt = $conn->prepare("INSERT INTO location (Lname) VALUES (?)");
        $createLocationStmt->bind_param("s", $Lname);
        if (!$createLocationStmt->execute()) {
            error_log("Failed to create location: " . $createLocationStmt->error);
            returnWithError("Failed to create location");
            $createLocationStmt->close();
            $conn->close();
            exit();
        }
        $createLocationStmt->close();
    } else {
        $locationCheckStmt->close();
    }

    $stmt = $conn->prepare("INSERT INTO events (eventtime, Lname, eventName, descript, phoneNo, email, university) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $eventtime, $Lname, $eventName, $descript, $phoneNo, $email, $university);

    if (!$stmt->execute()) {
        error_log("Event insertion failed: " . $stmt->error);
        returnWithError("Failed to create event");
        $stmt->close();
        $conn->close();
        exit();
    }

    $eventID = $stmt->insert_id;
    $stmt->close();

    if ($eventType === "private") {
        $stmt = $conn->prepare("INSERT INTO privateEvents (eid, aid, said) VALUES (?, ?, ?)");
        $said = 1; // Default value for 'said'
        $stmt->bind_param("iii", $eventID, $typeId, $said);
    } elseif ($eventType === "rso") {
        $stmt = $conn->prepare("INSERT INTO rsoEvents (eid, rid) VALUES (?, ?)");
        $stmt->bind_param("ii", $eventID, $typeId);
    } elseif ($eventType === "public") {
        $stmt = $conn->prepare("INSERT INTO publicEvents (eid, aid, said) VALUES (?, ?, ?)");
        $said = 1; // Default value for 'said'
        $stmt->bind_param("iii", $eventID, $typeId, $said);
    } else {
        returnWithError("Invalid event type");
        $conn->close();
        exit();
    }

    if (!$stmt->execute()) {
        error_log("Event type association failed: " . $stmt->error);
        returnWithError("Failed to associate event with type");
    } else {
        returnWithInfo($eventID, $eventName, $eventType);
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
        $retValue = '{"id":0,"eventName":"","eventType":"","error":"' . $err . '"}';
        sendResultInfoAsJson($retValue);
    }

    function returnWithInfo($eventID, $eventName, $eventType)
    {
        $retValue = '{"id":' . $eventID . ',"eventName":"' . $eventName . '","eventType":"' . $eventType . '","error":""}';
        sendResultInfoAsJson($retValue);
    }

?>
