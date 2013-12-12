<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <title>Base Scheme Optimizer</title>
</head>

<body style="font: 12px verdana; width: 700px;">
  <h3>Base Scheme Optimizer</h3><?php
  require_once 'library/BsOptimizerLibrary.php';
  require_once 'DbHandler.php';
  require_once 'Indicator.php';
  alwaysFlush();

  // Path to sql files
  define('PATH', realpath('.') . '/docs_and_dumps/dumps/base_scheme/ac/');
  define('DENORMALIZED_TABLES_PATH', 'denormalized_tables/');

  // SQL for denormalized tables, .sql omitted!
  define('SCHEMA_SQL', 'denormalized_schema');

  // Names of SQL queries in files, .sql omitted!
  define('SEARCH_ALL', '_search_all');
  define('SEARCH_ALL_COMMON_NAMES', '_search_all_common_names');
  define('TAXON_TREE_SPECIES_TOTALS', '_taxon_tree_species_totals');
  define('SEARCH_DISTRIBUTION', '_search_distribution');
  define('SEARCH_SCIENTIFIC', '_search_scientific');
  define('SEARCH_FAMILY', '_search_family');
  define('SOURCE_DATABASE_DETAILS', '_source_database_details');
  define('SOURCE_DATABASE_TO_TAXON_TREE_BRANCH', '_source_database_to_taxon_tree_branch');
  define('SPECIES_DETAILS', '_species_details');
  define('TAXON_TREE', '_taxon_tree');
  define('TOTALS', '_totals');
  $files = array(
      array(
          'path' => PATH, 
          'dumpFile' => SCHEMA_SQL, 
          'message' => 'Creating denormalized tables'
      ), 
      array(
          'path' => PATH . DENORMALIZED_TABLES_PATH, 
          'dumpFile' => SEARCH_ALL, 
          'message' => 'Filling ' . SEARCH_ALL . ' table'
      ), 
      array(
          'path' => PATH . DENORMALIZED_TABLES_PATH, 
          'dumpFile' => SEARCH_DISTRIBUTION, 
          'message' => 'Filling ' . SEARCH_DISTRIBUTION . ' table'
      ), 
      array(
          'path' => PATH . DENORMALIZED_TABLES_PATH, 
          'dumpFile' => SEARCH_SCIENTIFIC, 
          'message' => 'Filling ' . SEARCH_SCIENTIFIC . ' table'
      ), 
      array(
          'path' => PATH . DENORMALIZED_TABLES_PATH, 
          'dumpFile' => SEARCH_FAMILY, 
          'message' => 'Filling ' . SEARCH_FAMILY . ' table'
      ), 
      array(
          'path' => PATH . DENORMALIZED_TABLES_PATH, 
          'dumpFile' => SOURCE_DATABASE_DETAILS, 
          'message' => 'Filling ' . SOURCE_DATABASE_DETAILS . ' table'
      ), 
      array(
          'path' => PATH . DENORMALIZED_TABLES_PATH, 
          'dumpFile' => SPECIES_DETAILS, 
          'message' => 'Filling ' . SPECIES_DETAILS . ' table'
      ), 
      array(
          'path' => PATH . DENORMALIZED_TABLES_PATH, 
          'dumpFile' => TAXON_TREE, 
          'message' => 'Filling ' . TAXON_TREE . ' table'
      ), 
      array(
          'path' => PATH . DENORMALIZED_TABLES_PATH, 
          'dumpFile' => TOTALS, 
          'message' => 'Filling ' . TOTALS . ' table'
      )
  );

  // Denormalized tables and their indices
  // Indices on multiple columns should be written as 'column1, column2, etc'
  // Add [size] in case a partial index is needed, e.g. 'column1[10], column2[10], etc'
  // Use [0] for int columns in partial indices, so these are properly parsed
  $tables = array(
      SEARCH_ALL => array(
          'name_element', 
          'name', 
          'rank', 
          'name_status', 
          'accepted_taxon_id'
      ), 
      SEARCH_DISTRIBUTION => array(), 
      SEARCH_SCIENTIFIC => array(
          'kingdom', 
          'phylum', 
          'class', 
          'order', 
          'superfamily', 
          'family', 
      	  'subgenus',
          'species', 
          'infraspecies', 
          'genus, species, infraspecies', 
      	  'status',
      	  'accepted_species_id',
      	  'accepted_species_id[0], genus[15], subgenus[10], species[10], infraspecies[10]',
          'accepted_species_id[0], genus[10]',
      	  'accepted_species_id[0], infraspecies[10]',
      	  'accepted_species_id[0], species[10]',
          'accepted_species_id[0], subgenus[10]'
  ), 
      SOURCE_DATABASE_DETAILS => array(
          'id'
      ), 
      SPECIES_DETAILS => array(
          'taxon_id', 
          'kingdom_id', 
          'phylum_id', 
          'class_id', 
          'order_id', 
          'superfamily_id', 
          'family_id', 
          'genus_id', 
          'subgenus_id', 
      	  'source_database_id'
      ), 
      TAXON_TREE => array(
          'taxon_id', 
          'name', 
          'rank'
      ), 
  );

  // Name elements in _search_all table to be discarded
  $delete_name_elements = array(
      'sp.', 
      'subsp.', 
      'sp', 
      'subsp.', 
      'spec', 
      'singular', 
      'plural'
  );

  // Characters in name elements in _search_all table to be discarded
  $delete_chars = array(
      '(', 
      ')', 
      '=', 
      '?', 
      '+', 
      '.', 
      ',', 
      ';', 
      PHP_EOL
  );

  if (isset($argv) && isset($argv[1])) {
      $config = parse_ini_file($argv[1], true);
  }
  else {
      $config = parse_ini_file('config/AcToBs.ini', true);
  }

  foreach ($config as $k => $v) {
      $o = array();
      if (isset($v["options"])) {
          $options = explode(",", $v["options"]);
          foreach ($options as $option) {
              $parts = explode("=", trim($option));
              $o[$parts[0]] = $parts[1];
          }
          DbHandler::createInstance($k, $v, $o);
      }
  }
  $pdo = DbHandler::getInstance('target');
  $indicator = new Indicator();

  $scriptStart = microtime(true);

