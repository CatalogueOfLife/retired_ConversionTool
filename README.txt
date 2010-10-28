4D4Life Conversion Tool
by ETI BioInformatics

RELEASE INFORMATION
====================
4D4Lie Conversion Tool v@APP.VERSION@ r@APP.REVISION@
Released on @TIMESTAMP@

ABOUT THIS SOFTWARE
====================
This web application converts the database from the Annual Checklist
database to a database based on the 4D4Life Base Scheme. Additionally
an unsupported conversion from Dynamic Checklist to the Annual Checklist 
is provided.

FEATURES
=========
Functional:    
  * Conversion from Annual Checklist to Base Scheme database
  * Creation of denormalized tables for optimal performance

LIMITATIONS
============
None

INSTALLATION
=============
Please see the INSTALL.txt file for installation instructions.

CONVERTING DATA
===============
1. Create a new MySQL database

2. Import the table structure from: 
   docs_and_dumps/dumps/base_scheme/baseschema-schema.sql
  
3. Import fixed data (countries, languages, etc) from:
   docs_and_dumps/dumps/base_scheme/baseschema-data.sql
  
4. Configure the database settings in:
   config/AcToBs.ini
  
5. Start the conversion by pointing your browser to:
   http://your_server/path_to_converter/index.php
   The conversion will take a long time to finish. Depending on your server,
   this may take more than 24 hrs.
  
6. Create denormalized tables used for searching and display of species details:
   http://your_server/path_to_converter/BsOptimizer.php
   If MySQL is properly tuned for large innodb databases, this script should take
   less than half an hour to complete.

7. Install and configure the Annual Checklist v1.6 to use the database 
   you created.

LICENSING
==========
This software makes use of third-party open source libraries. You can find a 
copy of their license in:
  * application/library/Zend/LICENSE.txt, Zend Framework