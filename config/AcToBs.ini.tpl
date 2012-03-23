[source]
dbname = @SOURCE.DBNAME@
host = @SOURCE.DBHOST@
username = @SOURCE.DBUSER@
password = @SOURCE.DBPASS@
port =  @SOURCE.DBPORT@; Can be empty
driver = mysql
; separate options by comma
options = "PDO::MYSQL_ATTR_INIT_COMMAND=set names utf8"

[target]
dbname = @TARGET.DBNAME@
host = @TARGET.DBHOST@
username = @TARGET.DBUSER@
password = @TARGET.DBPASS@
port =  @TARGET.DBPORT@; Can be empty
driver = mysql
; separate options by comma
options = "PDO::MYSQL_ATTR_INIT_COMMAND=set names utf8"

[checks]
fk_constraints = 1
taxon_ids = 1
synonym_ids = 1
infraspecies_parent_ids = 1

[settings]
version = @VERSION@
revision = @REVISION@

[taxonmatcher]
; database name of the old AC
dbNameCurrent = assembly_previous
; database name staging area (will be created if not exists)
dbNameStage = CoLTTC
; The LSID suffix for the new CoL
lsidSuffix = col2012acv15
; maximum number of records to fetch from old and new taxa table
; zero or less means no limit
readLimit = 0


