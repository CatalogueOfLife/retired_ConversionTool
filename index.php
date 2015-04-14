<?php
require_once 'library/bootstrap.php';
$config = parse_ini_file('config/AcToBs.ini', true);
$table = '<table style="margin: 25px 0;"><tr>';
foreach ($config as $k => $v) {
    if (!in_array($k, array(
        'source',
        'target',
        'estimates'
    ))) {
        continue;
    }
    $table .= "<td style='font-size: 11px; padding-right: 25px;'><b>$k</b><br>";
    foreach ($v as $k => $v) {
        if (in_array($k, array(
            'dbname',
            'host'
        ))) {
            $table .= "$k = $v<br>";
        }
    }
    $table .= '</td>';
}
$table .= '</tr></table>';
$version = 'v' . $config['settings']['version'];
if ($config['settings']['revision'] != '') {
    $version .= ' rev ' . $config['settings']['revision'];
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
<p style="font-size: 10px; margin-bottom: 20px;">
<?php echo $version; ?></p>
<p>Welcome to the 4D4Life Conversion Tool. This tool is used to transfer
the Assembly Database to a database based on the Base Scheme. It's recommended
to start with a fresh Base Scheme database rather than re-using an older version,
as  additional tables or columns may be required that were not yet present in
previous editions. A new database can be created by importing the
'baseschema-schema.sql' and 'baseschema-data.sql' SQL
dump files at docs_and_dumps/dumps/base-scheme.</p>
<p>The conversion is a four-step process:
<ol>
<li>the Assembly Database is post-processed: foreign keys are converted,
a denormalized table is created and LSIDs are generated</li>
<li>the data is imported into the new database</li>
<li>several denormalized tables are created for the new database that
speed up searching and browsing in the Annual Checklist interface</li>
<li>the sitemap files for Google are updated</li>
</ol></p>
<p>Due to the nature of the base scheme database, the conversion is a
lengthy process that will take several hours. Please refer to INSTALL.TXT
and README.TXT for hints to improve performance.</p>
<p>Currently the conversion is set as follows. Changes can be applied to
the <i>AcToBs.ini</i> file in the config directory.</p>
<?php
echo $table;
?>
<p>Proceed to <b>Step 1</b>: <a href="AdOptimizer.php">Post-process the Assembly
Database</a>. Alternatively, you can:</p>
<ul>
<li><a href="AcToBs.php">proceed to the import script</a> (<b>step 2</b>)</li>
<li><a href="sitemaps.php">optimize the database</a> (<b>step 3</b>)</li>
<li><a href="sitemaps.php">create the sitemap files</a> (<b>step 4</b>)</li>
<li><a href="logs.php">check the conversion logs</a></li>
<li><a href="taylor.php">create a csv mapping file for Taylor &amp; Francis</a></li>
</ul>

</body>
</html>