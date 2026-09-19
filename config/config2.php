<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'u8340326_learning';
$CFG->dbuser    = 'u8340326_langgam';
$CFG->dbpass    = '$.o_!5k4qJw&';
$CFG->prefix    = 'mdlij_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '',
  'dbsocket' => '',
  'dbcollation' => 'utf8_general_ci',
);

$CFG->wwwroot   = 'http://airjernihpku.com/elearning';
$CFG->dataroot  = '/home/\\elearning';
$CFG->admin     = 'admin';

//$CFG->wwwroot   = 'https://moodle.airjernihpku.com';
//$CFG->dataroot  = '/home/u8340326/moodledata';
//$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!


