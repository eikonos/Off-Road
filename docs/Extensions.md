
# Extensions

Extensions are PHP code files that contain helper functions, classes or variables.

The [extension_loader layer](layers#extension_loader) loads all extensions listed in the [Settings file](settings).

    $settings["extensions"] = array("extension1", "extension2", "etc");

If an extension is only required on certain pages, you may prefer to load it in the controller for those pages.

    load_extension("extension1");

## Search Path

Extensions in the site/extensions folder are loaded first, then extensions in the off_road/extensions folder. If there are files with the same name, the file in the site/extension folder will be loaded.

## Off Road Extensions

### classform_helper

The classform_helper extension loads the classform extension and creates a form variable.

### dbsession

The dbsession extension stores the user session in the database and provides a few functions to access the $_SESSION variable. Must be loaded after a database extension (psql or mysqli).

    $settings["dbsession"] = array(
        "lifetime"=>60*60*24,      # how long the cookie lasts
        "regen_lifetime"=>60*30,   # how often (in seconds) the session is regenerated
        "dbtable"=>"sessions",     # name of the database table where sessions are stored
        "session_name"=>"cookie",  # used when calling the PHP function session_name()
    );

### dbversion

Used to version the structure of the database. Requires a database extension (psql or mysqli) and rowobj.

    $settings["dbversion"] = array(
        "version_table"=>"dbversion",  # name of the database table where the current version number is stored
        "current_db_version"=>1,       # current version of the database for the code
    );

### model_autoloader

The model_autoloader loads database model files in the site/models folder automatically the first time a model object is referenced. Note that the model object name must match the file name.

### mysqli

    $settings["database"] = array(
        "hostname"=>"127.0.0.1",  # database server host
        "database"=>"",           # database name
        "charset"=>"utf8",        # database character set (used when creating tables)
        "mysql_engine"=>"InnoDB", # database engine name (used when creating tables)
        "username"=>"",           # database username
        "password"=>"",           # database user password
        "debug"=>false,           # operate in debug mode?
        "if_not_exists"=>"if not exists",    # use 'if not exists' when creating tables?
    );

The mysqli extension is a wrapper around the mysqli database functions. See the [database access](database_access) page for details.

### pager

    $settings["pager"] = array(
        "link_count"=>3,               # number of links on each side of the active page link
        "pager_text"=>"Page:&emsp;",   # text to display before page links
        "prev_text"=>"&lang;",         # text or symbol to display as 'prev page' link
        "next_text"=>"&lang;",         # text or symbol to display as 'next page' link
        "spacer_text"=>"&#151;",       # text to display where page links are omitted
    );

### psql

The psql extension is a wrapper around the postgresql database functions. See the [database access](database_access) page for details.

    $settings["database"] = array(
        "hostname"=>"127.0.0.1",  # database server host
        "database"=>"",           # database name
        "pg_schema"=>"",          # postgresql schema. optional
        "username"=>"",           # database username
        "password"=>"",           # database user password
        "debug"=>false,           # operate in debug mode?
    );


### rowobj

Rowobj extends the mysqli and psql extensions to treat database tables and rows as objects. See the [database access](database_access) page for details.
