-- phpMyAdmin SQL Dump
-- version 3.2.5
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jul 02, 2010 at 01:09 PM
-- Server version: 5.1.44
-- PHP Version: 5.3.2

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `dynamic_checklist_v2`
--

-- --------------------------------------------------------

--
-- Table structure for table `common_names`
--

CREATE TABLE `common_names` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name_code` varchar(50) NOT NULL DEFAULT '',
  `common_name` varchar(700) NOT NULL,
  `language` varchar(25) NOT NULL,
  `country` varchar(44) NOT NULL DEFAULT '',
  `reference_id` int(10) DEFAULT '0',
  `database_id` int(10) NOT NULL DEFAULT '0',
  `is_infraspecies` int(1) NOT NULL DEFAULT '0',
  `reference_code` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  KEY `common_name` (`common_name`(255)),
  KEY `language` (`language`,`common_name`(255),`name_code`),
  KEY `common_name_2` (`common_name`(255),`name_code`,`database_id`),
  KEY `name_code` (`name_code`),
  KEY `common_name_3` (`common_name`(255),`database_id`),
  KEY `reference_code` (`reference_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=20578 ;

--
-- Dumping data for table `common_names`
--


-- --------------------------------------------------------

--
-- Table structure for table `databases`
--

CREATE TABLE `databases` (
  `database_name_displayed` varchar(100) DEFAULT NULL,
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `database_name` varchar(150) DEFAULT NULL,
  `database_full_name` varchar(100) NOT NULL DEFAULT '',
  `web_site` longtext,
  `organization` longtext,
  `contact_person` varchar(255) DEFAULT NULL,
  `taxa` varchar(255) DEFAULT NULL,
  `taxonomic_coverage` longtext,
  `abstract` longtext,
  `version` varchar(255) DEFAULT NULL,
  `release_date` date DEFAULT '0000-00-00',
  `SpeciesCount` int(11) DEFAULT '0',
  `SpeciesEst` int(11) DEFAULT '0',
  `authors_editors` longtext,
  `accepted_species_names` int(10) DEFAULT '0',
  `accepted_infraspecies_names` int(10) DEFAULT '0',
  `species_synonyms` int(10) DEFAULT '0',
  `infraspecies_synonyms` int(10) DEFAULT '0',
  `common_names` int(10) DEFAULT '0',
  `total_names` int(10) DEFAULT '0',
  PRIMARY KEY (`record_id`),
  KEY `database_name` (`database_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=44 ;

--
-- Dumping data for table `databases`
--


-- --------------------------------------------------------

--
-- Table structure for table `distribution`
--

CREATE TABLE `distribution` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name_code` varchar(50) NOT NULL DEFAULT '',
  `distribution` longtext,
  PRIMARY KEY (`record_id`),
  KEY `name_code` (`name_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=232664 ;

--
-- Dumping data for table `distribution`
--


-- --------------------------------------------------------

--
-- Table structure for table `families`
--

CREATE TABLE `families` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hierarchy_code` varchar(250) NOT NULL,
  `kingdom` varchar(50) NOT NULL DEFAULT '',
  `phylum` varchar(50) NOT NULL DEFAULT '',
  `class` varchar(50) NOT NULL DEFAULT '',
  `order` varchar(50) NOT NULL DEFAULT '',
  `family` varchar(50) NOT NULL DEFAULT '',
  `superfamily` varchar(50) DEFAULT NULL,
  `family_common_name` varchar(255) DEFAULT NULL,
  `database_name` varchar(50) NOT NULL DEFAULT '',
  `is_accepted_name` int(1) DEFAULT '0',
  `database_id` int(10) DEFAULT NULL,
  `family_code` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  UNIQUE KEY `unique_classification` (`kingdom`,`phylum`,`class`,`order`,`family`),
  KEY `kingdom` (`kingdom`),
  KEY `phylum` (`phylum`),
  KEY `class` (`class`),
  KEY `order` (`order`),
  KEY `family` (`family`),
  KEY `superfamily` (`superfamily`),
  KEY `is_accepted_name` (`is_accepted_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=6319 ;

--
-- Dumping data for table `families`
--


-- --------------------------------------------------------

--
-- Table structure for table `references`
--

CREATE TABLE `references` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `author` varchar(200) DEFAULT NULL,
  `year` varchar(50) DEFAULT NULL,
  `title` longtext,
  `source` longtext,
  `database_id` int(10) DEFAULT NULL,
  `reference_code` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  KEY `reference_code` (`reference_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=59138 ;

--
-- Dumping data for table `references`
--


-- --------------------------------------------------------

--
-- Table structure for table `scientific_names`
--

CREATE TABLE `scientific_names` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name_code` varchar(50) NOT NULL DEFAULT '',
  `web_site` longtext,
  `genus` varchar(50) NOT NULL DEFAULT '',
  `species` varchar(50) DEFAULT '',
  `infraspecies` varchar(50) DEFAULT '',
  `infraspecies_marker` varchar(50) DEFAULT NULL,
  `author` varchar(100) DEFAULT NULL,
  `accepted_name_code` varchar(50) DEFAULT NULL,
  `comment` longtext,
  `scrutiny_date` text,
  `sp2000_status_id` int(1) DEFAULT NULL,
  `database_id` int(10) NOT NULL DEFAULT '0',
  `specialist_id` int(10) DEFAULT NULL,
  `family_id` int(10) DEFAULT NULL,
  `specialist_code` varchar(50) DEFAULT NULL,
  `family_code` varchar(50) DEFAULT NULL,
  `is_accepted_name` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`record_id`),
  KEY `family_id` (`family_id`),
  KEY `species` (`species`),
  KEY `infraspecies` (`infraspecies`),
  KEY `accepted_name_code` (`accepted_name_code`),
  KEY `name_code` (`name_code`,`family_id`),
  KEY `record_id` (`record_id`,`family_id`),
  KEY `genus` (`genus`,`species`,`infraspecies`),
  KEY `sp2000_status_id` (`sp2000_status_id`),
  KEY `sp2000_status_id_2` (`sp2000_status_id`,`database_id`,`infraspecies`),
  KEY `family_code` (`family_code`),
  KEY `specialist_code` (`specialist_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1023461 ;

--
-- Dumping data for table `scientific_names`
--


-- --------------------------------------------------------

--
-- Table structure for table `scientific_name_references`
--

CREATE TABLE `scientific_name_references` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name_code` varchar(50) NOT NULL DEFAULT '',
  `reference_type` varchar(10) DEFAULT NULL,
  `reference_id` int(10) DEFAULT '0',
  `reference_code` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  KEY `name_code` (`name_code`,`reference_id`,`reference_type`),
  KEY `name_code_2` (`name_code`,`reference_type`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=471153 ;

--
-- Dumping data for table `scientific_name_references`
--


-- --------------------------------------------------------

--
-- Table structure for table `sp2000_statuses`
--

CREATE TABLE `sp2000_statuses` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sp2000_status` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`record_id`),
  KEY `sp2000_status` (`sp2000_status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=6 ;

--
-- Dumping data for table `sp2000_statuses`
--

INSERT INTO `sp2000_statuses` VALUES(1, 'accepted name');
INSERT INTO `sp2000_statuses` VALUES(2, 'ambiguous synonym');
INSERT INTO `sp2000_statuses` VALUES(3, 'misapplied name');
INSERT INTO `sp2000_statuses` VALUES(4, 'provisionally accepted name');
INSERT INTO `sp2000_statuses` VALUES(5, 'synonym');

-- --------------------------------------------------------

--
-- Table structure for table `specialists`
--

CREATE TABLE `specialists` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `specialist_name` varchar(255) NOT NULL DEFAULT '',
  `specialist_code` varchar(50) DEFAULT NULL,
  `database_id` decimal(11,0) DEFAULT NULL,
  PRIMARY KEY (`record_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=196 ;

--
-- Dumping data for table `specialists`
--

