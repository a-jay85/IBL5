<?php

declare(strict_types=1);

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

    /**
     * @deprecated This class is for PHP-Nuke module backward compatibility only.
     *
     * All IBL5 classes in /ibl5/classes/ and scripts in /ibl5/scripts/ have been
     * migrated to modern mysqli with prepared statements via BaseMysqliRepository.
     *
     * This class is ONLY used by legacy PHP-Nuke code in:
     * - /ibl5/modules/
     * - /ibl5/admin/modules/
     * - /ibl5/blocks/
     *
     * DO NOT use this class for new code. Instead:
     * - Extend BaseMysqliRepository for repository classes
     * - Use $mysqli_db global with prepared statements
     *
     * Example:
     *   $stmt = $mysqli_db->prepare("SELECT * FROM table WHERE id = ?");
     *   $stmt->bind_param("i", $id);
     *   $stmt->execute();
     *   $result = $stmt->get_result();
     *
     * Scheduled for removal once PHP-Nuke modules are deprecated.
     *
     * @see \BaseMysqliRepository
     */
    class MySQL
    {
        public \mysqli|false $db_connect_id = false;
        public \mysqli_result|bool|null $query_result = null;
        /** @var array<int|string, array<int|string, mixed>> */
        public array $row = [];
        /** @var array<int|string, array<int|string, mixed>> */
        public array $rowset = [];
        public int $num_queries = 0;
        public bool $persistency = true;
        public string $user = '';
        public string $password = '';
        public string $server = '';
        public string $dbname = '';

        //
        // Constructor
        //
        public function __construct(string $sqlserver, string $sqluser, string $sqlpassword, string $database, bool $persistency = true)
        {
            $this->persistency = $persistency;
            $this->user = $sqluser;
            $this->password = $sqlpassword;
            $this->server = $sqlserver;
            $this->dbname = $database;

            if (!$this->persistency) {
                $this->db_connect_id = @mysqli_connect($this->server, $this->user, $this->password);
            }
            if ($this->db_connect_id instanceof \mysqli) {
                if ($database !== "") {
                    $this->dbname = $database;
                    $dbselect = @mysqli_select_db($this->db_connect_id, $this->dbname);
                    if (!$dbselect) {
                        @mysqli_close($this->db_connect_id);
                        $this->db_connect_id = false;
                    }
                }
                if ($this->db_connect_id instanceof \mysqli) {
                    // Set character set to UTF-8 to support accent marks and special characters
                    @mysqli_set_charset($this->db_connect_id, 'utf8mb4');
                }
            }
        }

        //
        // Other base methods
        //
        public function sql_close(): bool
        {
            if ($this->db_connect_id instanceof \mysqli) {
                if ($this->query_result instanceof \mysqli_result) {
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
        /** @param int|bool $transaction */
        public function sql_query(string $query = "", int|bool $transaction = false): \mysqli_result|bool
        {
            // Remove any pre-existing queries
            unset($this->query_result);
            if ($query !== "" && $this->db_connect_id instanceof \mysqli) {
                $this->query_result = @mysqli_query($this->db_connect_id, $query);
            }
            if ($this->query_result instanceof \mysqli_result) {
                $this->row = [];
                $this->rowset = [];
                return $this->query_result;
            } else {
                return $transaction === END_TRANSACTION;
            }
        }

        //
        // Other query methods
        //
        /** @param \mysqli_result|int $query_id */
        public function sql_numrows(\mysqli_result|int $query_id = 0): int|false
        {
            if ($query_id === 0) {
                $query_id = $this->query_result;
            }
            if ($query_id instanceof \mysqli_result) {
                return (int) @mysqli_num_rows($query_id);
            } else {
                return false;
            }
        }

        public function sql_affectedrows(): int|false
        {
            if ($this->db_connect_id instanceof \mysqli) {
                return (int) @mysqli_affected_rows($this->db_connect_id);
            } else {
                return false;
            }
        }

        /** @param \mysqli_result|int $query_id */
        public function sql_numfields(\mysqli_result|int $query_id = 0): int|false
        {
            if ($query_id === 0) {
                $query_id = $this->query_result;
            }
            if ($query_id instanceof \mysqli_result) {
                $result = @mysqli_num_fields($query_id);
                return $result;
            } else {
                return false;
            }
        }

        /** @param \mysqli_result|int $query_id */
        public function sql_fieldname(int $offset, \mysqli_result|int $query_id = 0): \stdClass|false
        {
            if ($query_id === 0) {
                $query_id = $this->query_result;
            }
            if ($query_id instanceof \mysqli_result) {
                $result = @mysqli_fetch_field_direct($query_id, $offset);
                return $result;
            } else {
                return false;
            }
        }

        /** @param \mysqli_result|int $query_id */
        public function sql_fieldtype(int $offset, \mysqli_result|int $query_id = 0): \stdClass|false
        {
            if ($query_id === 0) {
                $query_id = $this->query_result;
            }
            if ($query_id instanceof \mysqli_result) {
                $result = @mysqli_fetch_field_direct($query_id, $offset);
                return $result;
            } else {
                return false;
            }
        }

        /**
         * @param \mysqli_result|array<mixed>|int|bool $query_id
         * @return array<mixed>|false|null
         */
        public function sql_fetchrow(\mysqli_result|array|int|bool $query_id = 0): array|false|null
        {
            if ($query_id === 0) {
                $query_id = $this->query_result;
            }
            if ($query_id !== null && $query_id !== false) {
                // If $query_id is already an array (from refactored methods), return it directly
                if (is_array($query_id)) {
                    return $query_id;
                }
                if ($query_id instanceof \mysqli_result) {
                    return mysqli_fetch_array($query_id);
                }
                return false;
            } else {
                return false;
            }
        }

        /**
         * @param \mysqli_result|array<mixed>|int|bool $query_id
         * @return array<int, array<mixed>>|false
         */
        public function sql_fetchrowset(\mysqli_result|array|int|bool $query_id = 0): array|false
        {
            if ($query_id === 0) {
                $query_id = $this->query_result;
            }
            if ($query_id !== null && $query_id !== false) {
                // If $query_id is already an array (from refactored methods), wrap it in result array
                if (is_array($query_id)) {
                    return [$query_id];
                }
                if ($query_id instanceof \mysqli_result) {
                    /** @var list<array<mixed>> $result */
                    $result = [];
                    while (($row = @mysqli_fetch_array($query_id)) !== null) {
                        if (is_array($row)) {
                            $result[] = $row;
                        }
                    }
                    return $result;
                }
                return false;
            } else {
                return false;
            }
        }

        /**
         * @param \mysqli_result|array<string, mixed>|int|bool $query_id
         * @return array<string, mixed>|false|null
         */
        public function sql_fetch_assoc(\mysqli_result|array|int|bool $query_id = 0): array|false|null
        {
            if ($query_id === 0) {
                $query_id = $this->query_result;
            }
            if ($query_id !== null && $query_id !== false) {
                // If $query_id is already an array (from refactored methods), return it directly
                if (is_array($query_id)) {
                    return $query_id;
                }
                if ($query_id instanceof \mysqli_result) {
                    return mysqli_fetch_assoc($query_id);
                }
                return false;
            } else {
                return false;
            }
        }

        // copy/pasted this function from the top comment of https://www.php.net/manual/en/class.mysqli-result.php
        public function sql_result(\mysqli_result $res, int $row, int|string $field = 0): mixed
        {
            $res->data_seek($row);
            $datarow = $res->fetch_array();
            if (!is_array($datarow)) {
                return false;
            }
            return $datarow[$field];
        }

        /** @param \mysqli_result|int $query_id */
        public function sql_rowseek(int $rownum, \mysqli_result|int $query_id = 0): bool
        {
            if ($query_id === 0) {
                $query_id = $this->query_result;
            }
            if ($query_id instanceof \mysqli_result) {
                $result = @mysqli_data_seek($query_id, $rownum);
                return $result;
            } else {
                return false;
            }
        }

        public function sql_nextid(): int|string|false
        {
            if ($this->db_connect_id instanceof \mysqli) {
                $result = @mysqli_insert_id($this->db_connect_id);
                return $result;
            } else {
                return false;
            }
        }

        /** @param \mysqli_result|int $query_id */
        public function sql_freeresult(\mysqli_result|int $query_id = 0): bool
        {
            if ($query_id === 0) {
                $query_id = $this->query_result;
            }
            if ($query_id instanceof \mysqli_result) {
                @mysqli_free_result($query_id);
                return true;
            } else {
                return false;
            }
        }

        /** @return array{message: string, code: int} */
        public function sql_error(int $query_id = 0): array
        {
            $result = [
                'message' => $this->db_connect_id instanceof \mysqli ? @mysqli_error($this->db_connect_id) : '',
                'code' => $this->db_connect_id instanceof \mysqli ? @mysqli_errno($this->db_connect_id) : 0,
            ];

            return $result;
        }
    } // class sql_db
} // if ... define
