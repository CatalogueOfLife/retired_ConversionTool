<?php
function getLogs ()
{
    $files = array();
    $dir = dirname(__FILE__) . '/logs';
    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? $http = 'https://' : $http = 'http://';
    $d = dir($dir);
    while (false !== ($file = $d->read())) {
        if (is_numeric(substr($file, 0, 4)) && !is_dir($file)) {
            list($year, $month, $day) = explode('-', $file);
            $files[$year.$month.$day] = "<a href='logs/$file'>$file</a> (" .
                getDownloadSize("logs/$file") . ')';
        }
    }
    $d->close();
    krsort($files);
    return $files;
}

function getDownloadSize ($path)
{
    $sizeKb = filesize($path) / 1024;
    $size = round($sizeKb, 1) . ' KB';
    if ($sizeKb > 999) {
        $size = round($sizeKb / 1024, 1) . ' MB';
    }
    return $size;
}
?>
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