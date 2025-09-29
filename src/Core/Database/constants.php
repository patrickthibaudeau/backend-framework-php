<?php

/**
 * Database constants - Moodle compatibility
 */

// Record existence strictness levels
define('IGNORE_MISSING', 0);  // Don't throw error if record doesn't exist
define('MUST_EXIST', 1);      // Throw error if record doesn't exist

// Transaction isolation levels
define('READ_UNCOMMITTED', 1);
define('READ_COMMITTED', 2);
define('REPEATABLE_READ', 3);
define('SERIALIZABLE', 4);

// SQL comparison operators for advanced queries
define('SQL_COMPARE_EQUAL', '=');
define('SQL_COMPARE_NOT_EQUAL', '!=');
define('SQL_COMPARE_GREATER', '>');
define('SQL_COMPARE_GREATER_EQUAL', '>=');
define('SQL_COMPARE_LESS', '<');
define('SQL_COMPARE_LESS_EQUAL', '<=');
define('SQL_COMPARE_LIKE', 'LIKE');
define('SQL_COMPARE_NOT_LIKE', 'NOT LIKE');

// SQL parameter types
define('SQL_PARAMS_NAMED', 'named');
define('SQL_PARAMS_QM', 'qm');
define('SQL_PARAMS_DOLLAR', 'dollar');

// Database field types
define('XMLDB_TYPE_INTEGER', 'I');
define('XMLDB_TYPE_NUMBER', 'N');
define('XMLDB_TYPE_FLOAT', 'F');
define('XMLDB_TYPE_CHAR', 'C');
define('XMLDB_TYPE_TEXT', 'X');
define('XMLDB_TYPE_BINARY', 'B');
define('XMLDB_TYPE_TIMESTAMP', 'T');
define('XMLDB_TYPE_DATETIME', 'D');
