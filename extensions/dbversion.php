<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# Database Version extension
#
# Purpose:
# Track current 'version' of database tables to enable upgrading structure easily.
#
# Requires (psql or mysqli) and rowobj extensions.
#

class dbversion extends rowobj
{
    static $fields = array(
        "id"         => array("type"=>"id"),
        "version"    => array("type"=>"int"),
        "created"    => array("type"=>"createdate"),
        );

    static function tablename() {
        static $tablename = null;
        if (null == $tablename) {
            $tablename = get_setting("dbversion", "version_table", "dbversion");
        }
        return $tablename;
    }
    static function idcolname(){return "id";}
    static function create_table($class_name = null){rowobj::create_table(__CLASS__);}
    static function &get() {
        $query = new dbquery(__CLASS__, self::tablename(), self::idcolname());
        return $query;
    }
    static function count_all(){return self::get()->count();}
    static function &get_id($id = null){return self::get()->id($id);}

    static function install_or_update_database() {
        $return = true;
        self::begin_transaction();
        $models = self::get_model_names();
        if (iterable($models) && count($models) > 0) {
            # create table for model object
            foreach ($models as $model_name) {
                if (isset($model_name::$fields)) {
                    # create table
                    $model_name::create_table();
                }
            }
            self::create_table();
            $installed_version = self::get_installed_version();
            if (null === $installed_version) {
                # database has just been created, so create any data the system requires
                foreach ($models as $model_name) {
                    $model_name::initialise();
                }
            } else {
                # make any necessary changes to existing table structures and data
                $active_version = get_setting("dbversion", "current_db_version", 1);
                if ($installed_version < $active_version) {
                    $installed_version++;
                    foreach ($models as $model_name) {
                        if (!$model_name::update_table($installed_version)) {
                            $return = false;
                        }
                    }
                    if ($return)
                        self::update_table($installed_version);
                }
                if ($installed_version < $active_version) {
                    $return = false;    # one update down, but more needed so run again
                }
            }
            # todo: create foreign keys
            # note: foreign keys should be created after table updates because updates might add missing columns
        } else {
            # no models
            echo("No models to create tables for.");
        }
        self::commit_transaction();
        return $return;    # done
    }

    static function get_installed_version() {
        static $installed_version = null;
        if (null == $installed_version) {
            try {
                $iv = self::get()->order_by("id")->page(1, 0)->one();
                $installed_version = max(1, $iv->version);
            } catch (Exception $e) {
                parent::static_insert(__CLASS__, array("version"=>get_setting("dbversion", "current_db_version", 1)));
                $installed_version = null;  # return null when the system has just been installed
            }
        }
        return $installed_version;
    }

    static function get_model_names() {
        $models = array();
        $model_files = glob(OR_SITE_DIRECTORY."/models/*.php");
        foreach ($model_files as $num => $model_file) {
            $models[] = pathinfo($model_file, PATHINFO_FILENAME);
        }
        return $models;
    }

    static function update_table($installed_version) {
        self::get()->order_by("id")->update(self::tablename(), array("version"=>$installed_version));
        return true;
    }
}
