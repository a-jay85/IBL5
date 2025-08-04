<?php
/***************************************************************************
 *                                 sqlite.php
 *                            -------------------
 *   Conversion from MySQL adapter to SQLite3 adapter
 ***************************************************************************/

if (!defined("SQL_LAYER")) {
    define("SQL_LAYER", "sqlite");

    class SQLite
    {
        public $db_connect_id;
        public $query_result;
        public $row = array();
        public $rowset = array();
        public $num_queries = 0;

        // Constructor: only needs the database file path
        public function __construct($database_file)
        {
            $this->db_connect_id = new SQLite3($database_file);
            return $this->db_connect_id ? $this->db_connect_id : false;
        }

        // Close connection
        public function sql_close()
        {
            if ($this->db_connect_id) {
                $this->db_connect_id->close();
                return true;
            }
            return false;
        }

        // Query
        public function sql_query($query = "", $transaction = false)
        {
            unset($this->query_result);
            if ($query != "") {
                $this->query_result = $this->db_connect_id->query($query);
            }
            return $this->query_result ? $this->query_result : false;
        }

        // Number of rows (must iterate, SQLite3 doesn't provide num_rows)
        public function sql_numrows($query_id = null)
        {
            if (!$query_id) $query_id = $this->query_result;
            if ($query_id) {
                $count = 0;
                while ($query_id->fetchArray(SQLITE3_ASSOC)) $count++;
                $query_id->reset();
                return $count;
            }
            return false;
        }

        // Number of affected rows (for write queries)
        public function sql_affectedrows()
        {
            return $this->db_connect_id ? $this->db_connect_id->changes() : false;
        }

        // Number of fields (columns)
        public function sql_numfields($query_id = null)
        {
            if (!$query_id) $query_id = $this->query_result;
            if ($query_id) {
                $cols = $query_id->numColumns();
                return $cols;
            }
            return false;
        }

        // Field name by offset
        public function sql_fieldname($offset, $query_id = null)
        {
            if (!$query_id) $query_id = $this->query_result;
            if ($query_id) {
                return $query_id->columnName($offset);
            }
            return false;
        }

        // Field type by offset (SQLite3 only returns type affinity as string)
        public function sql_fieldtype($offset, $query_id = null)
        {
            // SQLite3 does not provide direct field type info, so return 'unknown'
            return 'unknown';
        }

        // Fetch a single row (assoc)
        public function sql_fetchrow($query_id = null)
        {
            if (!$query_id) $query_id = $this->query_result;
            return $query_id ? $query_id->fetchArray(SQLITE3_ASSOC) : false;
        }

        // Fetch all rows
        public function sql_fetchrowset($query_id = null)
        {
            if (!$query_id) $query_id = $this->query_result;
            $result = [];
            if ($query_id) {
                while ($row = $query_id->fetchArray(SQLITE3_ASSOC)) {
                    $result[] = $row;
                }
                return $result;
            }
            return false;
        }

        // Fetch assoc (same as fetchrow for SQLite3)
        public function sql_fetch_assoc($query_id = null)
        {
            return $this->sql_fetchrow($query_id);
        }

        // Fetch field value from a row
        public function sql_fetchfield($field, $rownum = -1, $query_id = null)
        {
            if (!$query_id) $query_id = $this->query_result;
            if ($query_id) {
                if ($rownum > -1) {
                    $query_id->reset();
                    for ($i = 0; $i <= $rownum; $i++) {
                        $row = $query_id->fetchArray(SQLITE3_ASSOC);
                        if ($i == $rownum) {
                            return $row[$field] ?? false;
                        }
                    }
                } else {
                    $row = $query_id->fetchArray(SQLITE3_ASSOC);
                    return $row[$field] ?? false;
                }
            }
            return false;
        }

        // Get value from a specific row/field (seek then fetch)
        public function sql_result($result, $row = 0, $field = '')
        {
            if (!$result || !($result instanceof SQLite3Result)) {
                return false;
            }

            // Reset result set to beginning
            $result->reset();

            // Skip to desired row
            for ($i = 0; $i < $row; $i++) {
                if (!$result->fetchArray()) {
                    return false;
                }
            }

            $row_data = $result->fetchArray(SQLITE3_ASSOC);
            if (!$row_data) {
                return false;
            }

            // If no specific field requested, return entire row
            if ($field === '') {
                return $row_data;
            }

            // Return specific field if it exists
            return isset($row_data[$field]) ? $row_data[$field] : false;
        }

        // Seek to row (not supported in SQLite3, so use reset and fetch)
        public function sql_rowseek($rownum, $query_id = null)
        {
            if (!$query_id) $query_id = $this->query_result;
            if ($query_id) {
                $query_id->reset();
                for ($i = 0; $i < $rownum; $i++) {
                    $query_id->fetchArray(SQLITE3_ASSOC);
                }
                return true;
            }
            return false;
        }

        // Last inserted row ID
        public function sql_nextid()
        {
            return $this->db_connect_id ? $this->db_connect_id->lastInsertRowID() : false;
        }

        // Free result (no-op for SQLite3, but unset for compatibility)
        public function sql_freeresult($result)
        {
            if ($result instanceof SQLite3Result) {
                $result->finalize();
                return true;
            }
            return false;
        }

        // Error info
        public function sql_error($query_id = null)
        {
            $result["message"] = $this->db_connect_id ? $this->db_connect_id->lastErrorMsg() : "";
            $result["code"] = $this->db_connect_id ? $this->db_connect_id->lastErrorCode() : 0;
            return $result;
        }
    } // class SQLite
} // if ... define