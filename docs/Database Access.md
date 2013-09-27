
# Database Access

The database access extensions provide access to Postgresql and Mysql databases. An second optional extension (rowobj) builds on the first to simplify repetitive operations by exposing tables and rows as objects. A third optional extension (dbversion) builds on the first two to create and update the structure of the database.

    $settings["database"] = array(
        "username"=>"",             # database username
        "password"=>"",             # database password
        "database"=>"",             # database name
        "hostname"=>"127.0.0.1",    # hostname
        "debug"=>false,             # debug mod
        "pg\_schema"=>"",            # postgresql only. name of the database schema
        "charset"=>"utf8",          # mysql only. set the charset
        "mysql\_engine"=>"InnoDB",   # mysql only. set the database engine. ie: InnoDB, MyISAM
        "mysql\_port"=>3306,         # mysql only. port number to connect on
        "mysql\_socket"=>null,       # mysql only. socket to connect to
    );

The psql and mysqli extensions both contain objects named *db*, so only one of the extensions can be used at a time. Both extensions have the same functions, so a website can switch between postgresql and mysql by loading the appropriate extension.


## Rowobj Extension

The rowobj extension treats one table as an object and simplifies repetitive operations. Rowobj uses the db object (either psql or mysqli) internally, and replaces the need to call most db functions. Use the model\_autoloader extension with rowobj to have the model file automatically loaded the first time a rowobj-class object is referenced.


## DB Object Functions

* function add\_column(table, column, type, after\_col)

    Add a column to a table. Type is an array of column properties.

* function and\_where(column, operator, value, escape)

    Add a where clause to a query.

* static function begin\_transaction()

    Begin a database transaction. Call commit\_transaction() for every call to begin\_transaction().

* function change\_column\_name(table, current\_name, new\_name, type)

    Change a column name.

* function change\_column\_type(table, column, type)

    Change a column type. May not work when changing column constraints such as adding or removing NULL.

* function column(columns, escape)

    Call to add a single column or array of columns to a query.

* function column\_attributes\_to\_string(attributes)

* static function commit\_transaction()
    
    Commit a database transaction.

* function count(table)

    Returns the number of rows in the table.

* function create\_table(table, columns, keys)

* function delete(table)

    Delete rows from a table.

* function drop\_column(table, column)

    Drop a column from a table.

* function escape\_table(name)

* static function get\_affected\_rows(result)

    Return the number of rows affected by the last query. The update() function will return this value.

* static function get\_insert\_id()

    Return the id of the last inserted row. The insert() function will return this value.

* function group\_by(group\_by, escape)

    Add a column to the group by clause.

* function insert(table, data, get\_id)

* function insert\_or\_update(table, data, where)

* static function is\_connected()

* function join(table\_a, col\_a, table\_b, col\_b, type = "left", options = array())

    The 'options' parameter supports the following values:

    * alias\_table\_b -- alias for table b
    * noescape\_table\_a -- do not escape the name of table a
    * noescape\_table\_b -- do not escape the name of table b
    * noescape\_col\_a -- do not escape the name of column a
    * noescape\_col\_b -- do not escape the name of column b

* function limit(limit, offset)

* function or\_where(column, operator, value, escape)

* function order\_by(order\_by, sort, escape)

* function query(sql, get\_results)

* function random\_order()

    Sort the returned rows randomly.

* function select(table, add\_schema)

* function select\_one(table)

    Returns a single row if that is what the query returned. Returns null if no results, or more than one rows were returned by the query.

* function select\_value(table, column, default)

* function select\_values(table, column, default)

* function show\_tables(tablename)

    Returns a list of tables in the database. If the optional parameter is used it returns only tables with similar names.

* function subselect(table, value\_name)

* function update(table, data)

* function where(column, operator, value, and = true, escape = array())

    Add a where clause to a query. The 'and' parameter which determines if this clause is added using the AND or OR keyword. The 'escape' parameter is an array which may contain boolean values to prevent the column or value from being automatically escaped.

    * no\_escape\_column
    * no\_escape\_value

## Join Change

The join function was originally defined with four parameters:

* function join(other table, column a, column b, join type)

Now, the join function has more parameters to support more join types:

* function join(table\_a, col\_a, table\_b, col\_b, type = "left", options = array())

Options supports the following values:

* alias\_table\_b -- provide an alias for table b
* noescape\_table\_a -- do not escape the name of table a
* noescape\_table\_b -- do not escape the name of table b
* noescape\_col\_a -- do not escape the name of column a
* noescape\_col\_b -- do not escape the name of column b

The old function parameter order is not supported and all join() calls should be updated to the new format.

## Where Change

The join function was originally defined as follows:

* function where(column, operator, value, and, escape)

Now the function's last parameter is an array rather than a boolean so that escaping of the column name and the value can be controlled separately.

* no\_escape\_column
* no\_escape\_value
