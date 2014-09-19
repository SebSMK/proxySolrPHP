<?php

require_once(dirname(__FILE__) . '/SolrPhpClient/Apache/Solr/Service.php');
require_once(dirname(__FILE__) . '/Log2Solr/Pic2Solr.php');
error_reporting(E_ALL);

// $files = glob('c:\tmp\log\other_vhosts_access.*', GLOB_BRACE);
// foreach($files as $file) {
// 	set_time_limit(300);
// 	$g = new Pic2Solr('http://csdev-seb:8180/solr-example/dev_search_log/', $file);
// 	$g->run();	
// }

    $g = new Pic2Solr('http://csdev-seb:8180/solr-example/dev_search_log/', 'c:\tmp\log\essai_pic.log');
   $g->run();
//$g->flush();

?>