<?php require_once 'library/BsOptimizerLibrary.php'; ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title>Conversion Tool logs</title>
</head>
<body style="font: 12px verdana; width: 600px;">
<h3>Conversion Tool logs</h3>
<p style="font-size: 12px; margin-bottom: 20px;">
<p>This page accesses the logs created during conversion. Click one of the log files
to check conversion errors. You can download the log by using the save option in your browser.
Empty logs are not displayed.</p>
<?php
    $logs = getLogs();
    foreach ($logs as $log) {
        echo "$log<br>";
    }
?></p>

</body>
</html>