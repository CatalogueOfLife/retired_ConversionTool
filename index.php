<?php 
    $config = parse_ini_file('config/AcToBs.ini', true);
    $table = '<table style="margin: 15px 0;"><tr>';
    foreach ($config as $k => $v) {
        if (!in_array($k, array('source', 'target'))) {
            continue;
        }
        $table .= "<td style='width: 250px; font-size: 12px;'><b>$k</b><br>";
        foreach ($v as $k => $v) {
            if (in_array($k, array('dbname','host'))) {
                $table .=  "$k = $v<br>";
            }
        }
        $table .=  '</td>';
    }
    $table .= '</tr></table>';
    $version = 'v'.$config['settings']['version'];
    if ($config['settings']['revision'] != '') {
        $version .= ' rev '.$config['settings']['revision'];
    }
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <title>4D4Life Conversion Tool</title>
</head>
<body style="font: 12px verdana; width: 600px;">
<h3>4D4Life Conversion Tool</h3>
<p style="font-size: 10px; margin-bottom: 20px;"><?php echo $version; ?></p>
<p>Welcome to the 4D4Life Conversion Tool. This tool is used to transfer
the Annual Checklist to a database based on the base scheme. 
The conversion is a two-step process: first all data is
copied from the old database to the base scheme, next a set of tables
is created that speed up searching and browsing in the interface.</p>
<p>Due to the nature of the base scheme database, the conversion is a
lengthy process that may take over 24 hours. Please refer to INSTALL.TXT and
README.TXT for hints to improve performance.</p>
<p>Currently the conversion is set as follows. Changes can be applied to  
the <i>AcToBs.ini</i> file in the config directory. User name and password are not
shown for security reasons.</p>
<?php echo $table; ?>
<p style="margin-bottom: 30px;">
Click the link to start the conversion: 
<a href ="AcToBs.php">convert Annual Checklist to Base Scheme</a></p>
<p>An experimental and unsupported conversion of the latest version of the 
SpiceCache database to the previous Annual Checklist database format is also 
available: 
<a href ="ScToDc.php">convert SpiceCache to Dynamic Checklist</a></p>
</body>
</html>