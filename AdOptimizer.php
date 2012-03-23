<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <title>Assembly Database Optimizer</title>
</head>

<body style="font: 11px verdana; width: 700px;">
<h3>Assembly Database Optimizer</h3><?php
    require_once 'library/AdOptimizerLibrary.php';
    require_once 'DbHandler.php';
    require_once 'Indicator.php';
    require 'taxonmatcher/TaxonMatcher.php';
    require 'taxonmatcher/TaxonMatcherEventListener.php';
    require 'taxonmatcher/AbstractTaxonMatcherEventListener.php';
    require 'taxonmatcher/EchoEventListener.php';
    require 'taxonmatcher/TaxonMatcherException.php';
    require 'taxonmatcher/InvalidInputException.php';
    alwaysFlush();
    $indicator = new Indicator();
    
    if (isset($argv) && isset($argv[1])) {
      $config = parse_ini_file($argv[1], true);
    } else {
      $config = parse_ini_file('config/AcToBs.ini', true);
    }
    
    
    $taxonMatcher = new TaxonMatcher();
    $taxonMatcher->setDbHost($config['source']['host']);
    $taxonMatcher->setDbUser($config['source']['username']);
    $taxonMatcher->setDbPassword($config['source']['password']);
    $taxonMatcher->setDbNameCurrent($config['taxonmatcher']['dbNameCurrent']);
    $taxonMatcher->setDbNameNext($config['source']['dbname']);
    $taxonMatcher->setDbNameStage($config['taxonmatcher']['dbNameStage']);
    $taxonMatcher->setLSIDSuffix($config['taxonmatcher']['lsidSuffix']);
    $taxonMatcher->setReadLimit($config['taxonmatcher']['readLimit']);
    
    // Let's be interested in what the TaxonMatcher does.
    function isTrue($val) { return in_array(strtolower($val), array('1','true','on','yes')); }
    $listener = new EchoEventListener();
    $listener->setContentTypeHTML();
    // Configure the listener.
    if(isTrue($config['taxonmatcher']['debug'])) {
        $listener->enableMessages(TaxonMatcherEventListener::MSG_DEBUG);
    }
    if(isTrue($config['taxonmatcher']['showStackTrace'])) {
        $listener->showStackTrace();
    }
    $taxonMatcher->addEventListener($listener);
    // Go ...
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
    
    die();
/*    
    // Compare Jorrit's taxa to Ruud's taxa
    $link = mysqlConnect();
    echo "Fetching Ruud's data...<br>";
    mysql_select_db('assembly');
    $q = 'select name, name_code from taxa';
    $r = mysql_query($q) or die(mysql_error);
    while ($row = mysql_fetch_array($r, MYSQL_NUM)) {
        $jorrit[$row[1]] = $row[0];
    }
    echo "Fetching Jorrit's data...<br>";
    mysql_select_db('assembly_jorrit');
    $q = 'select name, name_code from taxa';
    $r = mysql_query($q) or die(mysql_error);
    $indicator->init(mysql_num_rows($r), 100, 10000);
    while ($row = mysql_fetch_array($r, MYSQL_NUM)) {
        $indicator->iterate();
        if (isset($jorrit[$row[1]]) && $jorrit[$row[1]] == $row[0]) {
            unset($jorrit[$row[1]]);
        }
    }
    echo '<pre>'; print_r($jorrit); echo '</pre>';
    die();
*/    
    
    $scriptStart = microtime(true);
    echo '<p>Checking database structure...</p>';
    $errors = checkDatabase();
    if (!empty($errors)) {
      printErrors($errors);
    }
    echo '<p><b>Copy foreign key codes to foreign key IDs</b><br>';
    $errors = copyCodesToIds();
    if (!empty($errors)) {
      printErrors($errors);
    }
    echo '</p><p><b>Checking foreign key references</b><br>';
    $errors = checkForeignKeys();
    if (!empty($errors)) {
      printErrors($errors);
    } 
    echo "</p><p><b>Building 'taxa' table</b><br>";
    $errors = buildTaxaTable();
    if (!empty($errors)) {
      echo '</p><p>';
      foreach ($errors as $category => $categoryErrors) {
          if (!empty($categoryErrors)) {
              echo "<b>$category:</b><br>";
              printErrors($categoryErrors);
          }
      }
    }
    $totalTime = round(microtime(true) - $scriptStart);
    echo '</p><p>Ready! Optimalization took ' . $indicator->formatTime($totalTime) . '.</p>';

  ?>
</body>
</html>