/*  
 * 
 * Test set
 * 
 * All species in genus Larus are pre-Holocene, except Larus fuscus which is still extant
 * 
 * update `taxon_detail` set `has_preholocene` = 1, `has_modern` = 0, `is_extinct` = 1 where `taxon_id` in (SELECT id FROM `_search_scientific` where `genus` = "larus" and `species` != "fuscus")
 * 
 * 
 * 
 * 
 * Structure update
 * 
 * 
 * ALTER TABLE `taxon_detail` ADD `has_preholocene` SMALLINT( 1 ) NOT NULL DEFAULT '0',
    ADD `has_modern` SMALLINT( 1 ) NOT NULL DEFAULT '1',
    ADD `is_extinct` SMALLINT( 1 ) NOT NULL DEFAULT '0';
    ALTER TABLE `taxon_detail` ADD INDEX ( `has_preholocene` ) ;
    ALTER TABLE `taxon_detail` ADD INDEX ( `has_modern` ) ;
    ALTER TABLE `taxon_detail` ADD INDEX ( `is_extinct` ) ;
 * 
 * ALTER TABLE `_search_all` ADD `has_preholocene` SMALLINT( 1 ) NOT NULL DEFAULT '0',
   ADD `has_modern` SMALLINT( 1 ) NOT NULL DEFAULT '1';
   ALTER TABLE `_search_all` ADD INDEX ( `has_preholocene` ) ;
   ALTER TABLE `_search_all` ADD INDEX ( `has_modern` ) ;

 *  ALTER TABLE `_taxon_tree` ADD `is_extinct` SMALLINT( 1 ) NOT NULL DEFAULT '0';

 */
  
  $queries = array(
    'UPDATE `' . SEARCH_ALL . '` AS t1 
     LEFT JOIN `taxon_detail` AS t2 ON t1.`id` = t2.`taxon_id`
     SET t1.`has_preholocene` = t2.`has_preholocene`, 
         t1.`has_modern` = t2.`has_modern`
     WHERE t2.`has_preholocene` IS NOT NULL',
    'UPDATE `' . SPECIES_DETAILS . '` AS t1 
     LEFT JOIN `taxon_detail` AS t2 ON t1.`taxon_id` = t2.`taxon_id`
     SET t1.`is_extinct` = t2.`is_extinct`
     WHERE t2.`is_extinct` = 1',
    'UPDATE `' . TAXON_TREE . '` AS t1 
     LEFT JOIN `taxon_detail` AS t2 ON t1.`id` = t2.`taxon_id`
     SET t1.`is_extinct` = t2.`is_extinct`
     WHERE t2.`is_extinct` = 1'
  );
  foreach ($queries as $q) {
      $pdo->query($q);
  }

  $totalTime = round(microtime(true) - $scriptStart);
  echo '</p><p>Ready! Optimalization took ' . $indicator->formatTime($totalTime) . '.</p>';

?>
</body>
</html>
