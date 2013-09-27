<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# PostgreSQL Database extension
#
# Purpose:
# Access PostgreSQL databases.
#
# Note: the MySQLi wrapper class does not share a base class with this, but both have the same object name,
#   same functions and same parameters. Both are therefore interchangeable, but cannot be used at the same time.
#

class db
{
    private static $connection     = null;
    private static $settings       = null;
    private static $use_count      = 0;
    private static $trans_count    = 0;        # count transactions started
    private static $trans_ok       = true;        # no errors during transaction
    private static $queries        = array();
    private static $previous_exception_handler = null;
    protected $columns             = array();
    protected $joins               = array();
    protected $where               = array();
    protected $group_by            = array();
    protected $order_by            = array();
    protected $limit               = null;
    protected $offset              = null;

    public static $booloan_true    = "t";
    public static $booloan_false   = "f";

    const GET_RESULTS              = 0;
    const GET_INSERT               = 1;
    const GET_AFFECTED             = 2;
    const GET_NOTHING              = 3;

    function __construct() {
        if (! self::_open_connection()) {
            throw new Exception('Error: failed to open database connection to '.self::_get_setting('hostname').' with '.
                'username '.self::_get_setting('username').'.');
        }
    }

    function __destruct() {
        self::_close_connection();
    }

    static function is_connected() {
        return self::_get_connection();
    }

    function begin_transaction() {
        if (self::_increment_transaction()) {
            $this->query('start transaction', self::GET_NOTHING);
        }
    }

    function commit_transaction() {
        if (self::_decrement_transaction()) {
            if (self::_check_transaction()) {
                $this->query('commit', self::GET_NOTHING);
            } else {
                $this->query('rollback', self::GET_NOTHING);
            }
        }
    }

    # todo: abort transaction

    static function get_affected_rows($result) {
        $aff_count = pg_affected_rows($result);
        return $aff_count;
    }

    function get_insert_id() {
        $row = $this->query('select lastval() as "insert_id"', self::GET_RESULTS);
        if (iterable($row) && 1 == count($row)) {
            return $row[0]->insert_id;
        } else {
            throw new Exception('Error getting insert value for query: '.$this->queries[count($this->queries)-2]);
        }
        return null;
    }

    function show_tables($tablename = null) {
        $this->column('table_name');
        $this->where('table_schema', '=', self::_get_setting('pg_schema'));
        if (null != $tablename) {
            $this->and_where('table_name', 'like', $tablename);
        }
        $row = $this->select('information_schema.tables', false);
        if (iterable($row))    # return an array of values
        {
            $values = array();
            foreach ($row as $value) {
                $values[] = $value->table_name;
            }
            return $values;
        }
        return array();
    }

    function query($sql, $get_results = self::GET_NOTHING) {
        $this->_reset();
        try {
            $result = pg_query(self::_get_connection(), $sql);
        } catch (Exception $e) {
            # add the full query so it is logged and/or emailed
            throw new Exception($e->getMessage()."\nSQL: $sql", $e->getCode(), $e);
        }
        if (false === $result) {
            $err_msg = pg_last_error(self::_get_connection());
            throw new Exception("Error: psql error '$err_msg' while running query '$sql'.");
            self::_transaction_error();    # in case we're using a transaction
            if (self::_get_setting('debug')) {
                self::track_query("QUERY FAILED: $sql");
            }
            return false;
        } else {
            if (self::_get_setting('debug')) {
                self::track_query($sql);
            }
            switch ($get_results) {
                case self::GET_RESULTS:
                {
                    $rows = array();
                    do {} while(false !== ($row = pg_fetch_object($result)) && $rows[] = $row);
                    return $rows;
                }
                break;
                case self::GET_INSERT:
                return $this->get_insert_id();
                break;
                case self::GET_AFFECTED:
                return $this->get_affected_rows($result);
                break;
                case self::GET_NOTHING:
                default:
                return null;
                break;
            }
        }
    }

    # query functions

