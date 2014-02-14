<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# Date Helper extension
#
# Purpose:
# Treat dates as objects.
#

# return a date_obj
function date_obj($date = "") {
    if (!is_a($date, "date_obj")) {
        # assume $date is a string date value
        $date = new date_obj($date);
    }
    return $date;
}

if (strnatcmp(phpversion(), "5.2.10") >= 0) {
    class date_obj extends DateTime {
        var $_data          = null;
        var $is_valid       = true;

        function __construct($date_string) {
            $this->_data = new stdClass;
            if (false === strtotime($date_string)) {
                # DateTime() throws an error for a bad date, so use a blank date instead
                $date_string = "0000-00-00 00:00:00";
            }
            parent::__construct($date_string);
            $user_timezone = get_setting("timezone", "user");
            if ($user_timezone) {
                $this->setTimezone(new DateTimeZone($user_timezone));
            }
            if ("" == $date_string || "0000-00-00 00:00:00" == $date_string) {
                $this->is_valid = false;
            }
        }

        function __toString() {
            return $this->datetime;
        }

        function value() {
            return $this->is_valid ? $this->getTimestamp() : null;
        }

        function is_valid() {
            return $this->is_valid;
        }

        function in_the_past() {
            if ($this->value) {
                return $this->value < date_obj("now")->value();
            }
            return null;
        }

        function in_the_future() {
            if ($this->value) {
                return $this->value > date_obj("now")->value();
            }
            return null;
        }

        function days_diff() {
            return abs(date_obj("now")->value() - $this->value()) / (60 * 60 * 24);
        }

        function now() {
            # it's silly to calculate the difference, then add it, but DateTime doesn't seem to offer a simple set() function
            # note that the timezone should still be the same as what was set in the constructor
            $this->add($this->diff(date_obj("now")));
            $this->_data = new stdClass;
            $this->is_valid = true;
            return $this;
        }

        function adjust($interval) {
            $this->modify($interval);
            $this->_data = new stdClass;
            return $this;
        }

        function __get($name) {
            if (property_exists($this->_data, $name))       # note isset() won't work with variable set to NULL
            {
                return $this->_data->{$name};
            } else {
                if (method_exists($this, $name)) {
                    $this->_data->{$name} = $this->{$name}();
                    return $this->_data->{$name};
                }
                # the error doesn't actually happen here, but wherever we're called from, so provide a helpful error
                $trace = debug_backtrace(false);
                $err_msg = get_class($this)." object does not have a {$name} value in {$trace[0]['file']} on line {$trace[0]['line']}.";
                throw new Exception($err_msg);
            }
        }

        function day_of_week() {
            if (!$this->is_valid()) return "";
            return intval(date("w", $this->value()));
        }

        function format($format) {
            if (!$this->is_valid()) return "";
            return date($format, $this->value());
        }

        function long() {
            if (!$this->is_valid()) return "";
            return date("l, F jS, Y", $this->value());      # eg: Saturday, March 20th, 2010
        }

        function short() {
            if (!$this->is_valid()) return "";
            return date("F jS, Y", $this->value());         # eg: March 20th, 2010
        }

        function ymd() {
            if (!$this->is_valid()) return "";
            return date("Y-m-d", $this->value());           # eg: 2010-03-17
        }

        function datetime() {
            if (!$this->is_valid()) return "";
            return date("Y-m-d H:i:s", $this->value());     # eg: 2010-03-20 17:27:17
        }

        function relative() {
            if (!$this->is_valid()) return "";
            # array of time period chunks
            $chunks = array(
                array(60 * 60 * 24 * 365 , 'year'),
                array(60 * 60 * 24 * 30 , 'month'),
                array(60 * 60 * 24 * 7, 'week'),
                array(60 * 60 * 24 , 'day'),
                array(60 * 60 , 'hour'),
                array(60 , 'minute'),
            );
            $chunk_count = count($chunks);

            $today = date_obj("now")->value();
            $diff = abs($today - $this->value());

            for ($i = 0; $i < $chunk_count; $i++) {
                $seconds = $chunks[$i][0];
                $name = $chunks[$i][1];

                # finding the biggest chunk (if the chunk fits, break)
                if (($count = floor($diff / $seconds)) != 0)
                    break;
            }
            $print = ($count == 1) ? "1 $name" : "$count {$name}s";

            if ($i + 1 < $chunk_count) {
                # now getting the second item
                $seconds2 = $chunks[$i + 1][0];
                $name2 = $chunks[$i + 1][1];

                # add second item if it's greater than 0
                if (($count2 = floor(($diff - ($seconds * $count)) / $seconds2)) != 0) {
                    $print .= ($count2 == 1) ? ", 1 $name2" : ", $count2 {$name2}s";
                }
            }
            return $print;
        }
    }
} else {
    class date_obj {
        var $_data       = null;
        var $date_string = null;
        var $date_value  = null;

        function __construct($date_string) {
            $this->update($date_string);
        }

        public function update($date_string) {
            $this->_data = new stdClass;
            if ("" == $date_string || "0000-00-00 00:00:00" == $date_string) {
                $this->date_string = "";
                $this->date_value  = false;
            } else {
                $this->date_string = $date_string;
            }
        }

        function __toString() {
            return $this->datetime; # not date_string
        }

        function is_valid() {
            return ($this->value !== false);
        }

        function &value() {
            if (null == $this->date_value) {
                $this->date_value = strtotime($this->date_string);
            }
            return $this->date_value;
        }

        function in_the_past() {
            if ($this->value) {
                return $this->value < time();
            }
            return null;
        }

        function in_the_future() {
            if ($this->value) {
                return $this->value > time();
            }
            return null;
        }

        function days_diff() {
            return abs(time() - $this->value()) / (60 * 60 * 24);
        }

        function now() {
            $new = date_obj(date("c", time()));
            $this->_data = $new->_data;
            $this->date_string = $new->date_string;
            $this->date_value = $new->date_value;
            return $this;
        }

        function adjust($interval) {
            $new = date_obj(date("c", strtotime($interval, $this->value)));
            $this->_data = $new->_data;
            $this->date_string = $new->date_string;
            $this->date_value = $new->date_value;
            return $this;
        }

        function __get($name) {
            if (property_exists($this->_data, $name))       # note isset() won't work with variable set to NULL
            {
                return $this->_data->{$name};
            } else {
                if (method_exists($this, $name)) {
                    $this->_data->{$name} = $this->{$name}();
                    return $this->_data->{$name};
                }
                # the error doesn't actually happen here, but wherever we're called from, so provide a helpful error
                $trace = debug_backtrace(false);
                $err_msg = get_class($this)." object does not have a {$name} value in {$trace[0]['file']} on line {$trace[0]['line']}.";
                throw new Exception($err_msg);
            }
        }

        function day_of_week() {
            if (!$this->is_valid()) return "";
            return intval(date("w", $this->value()));
        }

        function format($format) {
            if (!$this->is_valid()) return "";
            return date($format, $this->value());
        }

        function long() {
            if (!$this->is_valid()) return "";
            return date("l, F jS, Y", $this->value());      # eg: Saturday, March 20th, 2010
        }

        function short() {
            if (!$this->is_valid()) return "";
            return date("F jS, Y", $this->value());         # eg: March 20th, 2010
        }

        function date() # remove this as it collides with DateTime variable name
        {
            if (!$this->is_valid()) return "";
            return date("Y-m-d", $this->value());           # eg: 2010-03-17
        }

        function ymd() {
            if (!$this->is_valid()) return "";
            return date("Y-m-d", $this->value());           # eg: 2010-03-17
        }

        function datetime() {
            if (!$this->is_valid()) return "";
            return date("Y-m-d H:i:s", $this->value());     # eg: 2010-03-20 17:27:17
        }

        function relative() {
            if (!$this->is_valid()) return "";
            # array of time period chunks
            $chunks = array(
                array(60 * 60 * 24 * 365 , 'year'),
                array(60 * 60 * 24 * 30 , 'month'),
                array(60 * 60 * 24 * 7, 'week'),
                array(60 * 60 * 24 , 'day'),
                array(60 * 60 , 'hour'),
                array(60 , 'minute'),
            );
            $chunk_count = count($chunks);

            $today = time();
            $diff = abs($today - $this->value());

            for ($i = 0; $i < $chunk_count; $i++) {
                $seconds = $chunks[$i][0];
                $name = $chunks[$i][1];

                # finding the biggest chunk (if the chunk fits, break)
                if (($count = floor($diff / $seconds)) != 0)
                    break;
            }
            $print = ($count == 1) ? "1 $name" : "$count {$name}s";

            if ($i + 1 < $chunk_count) {
                # now getting the second item
                $seconds2 = $chunks[$i + 1][0];
                $name2 = $chunks[$i + 1][1];

                # add second item if it's greater than 0
                if (($count2 = floor(($diff - ($seconds * $count)) / $seconds2)) != 0) {
                    $print .= ($count2 == 1) ? ", 1 $name2" : ", $count2 {$name2}s";
                }
            }
            return $print;
        }
    }
}
