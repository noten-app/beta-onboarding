<?php

// Get config
require($_SERVER["DOCUMENT_ROOT"] . "/config.php");

// DB Connection
$beta_con = mysqli_connect(
    $settings["beta_database"]["host"],
    $settings["beta_database"]["username"],
    $settings["beta_database"]["password"],
    $settings["beta_database"]["database"]
);
if (mysqli_connect_errno()) exit("Error with the Database");

// Get Token
if (!isset($_GET["token"])) exit("Token missing, redirecting soon... <script>window.setTimeout(()=>location.assign('/'),3000);</script>");
else $req_token = $_GET["token"];

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

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beta-Onboarding | Noten-App</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/fonts.css">
    <link rel="apple-touch-icon" sizes="180x180" href="https://assets.noten-app.de/images/logo/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="https://assets.noten-app.de/images/logo/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="https://assets.noten-app.de/images/logo/favicon-16x16.png">
    <link rel="mask-icon" href="https://assets.noten-app.de/images/logo/safari-pinned-tab.svg" color="#eb660e">
    <link rel="shortcut icon" href="https://assets.noten-app.de/images/logo/favicon.ico">
</head>

<body>
    <div class="container">
        <div class="content">
            <div class="logo">
                <img src="https://assets.noten-app.de/images/logo/logo.webp" alt="Noten-App Logo">
            </div>
            <div class="gradient"></div>
            <div class="text">
                <h1>Start Transfer</h1>
                <p><span class="colorize">After you click</span> on the button, your account will be transferred to the Beta-Version of Noten-App.</p>
                <p>By doing this your <span class="colorize">current non-beta account</span> will be <span class="colorize">disabled</span> and can only be reenabled by support!</p>
                <p>Also, because this is a beta version we have another <a class="colorize" href="https://noten-app.de/legal/agb-beta">Privacy Policy</a>.</p>
                <button onclick="startTransfer()">Transfer</button>
            </div>
        </div>
    </div>
    <script src="https://assets.noten-app.de/js/jquery/jquery-3.6.1.min.js"></script>
    <script>
        const startTransfer = () => {
            $.ajax({
                url: "/onboard/onboard.php",
                type: "POST",
                data: {
                    token: "<?= $_GET["token"] ?>"
                },
                success: (data) => {
                    if (data == "success") {
                        location.assign("/onboard/success.html");
                    } else {
                        $(".text").html("<h1>Error</h1><p>" + data + "</p>");
                    }
                }
            });
        }
    </script>
</body>

</html>