    function count($table) {
        $sql = 'select count(*) as row_count from '.$this->escape_table($table);
        $sql .= $this->joins_to_string($table);
        $sql .= $this->where_to_string($table);
        $count = $this->query($sql, self::GET_RESULTS);
        return (count($count) > 0 ? $count[0]->row_count : 0);
    }

    function select($table, $add_schema = true) {
        # if no column specified, then assume all columns
        $select_columns = "*";
        if (0 == count($this->columns)) {
            if (count($this->joins) > 0) {
                # Fix to prevent column collisions silently overwriting data when joining tables.
                # selecting from multiple tables without specifying columns will cause column values to be overwritten
                #   when a column from the second table has the same name as a column in the first table. prevent this
                #   by assuming only data from the first table is required and the second table is used only as a constraint.
                #   if data from the second table is required, specify the columns explicity
                $select_columns = $this->escape_table($table).".*";
            }
        } else {
            $select_columns = implode(", ", $this->columns);
        }
        $sql = "select $select_columns";
        $sql .= ' from '.($add_schema ? $this->escape_table($table) : $this->_escape_column($table));
        $sql .= $this->joins_to_string($table);
        $sql .= $this->where_to_string($table);
        if (count($this->group_by) > 0) {
            $sql .= ' group by '.implode(', ', $this->group_by);
        }
        if (count($this->order_by) > 0) {
            $sql .= ' order by '.implode(', ', $this->order_by);
        }
        if (null != $this->limit) {
            $sql .= ' limit '.$this->limit;
            if (null != $this->offset) {
                $sql .= ' offset '.$this->offset;
            }
        }
        return $this->query($sql, self::GET_RESULTS);
    }

    function select_one($table) {
        # note: do not limit to one result because the query must do that
        $result = $this->select($table);
        return (1 == count($result) ? $result[0] : null);
    }

    function select_value($table, $column, $default = null) {
        $this->columns = array($this->_escape_column($column));
        $row = $this->select($table);
        if (1 == count($row)) {
            return $row[0]->$column;
        } else {
            if (iterable($row))    # return an array of values
            {
                $values = array();
                foreach ($row as $value) {
                    $values[] = $value->$column;
                }
                return $values;
            }
        }
        return $default;
    }

    function select_values($table, $column, $default = null) {
        $this->columns = array($this->_escape_column($column));
        $row = $this->select($table);
        $values = array();
        foreach ($row as $value) {
            $values[] = $value->$column;
        }
        return $values;
    }

    function subselect($table, $value_name) {
        if (count($this->columns) > 0) {
            $sql = 'select '.array_pop($this->columns);
            $sql .= ' from '.$this->escape_table($table);
            $sql .= $this->joins_to_string($table);
            $sql .= $this->where_to_string($table);
            if (count($this->group_by) > 0) {
                $sql .= ' group by '.implode(', ', $this->group_by);
            }
            if (count($this->order_by) > 0) {
                $sql .= ' order by '.implode(', ', $this->order_by);
            }
            if (null != $this->limit) {
                $sql .= ' limit '.$this->limit;
                if (null != $this->offset) {
                    $sql .= ' offset '.$this->offset;
                }
            }
            $this->_reset(false);    # retain existing columns
            $this->column("($sql) as $value_name", false);
        } else {
            throw new Exception("Error: subselect with no columns.");
        }
    }

    function insert($table, $data = null, $get_id = true) {
        if (iterable($data)) {
            $columns = array();
            $values = array();
            foreach ($data as $column => $value) {
                $columns[] = $this->_escape_column($column);
                $values[] = $this->_escape_data($value);
            }
            $sql = 'insert into '.$this->escape_table($table).
                ' ('.implode(', ', $columns).') values ('.implode(', ', $values).')';
            return $this->query($sql, ($get_id ? self::GET_INSERT : self::GET_NOTHING));
        } else {
            throw new Exception("Error: no data while inserting into '$table'.");
        }
    }

