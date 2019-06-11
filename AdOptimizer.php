<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <title>Assembly Database Optimizer</title>
  <style>
     body {font: 12px verdana; width: 800px;}
     p.taxon-matcher-msg-8 {margin:0;}
  </style>
</head>

<body>
<h3>Assembly Database Optimizer</h3>
<?php
    set_include_path('library' . PATH_SEPARATOR . get_include_path());
    ini_set('memory_limit', '1024M');
    set_time_limit(86400);
    alwaysFlush();
    
    require_once 'library/AdOptimizerClass.php';
    require_once 'DbHandler.php';
    
    $converter = new AdOptimizer('config/AcToBs.ini');
    $converter->setPdo();
    $converter->setLogger('logs/' . date("Y-m-d") . '-assembly-optimizer.log');
    
    $scriptStart = microtime(true);
    
    echo '<p>Started: ' . date('Y-m-d H:i:s') . '</p>';
    
    echo '<p>Importing csv files...<br>';
    $converter->importCsv()->printMessages();
    echo 'Checking database structure...<br>';
    $converter->checkDatabase()->printMessages('Database tables and columns');
    $converter->checkIndices()->printMessages('Database indices');
    echo "Copying family codes from accepted names to synonyms...<br>" ;
    $converter->familyCodeToSynonyms()->printMessages();
    echo 'Copying foreign key codes to foreign key ids...<br>';
    $converter->codesToIds()->printMessages();
    echo 'Checking foreign key references...<br>';
    $converter->checkForeignKeys();
    echo "</p><p>Building 'taxa' table...";
    $converter->buildTaxaTable();
    echo "<p>Errors in taxa table:<br>";
    $converter->printMessages();
        
    $totalTime = round(microtime(true) - $scriptStart);
    echo '</p><p>Optimalization took ' . $converter->formatTime($totalTime) . '.</p>';
?>
    <p><br><br>Post-processing ready! Proceed to <b>Step 2</b>:
    <a href="AcToBs.php">Import the data into the new database</a>.</p>
</body>
</html>
<?php 
    function alwaysFlush ()
    {
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
        for ($i = 0; $i < ob_get_level(); $i++) {
            ob_end_flush();
        }
        ob_implicit_flush(1);
        set_time_limit(0);
    }
?>