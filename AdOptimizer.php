<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <title>Assembly Database Optimizer</title>
  <style>
     body {font: 11px verdana; width: 700px;}
     p.taxon-matcher-msg-8 {margin:0;}
  </style>
</head>

<body>
<h3>Assembly Database Optimizer</h3><?php
    require_once 'library/AdOptimizerLibrary.php';
    require_once 'DbHandler.php';
    require_once 'Indicator.php';
    require 'taxonmatcher/TaxonMatcher.php';
    require 'taxonmatcher/EchoEventListener.php';
    alwaysFlush();
    $indicator = new Indicator();
    ini_set('memory_limit', '1024M');

    if (isset($argv) && isset($argv[1])) {
      $config = parse_ini_file($argv[1], true);
    } else {
      $config = parse_ini_file('config/AcToBs.ini', true);
    }

    $scriptStart = microtime(true);

    echo '<p>Checking database structure...</p>';
    $errors = checkDatabase();
    if (!empty($errors)) {
      printErrors($errors);
    }
    echo '<p><b>Copy foreign key codes to foreign key IDs</b><br>';
    $errors = copyCodesToIds();
    if (!empty($errors)) {
      echo '';
      printErrors($errors, 'Error: ' . count($errors) . ' missing foreign key codes');
    }
    echo '</p><p><b>Checking foreign key references</b><br>';
    $errors = checkForeignKeys();
    if (!empty($errors)) {
      printErrors($errors, 'Missing foreign key references');
    }

    echo "</p><p><b>Building 'taxa' table</b><br>";
    $errors = buildTaxaTable();
    if (!empty($errors)) {
      echo '</p><p style="color: red;"><b>Errors during creation of \'taxa\' table</b></p>';
      foreach ($errors as $category => $categoryErrors) {
          if (!empty($categoryErrors)) {
              printErrors($categoryErrors, $category);
          }
      }
    }
    $totalTime = round(microtime(true) - $scriptStart);
    echo '</p><p>Optimalization took ' . $indicator->formatTime($totalTime) . '.</p>';

    echo '<p><br><br><b>Creating LSIDs</b><br>';
    $taxonMatcher = new TaxonMatcher();
    $taxonMatcher->setDbHost($config['source']['host']);
    $taxonMatcher->setDbUser($config['source']['username']);
    $taxonMatcher->setDbPassword($config['source']['password']);
    $taxonMatcher->setDbNameCurrent($config['taxonmatcher']['dbNameCurrent']);
    $taxonMatcher->setDbNameNext($config['source']['dbname']);
    $taxonMatcher->setDbNameStage($config['taxonmatcher']['dbNameStage']);
    $taxonMatcher->setLSIDSuffix($config['taxonmatcher']['lsidSuffix']);
    $taxonMatcher->setResetLSIDs(true);

    $listener = new EchoEventListener();
    $listener->setContentTypeHTML();
    $listener->showStackTrace();
    $taxonMatcher->addEventListener($listener);
    try {
        $taxonMatcher->run();
    }
    catch(TaxonMatcherException $e) {
        // No need to print it out, because that will already
        // have been done by the EchoEventListener.
        die();
    }
    catch(Exception $e) {
        echo "\n" . $e->getTraceAsString();
    }
?>
    </p><p><br><br>Post-processing ready! Proceed to <b>Step 2</b>:
    <a href="AcToBs.php">Import the data into the new database</a>.</p>
</body>
</html>