    function update($table, $data) {
        if (iterable($data)) {
            $new_values = array();
            foreach ($data as $column => $value) {
                $new_values[] = $this->_escape_column($column).' = '.$this->_escape_data($value);
            }
            $sql = 'update '.$this->escape_table($table).' set '.implode(', ', $new_values);
            $sql .= $this->where_to_string($table);
            if (null != $this->limit) {
                $sql .= ' limit '.$this->limit;
                if (null != $this->offset) {
                    $sql .= ' offset '.$this->offset;
                }
            }
            return $this->query($sql, self::GET_AFFECTED);
        } else {
            throw new Exception("Error: no data while inserting into '$table'.");
        }
    }

    function insert_or_update($table, $data, $where) {
        if (iterable($data)) {
            foreach ($where as $key => $value) {
                $this->where($key, '=', $value);
            }
            if (0 == $this->count($table)) {
                # insert!
                foreach ($where as $key => $value) {
                    # merge the constraint value into the data
                    $data[$key] = $value;
                }
                $this->insert($table, $data, false);    # don't return id
            } else {
                # update!
                foreach ($where as $key => $value) {
                    # where value was reset, so do it again
                    $this->where($key, '=', $value);
                }
                $this->update($table, $data);
            }
        } else {
            throw new Exception("Error: no data while inserting or updating into '$table'.");
        }
    }

    function delete($table) {
        $sql = 'delete from '.$this->escape_table($table);
        $sql .= $this->where_to_string($table);
        if (null != $this->limit) {
            $sql .= ' limit '.$this->limit;
            if (null != $this->offset) {
                $sql .= ' offset '.$this->offset;
            }
        }
        return $this->query($sql, self::GET_AFFECTED);
    }

    function create_table($table, $columns, $keys) {
        if (count($columns) > 0) {
            if (0 == count($this->show_tables($table)))    # check if the table already exists
            {
                $sql = 'create table '.$this->escape_table($table)." (";
                $add_comma = false;
                foreach ($columns as $column => $type) {
                    if ($add_comma) {
                        $sql .= ', ';
                    } else {
                        $add_comma = true;
                    }
                    $sql .= $this->_escape_column($column)." ".self::column_attributes_to_string($type);
                }
                $p_keys = '';
                $db_keys = '';
                foreach ($keys as $key => $primary) {
                    $key = $this->_escape_column($key);
                    if (true === $primary) {
                        $p_keys .= ", primary key ($key)";
                    } else {
                        $db_keys .= ", key ($key)";
                    }
                }
                $sql .= " {$p_keys}$db_keys)";
                $this->query($sql, self::GET_NOTHING);
            } else {
                foreach ($columns as $name => $column) {
                    # reset any sequences in case database was reloaded
                    if (isset($column["type"]) && "id" == $column["type"]) {
                        $actual_table = $this->escape_table($table);
                        $actual_column = $this->_escape_column($name);
                        $sql = "SELECT pg_catalog.setval(pg_get_serial_sequence('{$actual_table}', '{$name}'), ".
                            "(SELECT MAX({$actual_column}) FROM {$actual_table})+1);";
                        $this->query($sql, self::GET_NOTHING);
                    }
                }
            }
        } else {
            throw new Exception("Warning: attempting to create table '$table' with no columns.");
        }
    }

    # alter table functions

    function add_column($table, $column, $type, $after_col = null) {
        # SELECT * FROM information_schema.columns WHERE table_name = 'projects' and table_schema = 'public'
        $this->column('count(*) as row_count', false);
        $this->where('table_schema', '=', self::_get_setting('pg_schema'));
        $this->where('table_name', '=', $table);
        $this->where('column_name', '=', $column);
        $row = $this->select('information_schema.columns', false);            # would use count, but it adds schema
        if (0 == $row[0]->row_count) {
            $sql = 'alter table '.$this->escape_table($table)." add ".$this->_escape_column($column)." ".self::column_attributes_to_string($type);
            # psql does not allow this, so skip it
            #if (null != $after_col)
            #{
            #    $sql .= ' after '.$this->_escape_column($after_col);
            #}
            $this->query($sql, self::GET_NOTHING);
        }
    }

