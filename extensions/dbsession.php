<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# Database Session extension
#
# Purpose:
# Store sessions in the database. Will automatically create the table if it does not exist.
#

global $session;
$session = new dbsession();


function set_session_data($name, $value) {
    $_SESSION[$name] = $value;
}
function get_session_data($name, $default = null) {
    return (isset($_SESSION[$name]) ? $_SESSION[$name] : $default);
}
function get_clear_session_data($name, $default = null) {
    $value = $default;
    if (isset($_SESSION[$name])) {
        $value = $_SESSION[$name];
        unset($_SESSION[$name]);
    }
    return $value;
}
function clear_session_data($name) {
    unset($_SESSION[$name]);
}


class dbsession extends db {
    private $session_settings;

    function __construct() {
        parent::__construct();    # connect to database

        set_setting_default("dbsession", "lifetime",          60*60*24);
        set_setting_default("dbsession", "regen_lifetime",    60*30);
        set_setting_default("dbsession", "dbtable",           "sessions");
        set_setting_default("dbsession", "session_name",      "cookie");
        $this->session_settings = get_setting(null, "dbsession");

        session_set_save_handler(array(&$this, "open"),
                                 array(&$this, "close"),
                                 array(&$this, "read"),
                                 array(&$this, "write"),
                                 array(&$this, "destroy"),
                                 array(&$this, "gc"));
        session_set_cookie_params($this->session_settings["lifetime"], "/");
        session_name($this->session_settings["session_name"]);
        session_start();

        global $request;
        $request['route']['parameters']['cookie_set'] = isset($_COOKIE[session_name()]);

        # check if session has expired
        if (get_session_data("last_access_time")) {
            if ((time() - get_session_data("last_access_time")) > $this->session_settings["lifetime"]) {
                # session expired, so reset it

                # destroy session variables
                session_unset();

                # delete session cookie
                if (isset($_COOKIE[session_name()])) {
                    unset($_COOKIE[session_name()]);
                    setcookie(session_name(), "", time()-42000, "/");
                }
                # finally, destroy the session
                session_destroy();
            }
        }
        set_session_data("last_access_time", time());

        # check if the session id should be regenerated
        if (get_session_data("last_regen_time")) {
            if ((time() - get_session_data("last_regen_time")) > $this->session_settings["regen_lifetime"]) {
                session_regenerate_id(TRUE);
                set_session_data("last_regen_time", time());
            }
        } else {
            set_session_data("last_regen_time", time());
        }
        return true;
    }

    function __destruct() {
        # this will be called before session:write(), so destruct in close() instead
        return true;
    }

    function open() {
        if ($this->is_connected()) {
            if (1 == count($this->show_tables($this->session_settings["dbtable"]))) {
                return true;
            } else {
                # build the sessions table
                $fields = array(
                    "id"             => array("type"=>"varchar", "size"=>32),
                    "last_access"    => array("type"=>"int", "null"=>false),
                    "data"           => array("type"=>"text", "null"=>false),
                    );
                return $this->create_table("sessions", $fields, array("id"=>true));
            }
        }
        return false;
    }

    function close() {
        parent::__destruct();
        return true;
    }

    function read($id) {
        $this->where("id", "=", $id);
        return $this->select_value($this->session_settings["dbtable"], "data");
    }

    function write($id, $data) {
        $data = array("last_access"=>time(), "data"=>$data);
        $this->insert_or_update($this->session_settings["dbtable"], $data, array("id"=>$id));
        return true;
    }

    function destroy($id) {
        $this->where("id", "=", $id);
        $this->delete($this->session_settings["dbtable"]);
        return true;
    }

    function gc($max) {
        $this->where("last_access", "<", time() - $max);
        $this->delete($this->session_settings["dbtable"]);
        return true;
    }
}
