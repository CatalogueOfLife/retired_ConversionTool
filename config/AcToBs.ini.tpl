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

[schema]
; path to directory including base scheme and denormalized tables dumps
path = /var/www/path

[settings]
version = @VERSION@
revision = @REVISION@

[col_plus]
; url to zip file containing csv dumps
csvUrl = "http://api.col.plus/download/ac-export.zip"
; path to root of CoL application
colPath = ""
; path to directory with all individual log files
logZipPath = ""
; database scheme of ACEF assembly database
schema = ""
; auto-pilot password
key = ""
; maximum runtime for auto-pilot script in seconds
runtime = 43200


[sitemaps]
; directory in which to store sitemaps
sitemapPath = sitemaps/
; base url of CoL species detail page
sitemapBaseUrl = http://www.catalogueoflife.org/col/details/species/id/

[keys]
; use natural keys (1) or numerical ids (0)
naturalKeys = 1
; use CoL+ unique ids (1) or traditional CoL natural keys (0)
colPlusKeys = 1

[dead_ends]
; copy dead ends in tree from source database?
deadEnds = 1