    function drop_column($table, $column) {
        $sql = "alter table ".$this->escape_table($table)." drop ".$this->_escape_column($column);
        $this->query($sql, self::GET_NOTHING);
    }

    function change_column_type($table, $column, $type) {
        switch ($type["type"]) {
            case "id":$new_type = "SERIAL";break;

            case "bool":
            case "boolean":$new_type = "BOOLEAN";break;

            case "int":$new_type = "INTEGER";break;

            case "float":$new_type = "REAL";break;

            case "double":$new_type = "DOUBLE PRECISION";break;

            case "decimal":
            case "numeric":
            $digits = (isset($attributes["digits"]) && iterable($attributes["digits"]))
                ? "({$attributes['digits'][0]},{$attributes['digits'][1]})" : "";
            $new_type = "NUMERIC{$digits}";
            break;

            case "text":$new_type = "TEXT";break;

            case "varchar":
            $size = (isset($attributes["size"]) && (int)$attributes["size"] > 0) ? (int)$attributes["size"] : 10;
            $new_type = "VARCHAR($size)";
            break;

            case "blob":case "binary":$new_type = "BYTEA";break;

            case "createdate":$new_type = "TIMESTAMP";break;

            case "timestamp":case "datetime":$new_type = "TIMESTAMP";break;

            case "time":$new_type = "TIME";break;

            default:
            throw new Exception("Unknown database column type '{$attributes["type"]}'.");
            break;
        }
        $sql = "alter table ".$this->escape_table($table)." alter column ".$this->_escape_column($column)." type ".$new_type;
        $this->query($sql, self::GET_NOTHING);
        if (isset($type["null"])) {
            $sql = "alter table ".$this->escape_table($table)." alter column ".$this->_escape_column($column)." ".
                ($type["null"] ? "SET" : "DROP")." NOT NULL";
            $this->query($sql, self::GET_NOTHING);
        }
        if (isset($type["default"])) {
            $sql = "alter table ".$this->escape_table($table)." alter column ".$this->_escape_column($column)." SET DEFAULT ".
                (null === $type["default"] ? "NULL" : $type["default"]);
            $this->query($sql, self::GET_NOTHING);
        } else {
            $sql = "alter table ".$this->escape_table($table)." alter column ".$this->_escape_column($column)." DROP DEFAULT";
            $this->query($sql, self::GET_NOTHING);
        }
    }

    function change_column_name($table, $current_name, $new_name, $type) {
        # note: column type is not required by psql, but it is required by mysql so require the parameter to keep portability
        $sql = "alter table ".$this->escape_table($table)." rename column ".$this->_escape_column($current_name)." to ".
            $this->_escape_column($new_name);
        $this->query($sql, self::GET_NOTHING);
    }

    # get/set parameter functions

    function column($columns, $escape = true) {
        if (iterable($columns)) {
            foreach ($columns as $name => $alias) {
                if (is_string($name)) {
                    if ($escape) {
                        $this->columns[] = $this->_escape_column($name).' as '.$this->_escape_column($alias);
                    } else {
                        # should be safe to escape the alias
                        $this->columns[] = $name.' as '.$this->_escape_column($alias);
                    }
                } else {
                    $this->column($alias, $escape);        # in this case, it isn't really an alias
                }
            }
        } else {
            if ($escape) {
                $this->columns[] = $this->_escape_column($columns);
            } else {
                $this->columns[] = $columns;
            }
        }
        return $this;
    }

