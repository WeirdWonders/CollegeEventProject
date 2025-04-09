<?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            "error" => "Invalid request method. Please use POST.",
            "instructions" => "Send a POST request with JSON body containing rsoName, adminEmail, university, and members."
        ]);
        exit();
    }

    $inData = getRequestInfo();

    if ($inData === null) {
        error_log("Raw input received: " . file_get_contents('php://input'));
        returnWithError("Invalid JSON format or no data received");
        exit();
    }

    $rsoName = $inData["rsoName"] ?? null;
    $adminEmail = $inData["adminEmail"] ?? null;
    $university = $inData["university"] ?? null;
    $members = $inData["members"] ?? [];

    if (empty($rsoName) || empty($adminEmail) || empty($university) || count($members) < 5) {
        returnWithError("All fields are required, and at least 5 members (including the admin) must be provided.");
        exit();
    }

    $conn = new mysqli("localhost", "testuser", "ProjectPass1s", "Projectdb");
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        returnWithError("Connection failed: " . $conn->connect_error);
        exit();
    }

    $stmt = $conn->prepare("SELECT uid FROM users WHERE email = ?");
    $stmt->bind_param("s", $adminEmail);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($adminRow = $result->fetch_assoc()) {
        $adminUid = $adminRow['uid'];
    } else {
        returnWithError("Admin email not found in the users table.");
        $stmt->close();
        $conn->close();
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO RSO (rsoName, adminEmail, university) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $rsoName, $adminEmail, $university);
    if (!$stmt->execute()) {
        error_log("Failed to create RSO: " . $stmt->error);
        returnWithError("Failed to create RSO.");
        $stmt->close();
        $conn->close();
        exit();
    }

    $rsoId = $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO joinRSO (uid, rid) VALUES (?, ?)");
    foreach ($members as $email) {
        $stmt->bind_param("is", $uid, $rsoId);

        $userStmt = $conn->prepare("SELECT uid FROM users WHERE email = ?");
        $userStmt->bind_param("s", $email);
        $userStmt->execute();
        $userResult = $userStmt->get_result();

        if ($userRow = $userResult->fetch_assoc()) {
            $uid = $userRow['uid'];
            $stmt->execute();
        } else {
            error_log("User email not found: " . $email);
        }

        $userStmt->close();
    }

    $stmt->close();
    $conn->close();

    returnWithInfo($rsoId, $rsoName, $adminEmail, $university);

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
        $retValue = '{"id":0,"error":"' . $err . '"}';
        sendResultInfoAsJson($retValue);
    }

    function returnWithInfo($rsoId, $rsoName, $adminEmail, $university)
    {
        $retValue = '{"id":' . $rsoId . ',"rsoName":"' . $rsoName . '","adminEmail":"' . $adminEmail . '","university":"' . $university . '","error":""}';
        sendResultInfoAsJson($retValue);
    }

?>
