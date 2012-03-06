[source]
driver = mysql
host = @SOURCE.DBHOST@
username = @SOURCE.DBUSER@
password = @SOURCE.DBPASS@
port =  @SOURCE.DBPORT@; Can be empty
dbname = @SOURCE.DBNAME@
; separate options by comma
options = "PDO::MYSQL_ATTR_INIT_COMMAND=set names utf8"

[target]
driver = mysql
host = @TARGET.DBHOST@
username = @TARGET.DBUSER@
password = @TARGET.DBPASS@
port = @TARGET.DBPORT@; Can be empty
dbname = @TARGET.DBNAME@
; separate options by comma
options = "PDO::MYSQL_ATTR_INIT_COMMAND=set names utf8"