    function join($table_a, $col_a, $table_b, $col_b, $type = "left", $options = array()) {
        # notes:
        # - table b might be aliased
        # - either column might not need escaping
        $join           = new stdClass;
        $join->table_a  = $table_a;
        $join->table_b  = $table_b;
        $join->col_a    = $col_a;
        $join->col_b    = $col_b;
        $join->type     = $type;
        $join->options  = iterable($options) ? $options : array();
        $this->joins[]  = $join;
        return $this;
    }
    private function joins_to_string($table) {
        $joins_to_string = "";
        foreach ($this->joins as $join) {
            # if table_a isn't set, assume it is the table this query is selecting from
            if (null == $join->table_a)$join->table_a = $table;
            # join on table b
            $join->join_table = $this->escape_table($join->table_b);
            # if table b is aliased, then use that for the 'on' portion of the join
            if (isset($join->options["alias_table_b"])){$join->table_b = $join->options["alias_table_b"];}
            # escape the table names unless specifically directed not to
            if (!isset($join->options["noescape_table_b"])){$join->table_b = $this->escape_table($join->table_b);}
            if (!isset($join->options["noescape_table_a"])){$join->table_a = $this->escape_table($join->table_a);}
            # escape the join column names unless specfically directed not to
            if (!isset($join->options["noescape_col_a"])){$join->col_a = $this->_escape_column($join->col_a);}
            if (!isset($join->options["noescape_col_b"])){$join->col_b = $this->_escape_column($join->col_b);}

            # build the join string
            $joins_to_string .= " {$join->type} join ".
                $join->join_table.(isset($join->options["alias_table_b"]) ? " as ".$join->options["alias_table_b"] : null).
                " on ".$join->table_a.".".$join->col_a." = ".
                (isset($join->options["alias_table_b"]) ? $join->options["alias_table_b"] : $join->table_b).".".$join->col_b;
        }
        return $joins_to_string;
    }

    function where($column, $operator = true, $value = null, $and = true, $options = array()) {
        if (!in_array($operator, array("=", "<>", "is", "is not", "<", ">", ">=", "<="))) {
            # to help when porting code from rowBase objects which do not pass an operator, shift parameters
            $value = $operator;
            $operator = "=";
            $trace = debug_backtrace();
error_log("assuming operator is = for where query on $column - $value in {$trace[0]['file']} : {$trace[0]['line']}");
        }
        $this->_where($column, $operator, $value, $and, $options);
        return $this;
    }
    function or_where($column, $operator = true, $value = null, $options = array()) {
        $this->_where($column, $operator, $value, false, $options);
        return $this;
    }
    function and_where($column, $operator = true, $value = null, $options = array()) {
        $this->_where($column, $operator, $value, true, $options);
        return $this;
    }
    function where_in($column, Array $items, $and = true, $options = array()) {
        $escaped_items = array();
        foreach ($items as $item) {
            $escaped_items[] = $this->_escape_data($item);
        }
        $options["no_escape_value"] = true;
        $this->_where($column, "in", "(".implode(", ", $escaped_items).")", $and, $options);
        return $this;
    }
    function where_not_in($column, Array $items, $and = true, $options = array()) {
        $escaped_items = array();
        foreach ($items as $item) {
            $escaped_items[] = $this->_escape_data($item);
        }
        $options["no_escape_value"] = true;
        $this->_where($column, "not in", "(".implode(", ", $escaped_items).")", $and, $options);
        return $this;
    }

    function group_by($group_by, $escape = true) {
        $this->group_by[]    = $escape ? $this->_escape_column($group_by) : $group_by;
        return $this;
    }
    function order_by($order_by, $sort = 'asc', $escape = true) {
        $this->order_by[]    = ($escape ? $this->_escape_column($order_by) : $order_by).' '.$sort;
        return $this;
    }
    function random_order() {
        $this->order_by[]    = "RANDOM()";
        return $this;
    }
    function &limit($limit, $offset = null) {
        if (0 == $limit) {
            throw new Exception("Query limit is zero. Check if limit and offset are swapped.");
        }
        $this->limit         = $limit;
        $this->offset        = $offset;
        return $this;
    }

    # internal functions

