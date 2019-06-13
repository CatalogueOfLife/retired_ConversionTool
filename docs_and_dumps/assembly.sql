# ************************************************************
# Sequel Pro SQL dump
# Version 5428
#
# https://www.sequelpro.com/
# https://github.com/sequelpro/sequelpro
#
# Host: localhost (MySQL 5.6.38)
# Database: assembly_deploy
# Generation Time: 2019-02-14 12:44:53 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
SET NAMES utf8mb4;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table common_names
# ------------------------------------------------------------

DROP TABLE IF EXISTS `common_names`;

CREATE TABLE `common_names` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name_code` varchar(42) COLLATE utf8_bin NOT NULL DEFAULT '',
  `common_name` varchar(700) COLLATE utf8_bin NOT NULL,
  `transliteration` varchar(700) COLLATE utf8_bin DEFAULT NULL,
  `language` varchar(25) COLLATE utf8_bin NOT NULL,
  `country` varchar(44) COLLATE utf8_bin NOT NULL DEFAULT '',
  `area` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  `reference_id` int(10) DEFAULT '0',
  `database_id` int(10) NOT NULL,
  `is_infraspecies` int(1) NOT NULL DEFAULT '0',
  `reference_code` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  KEY `common_name` (`common_name`(333)),
  KEY `reference_code` (`reference_code`),
  KEY `name_code` (`name_code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;



# Dump of table databases
# ------------------------------------------------------------

DROP TABLE IF EXISTS `databases`;

