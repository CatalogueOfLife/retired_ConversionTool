<?php require_once 'library/BsOptimizerLibrary.php'; ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title>Conversion Tool logs</title>
</head>
<body style="font: 11px verdana; width: 600px;">
<h3>Conversion Tool logs</h3>
<p style="font-size: 10px; margin-bottom: 20px;">
<p>Click one of the logs files to check conversion errors.</p>
<?php
    $logs = getLogs();
    foreach ($logs as $log) {
        echo "$log<br>";
    }
?></p>

</body>
</html>