    function _reset($reset_columns = true) {
        if ($reset_columns) {
            $this->columns = array();
        }
        $this->joins       = array();
        $this->where       = array();
        $this->group_by    = array();
        $this->order_by    = array();
        $this->limit       = null;
        $this->offset      = null;
    }

    function escape_table($name) {
        return '"'.str_replace('.', '"."', self::_get_setting('pg_schema').'.'.$name).'"';
    }

    function _escape_column($name) {
        # is not escapting columns wrapped in (brackets) a security risk?
        return ('*' == $name) ? '*' : ('(' == $name[0] ? $name : '"'.str_replace('.','"."',$name).'"');
    }

    function _escape_data($value) {
        switch (gettype($value)) {
            case 'object':     return "'".pg_escape_string((string)$value)."'"; break;
            case 'string':     return "'".pg_escape_string($value)."'"; break;
            case 'boolean':    return (true === $value) ? "'t'" : "'f'"; break;
            case 'NULL':       return 'NULL'; break;
            default:           return $value; break;
        }
    }

    private function _where($column, $operator, $value, $and, $options = array()) {
        $where              = new stdClass;
        $where->column      = $column;
        $where->operator    = $operator;
        $where->value       = $value;
        $where->and         = $and;
        $where->options     = iterable($options) ? $options : array();  # can be: no_escape_column=>true, no_escape_value=>true
        $this->where[]      = $where;
    }
    private function where_to_string($table) {
        $where_to_string = "";
        foreach ($this->where as $where) {
            if (iterable($where->column)) {
                $subquery = "";
                foreach ($where->column as $value) {
                    if (iterable($value) && count($value) > 2) {
throw new Exception("bam"); # how does subquery work?
#                        $this->_where($subquery, $value[0], $value[1], $value[2],
#                            isset($value[3]) ? $value[3] : true, isset($value[4]) ? $value[4] : true);
                    } else {
                        throw new Exception("Error: parameters to where() are not array for {$where->column[0]} {$where->column[1]} {$where->column[2]}.");
                    }
                }
                if ($subquery != '') {
                    if ($where_to_string != '') {
                        $where_to_string .= ($where->and ? ' and' : ' or');
                    }
                    $where_to_string .= " ($subquery)";
                }
            } else {
                # if this where clause follows another, add 'and' or 'or'
                if ($where_to_string != '') {
                    $where_to_string .= ($where->and ? " and" : " or");
                }
                if (isset($where->options["begin_group"]) && $where->options["begin_group"] > 0) {
                    $where_to_string .= " ".str_repeat("(", $where->options["begin_group"]);
                }

                # add the column name
                if (isset($where->options["no_escape_column"]) && $where->options["no_escape_column"]) {
                    $where_to_string .= " $where->column";
                } else {
                    $where_to_string .= " ".$this->_escape_column($where->column);
                }

                # confirm the operator and add it
                if ($where->operator == "!=") {
                    $where->operator == "<>";
                }
                if (null === $where->value) {
                    # note: the operator should be "is" or "is not"
                    if ($where->operator == "=") {
                        $where->operator = "is";
                    }
                    else if ($where->operator == "<>") {
                        $where->operator = "is not";
                    }
                }
                $where_to_string .= " $where->operator";

                # add the value
                if (0 == strcasecmp('now()', $where->value) || isset($where->options["no_escape_value"]) && $where->options["no_escape_value"]) {
                    $where_to_string .= " $where->value";
                } else {
                    $where_to_string .= " ".$this->_escape_data($where->value);
                }
                if (isset($where->options["end_group"]) && $where->options["end_group"] > 0) {
                    $where_to_string .= str_repeat(")", $where->options["end_group"]);
                }
            }
        }
        if (strlen($where_to_string) > 0) {
            $where_to_string = " where".$where_to_string;
        }
        return $where_to_string;
    }

    static function _last_query() {
        return count(self::$queries) > 0 ? self::$queries[count(self::$queries)-1] : null;
    }

    static function _all_queries() {
        return implode("<br />\n", self::$queries);
    }

