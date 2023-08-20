<?php

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require $_SERVER["DOCUMENT_ROOT"] . "/res/php/PHPMailer-master/src/Exception.php";
require $_SERVER["DOCUMENT_ROOT"] . "/res/php/PHPMailer-master/src/PHPMailer.php";
require $_SERVER["DOCUMENT_ROOT"] . "/res/php/PHPMailer-master/src/SMTP.php";

// Get email
if (!isset($_POST["email"])) exit("No email provided, redirecting soon... <script>window.setTimeout(()=>location.assign('/'),3000);</script>");
else $email = $_POST["email"];
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) exit("Invalid email");

// Get password
if (!isset($_POST["password"])) exit("No password provided, redirecting soon... <script>window.setTimeout(()=>location.assign('/'),3000);</script>");
else $password = $_POST["password"];

// Get config
require($_SERVER["DOCUMENT_ROOT"] . "/config.php");

// DB Connection
$prod_con = mysqli_connect(
    $settings["production_database"]["host"],
    $settings["production_database"]["username"],
    $settings["production_database"]["password"],
    $settings["production_database"]["database"]
);
if (mysqli_connect_errno()) exit("Error with the Database");

// Check if email has a registered account
$stmt = mysqli_prepare($prod_con, "SELECT password FROM " . $settings["database_tables"]["config_table_name_accounts"] . " WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($password_hash);
$stmt->fetch();

// Check if email exists
if ($stmt->num_rows == 0) exit("No account with this email found - You need to have a registered account to apply for the beta");
$prod_con->close();

// Check if password is correct
if (!password_verify($password, $password_hash)) exit("Wrong password, redirecting soon... <script>window.setTimeout(()=>location.assign('/'),3000);</script>");

// DB Connection
$beta_con = mysqli_connect(
    $settings["beta_database"]["host"],
    $settings["beta_database"]["username"],
    $settings["beta_database"]["password"],
    $settings["beta_database"]["database"]
);
if (mysqli_connect_errno()) exit("Error with the Database");


// Check if email has already applied
$stmt = mysqli_prepare($beta_con, "SELECT state FROM onboarding WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($state);
$stmt->fetch();

// If result row "state" is 1 the user has already been accepted
if ($state == 2) exit("You have already been accepted for the beta");
else if ($stmt->num_rows == 1) {
    // Delete old application
    $stmt = mysqli_prepare($beta_con, "DELETE FROM onboarding WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
}

// Insert new application
$state = 0;
$token = bin2hex(random_bytes(16));
$stmt = mysqli_prepare($beta_con, "INSERT INTO onboarding (email, state, token) VALUES (?, ?, ?)");
$stmt->bind_param("sis", $email, $state, $token);
$stmt->execute();
$stmt->close();

// Check auto-accept
if ($settings["accept_immediately"]) {

    // Send email
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->SMTPDebug  = SMTP::DEBUG_SERVER;                         //Enable verbose debug output
        $mail->isSMTP();                                                //Send using SMTP
        $mail->Host       = $settings["mail"]["host"];                  //Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                       //Enable SMTP authentication
        $mail->Username   = $settings["mail"]["username"];              //SMTP username
        $mail->Password   = $settings["mail"]["password"];              //SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;                //Enable implicit TLS encryption
        $mail->Port       = 465;                                        //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

        // Recipients
        $mail->setFrom($settings["mail"]["sender_mail"], $settings["mail"]["sender_name"]);
        $mail->addAddress($email, $displayname);                        //Add a recipient

        // Content
        $mail->isHTML(true);                                            //Set email format to HTML
        $mail->Subject = 'Noten-App | Beta Application';

        // Content from /mails/apply.html
        $mail->Body       = str_replace("TRANSFERLINK", $token, file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/mails/apply.html"));
        $mail->AltBody    = str_replace("TRANSFERLINK", $token, file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/mails/apply.txt"));

        // Disable debugging
        $mail->SMTPDebug = false;

        // Send mail
        $mail->send();

        // Set state to 1
        if ($stmt = $beta_con->prepare("UPDATE " . $settings["database_tables"]["config_table_name_onboarding"] . " SET state = 2 WHERE token = ?")) {
            $stmt->bind_param("s", $req_token);
            $stmt->execute();
            $stmt->close();
            $beta_con->close();

            // Redirect to login
            header("Location: /success.html");
        } else exit("Error with the Database");
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}
