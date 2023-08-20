<?php

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require $_SERVER["DOCUMENT_ROOT"] . "/res/php/PHPMailer-master/src/Exception.php";
require $_SERVER["DOCUMENT_ROOT"] . "/res/php/PHPMailer-master/src/PHPMailer.php";
require $_SERVER["DOCUMENT_ROOT"] . "/res/php/PHPMailer-master/src/SMTP.php";

// Get config
require($_SERVER["DOCUMENT_ROOT"] . "/config.php");

// Get Token
if (!isset($_POST["token"])) exit("Token missing, redirecting soon... <script>window.setTimeout(()=>location.assign('/'),3000);</script>");
else $req_token = $_POST["token"];

// DB Connection
$beta_con = mysqli_connect(
    $settings["beta_database"]["host"],
    $settings["beta_database"]["username"],
    $settings["beta_database"]["password"],
    $settings["beta_database"]["database"]
);
if (mysqli_connect_errno()) exit("Error with the Database");

// Check token
if ($stmt = $beta_con->prepare("SELECT email, state FROM " . $settings["database_tables"]["config_table_name_onboarding"] . " WHERE `token` = ?")) {
    $stmt->bind_param("s", $req_token);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 0) exit("Token invalid, redirecting soon... <script>window.setTimeout(()=>location.assign('/'),3000);</script>");
    $stmt->bind_result($email, $state);
    $stmt->fetch();
    $stmt->close();
} else exit("Error with the Database");

// DB Connection
$beta_con->close();
$prod_con = mysqli_connect(
    $settings["production_database"]["host"],
    $settings["production_database"]["username"],
    $settings["production_database"]["password"],
    $settings["production_database"]["database"]
);
if (mysqli_connect_errno()) exit("Error with the Database");

//
// Get tables from PROD
//

// Table "accounts"
$accounts_data = array();
if ($stmt = $prod_con->prepare("SELECT id,displayname,username,password,account_creation,account_version,delete_until,beta_tester,rounding,sorting,gradesystem from " . $settings["database_tables"]["config_table_name_accounts"] . " WHERE email = ?")) {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 0) exit("No account with this email found - You need to have a registered account to apply for the beta");
    $stmt->bind_result($accounts_data["id"], $accounts_data["displayname"], $accounts_data["username"], $accounts_data["password"], $accounts_data["account_creation"], $accounts_data["account_version"], $accounts_data["delete_until"], $accounts_data["beta_tester"], $accounts_data["rounding"], $accounts_data["sorting"], $accounts_data["gradesystem"]);
    $stmt->fetch();
    $stmt->close();
} else exit("Error with the Database");

// Table "classes" (=> "subjects") - multiple rows
$subjects_data = array();
if ($stmt = $prod_con->prepare("SELECT * FROM " . $settings["database_tables"]["config_table_name_classes"] . " WHERE user_id = ?")) {
    $stmt->bind_param("s", $accounts_data["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) exit("No subjects found - You need to have at least one subject to apply for the beta");
    while ($row = $result->fetch_assoc()) {
        array_push($subjects_data, $row);
    }
    $stmt->close();
} else exit("Error with the Database");

// Table "grades" - multiple rows
$grades_data = array();
if ($stmt = $prod_con->prepare("SELECT * FROM " . $settings["database_tables"]["config_table_name_grades"] . " WHERE user_id = ?")) {
    $stmt->bind_param("s", $accounts_data["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) exit("No grades found - You need to have at least one grade to apply for the beta");
    while ($row = $result->fetch_assoc()) {
        array_push($grades_data, $row);
    }
    $stmt->close();
} else exit("Error with the Database");

// Table "homework" - multiple rows
$homework_data = array();
if ($stmt = $prod_con->prepare("SELECT * FROM " . $settings["database_tables"]["config_table_name_homework"] . " WHERE user_id = ?")) {
    $stmt->bind_param("s", $accounts_data["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        array_push($homework_data, $row);
    }
    $stmt->close();
} else exit("Error with the Database");

// DB Connection
$prod_con->close();
$beta_con = mysqli_connect(
    $settings["beta_database"]["host"],
    $settings["beta_database"]["username"],
    $settings["beta_database"]["password"],
    $settings["beta_database"]["database"]
);
if (mysqli_connect_errno()) exit("Error with the Database");

//
// Insert tables into BETA
//

// Table "accounts"
if ($stmt = $beta_con->prepare("INSERT INTO " . $settings["database_tables"]["config_table_name_accounts"] . " (id,displayname,username,password,email,account_creation,account_version,delete_until,rounding,sorting,gradesystem) VALUES (?,?,?,?,?,?,?,?,?,?,?)")) {
    $stmt->bind_param("sssssssssss", $accounts_data["id"], $accounts_data["displayname"], $accounts_data["username"], $accounts_data["password"], $email, $accounts_data["account_creation"], $accounts_data["account_version"], $accounts_data["delete_until"], $accounts_data["rounding"], $accounts_data["sorting"], $accounts_data["gradesystem"]);
    $stmt->execute();
    $stmt->close();
} else exit("Error with the Database");

// Table "classes" (=> "subjects") - multiple rows
if ($stmt = $beta_con->prepare("INSERT INTO " . $settings["database_tables"]["config_table_name_classes"] . " (name, color, user_id, id, last_used, grade_k, grade_m, grade_t, grade_s, average) VALUES (?,?,?,?,?,?,?,?,?,?)")) {
    foreach ($subjects_data as $row) {
        $stmt->bind_param("ssssssssss", $row["name"], $row["color"], $accounts_data["id"], $row["id"], $row["last_used"], $row["grade_k"], $row["grade_m"], $row["grade_t"], $row["grade_s"], $row["average"]);
        $stmt->execute();
    }
    $stmt->close();
} else exit("Error with the Database");

// Table "grades" - multiple rows
if ($stmt = $beta_con->prepare("INSERT INTO " . $settings["database_tables"]["config_table_name_grades"] . " (id,user_id,class,type,note,date,grade) VALUES (?,?,?,?,?,?,?)")) {
    foreach ($grades_data as $row) {
        $stmt->bind_param("sssssss", $row["id"], $accounts_data["id"], $row["class"], $row["type"], $row["note"], $row["date"], $row["grade"]);
        $stmt->execute();
    }
    $stmt->close();
} else exit("Error with the Database");

// Table "homework" - multiple rows
if ($stmt = $beta_con->prepare("INSERT INTO " . $settings["database_tables"]["config_table_name_homework"] . " (user_id, entry_id, given, deadline, text, type, status) VALUES (?,?,?,?,?,?,?)")) {
    foreach ($homework_data as $row) {
        $stmt->bind_param("sssssss", $accounts_data["id"], $row["entry_id"], $row["given"], $row["deadline"], $row["text"], $row["type"], $row["status"]);
        $stmt->execute();
    }
    $stmt->close();
} else exit("Error with the Database");

// 
// Table "onboarding"
// 
if ($stmt = $beta_con->prepare("UPDATE " . $settings["database_tables"]["config_table_name_onboarding"] . " SET state = 2 WHERE token = ?")) {
    $stmt->bind_param("s", $req_token);
    $stmt->execute();
    $stmt->close();
} else exit("Error with the Database");

exit("success");