    static function _open_connection() {
        if (null == self::$settings) {
            set_setting_default('database', 'debug', false);
            set_setting_default('database', 'pg_schema', 'public');
            self::$settings = get_setting(null,'database');
        }
        if (null == self::$connection) {
            # port=xx
            self::$connection = pg_connect("host=".self::$settings['hostname']." dbname=".self::$settings['database'].
                " user=".self::$settings['username']." password=".self::$settings['password']);
            if (null == self::$connection) {
                return false;
            }
        }
        if (0 == self::$use_count) {
            self::prepare_handle_exception();

            # the first time we connect to the database, check that the schema exists
            $schema = self::$settings['pg_schema'];
            $result = pg_query(self::$connection, "select exists (select * from pg_catalog.pg_namespace where nspname = '{$schema}') as schema_exists;");
            if ('f' == pg_fetch_object($result)->schema_exists) {
                pg_query(self::$connection, "create schema \"{$schema}\";");
            }
            pg_free_result($result);
        }
        ++self::$use_count;
        return true;
    }

    static function _get_connection() {
        return self::$connection;
    }

    static function _close_connection() {
        if (0 == --self::$use_count && null != self::$connection) {
            pg_close(self::$connection);
            self::$connection = null;
            if (self::_get_setting('debug')) {
                error_log("----- begin database queries -----");
                foreach (self::$queries as $query) {
                    error_log($query);
                }
                error_log("----- end database queries -----");
            }
        }
    }

    static function _get_setting($name) {
        return self::$settings[$name];
    }