CREATE TABLE `databases` (
  `record_id` int(10) NOT NULL AUTO_INCREMENT,
  `database_name_displayed` varchar(125) COLLATE utf8_bin DEFAULT NULL,
  `database_name` varchar(150) COLLATE utf8_bin DEFAULT NULL,
  `database_full_name` varchar(100) COLLATE utf8_bin NOT NULL DEFAULT '',
  `web_site` longtext COLLATE utf8_bin,
  `organization` longtext COLLATE utf8_bin,
  `contact_person` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `taxa` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `taxonomic_coverage` longtext COLLATE utf8_bin,
  `abstract` longtext COLLATE utf8_bin,
  `version` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `release_date` date DEFAULT '1900-01-01',
  `SpeciesCount` int(11) DEFAULT '0',
  `SpeciesEst` int(11) DEFAULT '0',
  `authors_editors` longtext COLLATE utf8_bin,
  `accepted_species_names` int(10) DEFAULT '0',
  `accepted_infraspecies_names` int(10) DEFAULT '0',
  `species_synonyms` int(10) DEFAULT '0',
  `infraspecies_synonyms` int(10) DEFAULT '0',
  `common_names` int(10) DEFAULT '0',
  `total_names` int(10) DEFAULT '0',
  `is_new` tinyint(1) NOT NULL DEFAULT '0',
  `coverage` varchar(45) COLLATE utf8_bin DEFAULT NULL,
  `completeness` int(3) unsigned DEFAULT '0',
  `confidence` tinyint(1) unsigned DEFAULT '0',
  PRIMARY KEY (`record_id`),
  KEY `database_name` (`database_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;



# Dump of table distribution
# ------------------------------------------------------------

DROP TABLE IF EXISTS `distribution`;

CREATE TABLE `distribution` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name_code` varchar(42) COLLATE utf8_bin NOT NULL,
  `distribution` longtext COLLATE utf8_bin,
  `StandardInUse` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `DistributionStatus` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `database_id` int(10) NOT NULL,
  PRIMARY KEY (`record_id`),
  KEY `name_code` (`name_code`),
  KEY `database_id` (`database_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


DROP TABLE IF EXISTS `estimates`;

CREATE TABLE `estimates` (
  `name_code` varchar(42) NOT NULL DEFAULT '',
  `kingdom` varchar(15) NOT NULL,
  `name` varchar(255) NOT NULL,
  `rank` varchar(15) NOT NULL,
  `estimate` int(7) DEFAULT NULL,
  `source` text,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` timestamp NULL DEFAULT NULL,
  KEY `kingdom` (`kingdom`,`rank`,`name`),
  KEY `name_code` (`name_code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


# Dump of table families
# ------------------------------------------------------------

DROP TABLE IF EXISTS `families`;

CREATE TABLE `families` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hierarchy_code` varchar(250) COLLATE utf8_bin DEFAULT '',
  `kingdom` varchar(50) COLLATE utf8_bin DEFAULT 'Not Assigned',
  `phylum` varchar(50) COLLATE utf8_bin DEFAULT ' Not Assigned ',
  `class` varchar(50) COLLATE utf8_bin DEFAULT ' Not Assigned ',
  `order` varchar(50) COLLATE utf8_bin DEFAULT ' Not Assigned ',
  `family` varchar(50) COLLATE utf8_bin DEFAULT ' Not Assigned ',
  `superfamily` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  `database_id` int(10) NOT NULL,
  `family_code` varchar(50) COLLATE utf8_bin NOT NULL,
  `is_accepted_name` int(1) unsigned DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  KEY `family_code` (`family_code`),
  KEY `kingdom` (`kingdom`),
  KEY `phylum` (`phylum`),
  KEY `class` (`class`),
  KEY `order` (`order`),
  KEY `family` (`family`),
  KEY `superfamily` (`superfamily`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;



# Dump of table lifezone
# ------------------------------------------------------------

DROP TABLE IF EXISTS `lifezone`;

CREATE TABLE `lifezone` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `name_code` varchar(42) COLLATE utf8_bin DEFAULT NULL,
  `lifezone` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `database_id` int(10) DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  KEY `name_code` (`name_code`),
  KEY `database_id` (`database_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;



# Dump of table references
# ------------------------------------------------------------

DROP TABLE IF EXISTS `references`;

CREATE TABLE `references` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `author` varchar(200) COLLATE utf8_bin DEFAULT NULL,
  `year` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  `title` longtext COLLATE utf8_bin,
  `source` longtext COLLATE utf8_bin,
  `database_id` int(10) NOT NULL,
  `reference_code` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  KEY `reference_code` (`reference_code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;



# Dump of table scientific_name_references
# ------------------------------------------------------------

DROP TABLE IF EXISTS `scientific_name_references`;

CREATE TABLE `scientific_name_references` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name_code` varchar(42) COLLATE utf8_bin NOT NULL DEFAULT '',
  `reference_type` varchar(10) COLLATE utf8_bin DEFAULT NULL,
  `reference_id` int(10) DEFAULT '0',
  `reference_code` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  `database_id` int(10) NOT NULL,
  PRIMARY KEY (`record_id`),
  KEY `name_code` (`name_code`,`reference_id`,`reference_type`),
  KEY `reference_code` (`reference_code`),
  KEY `name_code_2` (`name_code`,`reference_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;



# Dump of table scientific_names
# ------------------------------------------------------------

DROP TABLE IF EXISTS `scientific_names`;

CREATE TABLE `scientific_names` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name_code` varchar(42) COLLATE utf8_bin NOT NULL,
  `web_site` longtext COLLATE utf8_bin,
  `genus` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  `subgenus` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  `species` varchar(60) COLLATE utf8_bin DEFAULT NULL,
  `infraspecies_parent_name_code` varchar(42) COLLATE utf8_bin DEFAULT NULL,
  `infraspecies` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  `infraspecies_marker` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  `author` varchar(100) COLLATE utf8_bin DEFAULT NULL,
  `accepted_name_code` varchar(42) COLLATE utf8_bin DEFAULT NULL,
  `comment` longtext COLLATE utf8_bin,
  `scrutiny_date` text COLLATE utf8_bin,
  `sp2000_status_id` int(1) DEFAULT NULL,
  `database_id` int(10) DEFAULT NULL,
  `specialist_id` int(10) DEFAULT NULL,
  `family_id` int(10) DEFAULT NULL,
  `specialist_code` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  `family_code` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  `is_accepted_name` int(1) DEFAULT NULL,
  `GSDTaxonGUID` longtext COLLATE utf8_bin,
  `GSDNameGUID` longtext COLLATE utf8_bin,
  `is_extinct` smallint(1) DEFAULT '0',
  `has_preholocene` smallint(1) DEFAULT '0',
  `has_modern` smallint(1) DEFAULT '1',
  PRIMARY KEY (`record_id`,`name_code`),
  KEY `database_id` (`database_id`),
  KEY `family_id` (`family_id`),
  KEY `species` (`species`),
  KEY `infraspecies` (`infraspecies`),
  KEY `specialist_code` (`specialist_code`),
  KEY `accepted_name_code` (`accepted_name_code`),
  KEY `infraspecies_parent_name_code` (`infraspecies_parent_name_code`),
  KEY `name_code1` (`name_code`,`family_id`),
  KEY `record_id` (`record_id`,`family_id`),
  KEY `genus` (`genus`,`species`,`infraspecies`),
  KEY `sp2000_status_id` (`sp2000_status_id`),
  KEY `sp2000_status_id_2` (`sp2000_status_id`,`database_id`,`infraspecies`),
  KEY `family_code` (`family_code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;



# Dump of table sp2000_statuses
# ------------------------------------------------------------

DROP TABLE IF EXISTS `sp2000_statuses`;

CREATE TABLE `sp2000_statuses` (
  `record_id` int(1) NOT NULL AUTO_INCREMENT,
  `sp2000_status` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`record_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;



# Dump of table specialists
# ------------------------------------------------------------

DROP TABLE IF EXISTS `specialists`;

CREATE TABLE `specialists` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `specialist_name` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
  `specialist_code` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  `database_id` int(10) NOT NULL,
  PRIMARY KEY (`record_id`),
  KEY `specialist_code` (`specialist_code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
