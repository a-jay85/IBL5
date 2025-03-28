<?php
/***************************************************************************
 *                                 mysql.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: mysql.php,v 1.16 2002/03/19 01:07:36 psotfx Exp $
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

if (!defined("SQL_LAYER")) {
    define("SQL_LAYER", "mysql");

    class MySQL
    {
        public $db_connect_id;
        public $query_result;
        public $row = array();
        public $rowset = array();
        public $num_queries = 0;

        //
        // Constructor
        //
        public function __construct($sqlserver, $sqluser, $sqlpassword, $database, $persistency = true)
        {
            $this->persistency = $persistency;
            $this->user = $sqluser;
            $this->password = $sqlpassword;
            $this->server = $sqlserver;
            $this->dbname = $database;

            if ($this->persistency) {
                $this->db_connect_id = @mysqli_pconnect($this->server, $this->user, $this->password);
            } else {
                $this->db_connect_id = @mysqli_connect($this->server, $this->user, $this->password);
            }
            if ($this->db_connect_id) {
                if ($database != "") {
                    $this->dbname = $database;
                    $dbselect = @mysqli_select_db($this->db_connect_id, $this->dbname);
                    if (!$dbselect) {
                        @mysqli_close($this->db_connect_id);
                        $this->db_connect_id = $dbselect;
                    }
                }
                return $this->db_connect_id;
            } else {
                return false;
            }
        }

        //
        // Other base methods
        //
        public function sql_close()
        {
            if ($this->db_connect_id) {
                if ($this->query_result) {
                    @mysqli_free_result($this->query_result);
                }
                $result = @mysqli_close($this->db_connect_id);
                return $result;
            } else {
                return false;
            }
        }

        //
        // Base query method
        //
        public function sql_query($query = "", $transaction = false)
        {
            // Remove any pre-existing queries
            unset($this->query_result);
            if ($query != "") {
                $this->query_result = @mysqli_query($this->db_connect_id, $query);
            }
            if ($this->query_result) {
                unset($this->row);
                unset($this->rowset);
                return $this->query_result;
            } else {
                return ($transaction == END_TRANSACTION) ? true : false;
            }
        }

        //
        // Other query methods
        //
        public function sql_numrows($query_id = 0)
        {
            if (!$query_id) {
                $query_id = $this->query_result;
            }
            if ($query_id) {
                $result = @mysqli_num_rows($query_id);
                return $result;
            } else {
                return false;
            }
        }

        public function sql_affectedrows()
        {
            if ($this->db_connect_id) {
                $result = @mysqli_affected_rows($this->db_connect_id);
                return $result;
            } else {
                return false;
            }
        }

        public function sql_numfields($query_id = 0)
        {
            if (!$query_id) {
                $query_id = $this->query_result;
            }
            if ($query_id) {
                $result = @mysqli_num_fields($query_id);
                return $result;
            } else {
                return false;
            }
        }

        public function sql_fieldname($offset, $query_id = 0)
        {
            if (!$query_id) {
                $query_id = $this->query_result;
            }
            if ($query_id) {
                $result = @mysqli_fetch_field_direct($query_id, $offset);
                return $result;
            } else {
                return false;
            }
        }

        public function sql_fieldtype($offset, $query_id = 0)
        {
            if (!$query_id) {
                $query_id = $this->query_result;
            }
            if ($query_id) {
                $result = @mysqli_fetch_field_direct($query_id, $offset);
                return $result;
            } else {
                return false;
            }
        }

        public function sql_fetchrow($query_id = 0)
        {
            if (!$query_id) {
                $query_id = $this->query_result;
            }
            if ($query_id) {
                // Original PHP-Nuke implementation method is commented out.
                // It has been simplified for PHP 7+ since mysqli_fetch_array always returns objects and not resources.
                // $this->row[$query_id] = @mysqli_fetch_array($query_id);
                // return $this->row[$query_id];
                return mysqli_fetch_array($query_id);
            } else {
                return false;
            }
        }

        public function sql_fetchrowset($query_id = 0)
        {
            if (!$query_id) {
                $query_id = $this->query_result;
            }
            if ($query_id) {
                unset($this->rowset[$query_id]);
                unset($this->row[$query_id]);
                while ($this->rowset[$query_id] = @mysqli_fetch_array($query_id)) {
                    $result[] = $this->rowset[$query_id];
                }
                return $result;
            } else {
                return false;
            }
        }

        public function sql_fetch_assoc($query_id = 0)
        {
            if (!$query_id) {
                $query_id = $this->query_result;
            }
            if ($query_id) {
                return mysqli_fetch_assoc($query_id);
            } else {
                return false;
            }
        }

        public function sql_fetchfield($field, $rownum = -1, $query_id = 0)
        {
            if (!$query_id) {
                $query_id = $this->query_result;
            }
            if ($query_id) {
                if ($rownum > -1) {
                    $result = @mysqli_result($query_id, $rownum, $field);
                } else {
                    if (empty($this->row[$query_id]) && empty($this->rowset[$query_id])) {
                        if ($this->sql_fetchrow()) {
                            $result = $this->row[$query_id][$field];
                        }
                    } else {
                        if ($this->rowset[$query_id]) {
                            $result = $this->rowset[$query_id][$field];
                        } elseif ($this->row[$query_id]) {
                            $result = $this->row[$query_id][$field];
                        }
                    }
                }
                return $result;
            } else {
                return false;
            }
        }

        // copy/pasted this function from the top comment of https://www.php.net/manual/en/class.mysqli-result.php
        public function sql_result($res, $row, $field = 0)
        {
            $res->data_seek($row);
            $datarow = $res->fetch_array();
            return $datarow[$field];
        }

        public function sql_rowseek($rownum, $query_id = 0)
        {
            if (!$query_id) {
                $query_id = $this->query_result;
            }
            if ($query_id) {
                $result = @mysqli_data_seek($query_id, $rownum);
                return $result;
            } else {
                return false;
            }
        }

        public function sql_nextid()
        {
            if ($this->db_connect_id) {
                $result = @mysqli_insert_id($this->db_connect_id);
                return $result;
            } else {
                return false;
            }
        }

        public function sql_freeresult($query_id = 0)
        {
            if (!$query_id) {
                $query_id = $this->query_result;
            }
            if ($query_id) {
                unset($this->row[$query_id]);
                unset($this->rowset[$query_id]);

                @mysqli_free_result($query_id);

                return true;
            } else {
                return false;
            }
        }

        public function sql_error($query_id = 0)
        {
            $result["message"] = @mysqli_error($this->db_connect_id);
            $result["code"] = @mysqli_errno($this->db_connect_id);

            return $result;
        }
    } // class sql_db
} // if ... define