    static function column_attributes_to_string($attributes) {
        $value = "";
        if (iterable($attributes)) {
            switch ($attributes["type"]) {
                # http://www.postgresql.org/docs/8.4/static/datatype.html
                # todo: review mysql column types and re-consider these mappings
                case "id":    # todo: option for bigserial?
                $value = "SERIAL UNIQUE";
                break;

                case "bool":
                case "boolean":
                $null = (isset($attributes["null"]) && $attributes["null"]) ? "" : " NOT NULL";
                $default = (isset($attributes["default"])) ? (null === $attributes["default"]
                    ? " DEFAULT NULL" : " DEFAULT ".($attributes["default"] ? "TRUE" : "FALSE")) : "";
                $value = "BOOLEAN{$null}{$default}";
                break;

                case "int":
                $null = (isset($attributes["null"]) && $attributes["null"]) ? "" : " NOT NULL";
                $default = (isset($attributes["default"])) ? (null === $attributes["default"]
                    ? " DEFAULT NULL" : " DEFAULT ".(int)$attributes["default"]) : "";
                #$signed = (isset($attributes["signed"]) && !$attributes["signed"]) ? " UNSIGNED" : " SIGNED";
                $value = "INTEGER{$null}{$default}";
                break;

                case "float":
                $null = (isset($attributes["null"]) && $attributes["null"]) ? "" : " NOT NULL";
                $default = (isset($attributes["default"])) ? (null === $attributes["default"]
                    ? " DEFAULT NULL" : " DEFAULT ".(float)$attributes["default"]) : "";
                #$signed = (isset($attributes["signed"]) && !$attributes["signed"]) ? " UNSIGNED" : "";
                $value = "REAL{$null}{$default}";
                break;

                case "double":
                $null = (isset($attributes["null"]) && $attributes["null"]) ? "" : " NOT NULL";
                $default = (isset($attributes["default"])) ? (null === $attributes["default"]
                    ? " DEFAULT NULL" : " DEFAULT ".(double)$attributes["default"]) : "";
                #$signed = (isset($attributes["signed"]) && !$attributes["signed"]) ? " UNSIGNED" : "";
                $value = "DOUBLE PRECISION{$null}{$default}";
                break;

                case "decimal":
                case "numeric":
                $null = (isset($attributes["null"]) && $attributes["null"]) ? "" : " NOT NULL";
                $default = (isset($attributes["default"])) ? (null === $attributes["default"]
                    ? " DEFAULT NULL" : " DEFAULT ".(double)$attributes["default"]) : "";
                #$signed = (isset($attributes["signed"]) && !$attributes["signed"]) ? " UNSIGNED" : "";
                $digits = (isset($attributes["digits"]) && iterable($attributes["digits"]))
                    ? "({$attributes['digits'][0]},{$attributes['digits'][1]})" : "";
                $value = "NUMERIC{$digits}{$null}{$default}";
                break;

                case "text":
                $null = (isset($attributes["null"]) && $attributes["null"]) ? "" : " NOT NULL";
                $default = (isset($attributes["default"])) ? (null === $attributes["default"]
                    ? " DEFAULT NULL" : " DEFAULT \"{$attributes["default"]}\"") : "";
                $unique = (isset($attributes["unique"]) && $attributes["unique"]) ? " UNIQUE" : "";
                $value = "TEXT{$null}{$default}{$unique}";
                break;

                case "varchar":
                $null = (isset($attributes["null"]) && $attributes["null"]) ? "" : " NOT NULL";
                $default = (isset($attributes["default"])) ? (null === $attributes["default"]
                    ? " DEFAULT NULL" : " DEFAULT \"{$attributes["default"]}\"") : "";
                $size = (isset($attributes["size"]) && (int)$attributes["size"] > 0) ? (int)$attributes["size"] : 10;
                $unique = (isset($attributes["unique"]) && $attributes["unique"]) ? " UNIQUE" : "";
                $value = "VARCHAR($size){$null}{$default}{$unique}";
                break;

                case "blob":
                case "binary":
                $null = (isset($attributes["null"]) && $attributes["null"]) ? "" : " NOT NULL";
                $default = (isset($attributes["default"])) ? (null === $attributes["default"]
                    ? " DEFAULT NULL" : " DEFAULT \"{$attributes["default"]}\"") : "";
                $value = "BYTEA{$null}{$default}";
                break;

                case "createdate":
                $value = "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP";
                break;

                case "timestamp":
                case "datetime":
                $null = (isset($attributes["null"]) && $attributes["null"]) ? "" : " NOT NULL";
                $value = "TIMESTAMP{$null}";
                break;

                case "time":
                $null = (isset($attributes["null"]) && $attributes["null"]) ? "" : " NOT NULL";
                $value = "TIME{$null}";
                break;

                default:
                throw new Exception("Unknown database column type '{$attributes["type"]}'.");
                break;
            }
        } else {
            $value = $attributes;
        }
        return $value;
    }

    static function _increment_transaction() {
        if (0 == self::$trans_count++) {
            self::$trans_ok = true;
            return true;
        }
        return false;
    }

    static function _transaction_error() {
        # if we're using a transaction, save the error
        if (self::$trans_count > 0) {
            self::$trans_ok = false;
        }
    }

    static function _decrement_transaction() {
        return (self::$trans_count >= 0 && 0 == --self::$trans_count);
    }

    static function _check_transaction() {
        return self::$trans_ok;
    }

    private static function track_query($new_query) {
        self::$queries[] = $new_query;
    }

    static function prepare_handle_exception() {
        if (false === self::$previous_exception_handler) {
            # todo: the previous exception handler might be null... use false?
            self::$previous_exception_handler = set_exception_handler(array("db", "_handle_exception"));
        }
    }

    static function _handle_exception($exception) {
        if (self::$trans_count >= 0) {
            # todo: make a global db object that could be accessed here?
            # we're in the middle of a transaction, so roll it back
            $db = new db();
            $db->query('rollback', self::GET_NOTHING);
        }
        if (self::$previous_exception_handler) {
            $function = self::$previous_exception_handler;
            if (iterable($function)) {
                if (count($function) == 2) {
                    $object_name = $function[0];
                    $method_name = $function[1];
                    if ($object_name != __CLASS__) {
                        $object_name::$method_name($exception);
                    }
                }
            } else {
                $function($exception);
            }
        }
    }
}
