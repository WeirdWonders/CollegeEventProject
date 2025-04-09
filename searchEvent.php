<?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            "error" => "Invalid request method. Please use POST.",
            "instructions" => "Send a POST request with JSON body containing search criteria."
        ]);
        exit();
    }

    $inData = getRequestInfo();

    if ($inData === null) {
        error_log("Raw input received: " . file_get_contents('php://input'));
        returnWithError("Invalid JSON format or no data received");
        exit();
    }

    $eventType = $inData["eventType"] ?? null;
    $university = $inData["university"] ?? null;
    $email = $inData["email"] ?? null;

    if (empty($eventType)) {
        returnWithError("Event type is required");
        exit();
    }

    $conn = new mysqli("localhost", "testuser", "ProjectPass1s", "Projectdb");
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        returnWithError("Connection failed: " . $conn->connect_error);
        exit();
    }

    if ($eventType === "public") {
        $stmt = $conn->prepare("SELECT * FROM events INNER JOIN publicEvents ON events.eventID = publicEvents.eid");
    } elseif ($eventType === "private") {
        if (empty($university)) {
            returnWithError("University is required for private events");
            $conn->close();
            exit();
        }
        $stmt = $conn->prepare("SELECT * FROM events INNER JOIN privateEvents ON events.eventID = privateEvents.eid WHERE events.university = ?");
        $stmt->bind_param("s", $university);
    } elseif ($eventType === "rso") {
        if (empty($email)) {
            returnWithError("Email is required for RSO events");
            $conn->close();
            exit();
        }
        $stmt = $conn->prepare("
            SELECT events.* 
            FROM events 
            INNER JOIN rsoEvents ON events.eventID = rsoEvents.eid 
            INNER JOIN joinRSO ON rsoEvents.rid = joinRSO.rid 
            INNER JOIN users ON joinRSO.uid = users.uid 
            WHERE users.email = ?
        ");
        $stmt->bind_param("s", $email);
    } else {
        returnWithError("Invalid event type");
        $conn->close();
        exit();
    }

    if (!$stmt->execute()) {
        error_log("Search query failed: " . $stmt->error);
        returnWithError("Failed to search events");
        $stmt->close();
        $conn->close();
        exit();
    }

    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        // Fetch comments for the current event
        $eventID = $row['eventID'];
        $commentStmt = $conn->prepare("SELECT uid, text, rating, commenttime FROM comments WHERE eventID = ?");
        $commentStmt->bind_param("i", $eventID);
        $commentStmt->execute();
        $commentResult = $commentStmt->get_result();
        $comments = [];
        while ($commentRow = $commentResult->fetch_assoc()) {
            $comments[] = $commentRow;
        }
        $commentStmt->close();

        // Add comments to the event data
        $row['comments'] = $comments;
        $events[] = $row;
    }

    $stmt->close();
    $conn->close();

    // Add eventType to the response
    $response = [
        "eventType" => ucfirst($eventType),
        "events" => $events
    ];

    sendResultInfoAsJson(json_encode($response));

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

?>
