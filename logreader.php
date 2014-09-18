<?php

require_once(dirname(__FILE__) . '/SolrPhpClient/Apache/Solr/Service.php');
require_once(dirname(__FILE__) . '/Log2Solr/Log2Solr.php');
error_reporting(E_ALL);

$files = glob('c:\tmp\log\other_vhosts_access.*', GLOB_BRACE);
foreach($files as $file) {
	$g = new Log2Solr('http://csdev-seb:8180/solr-example/dev_search_log/', $file);
	$g->run();	
}

   $g = new Log2Solr('http://csdev-seb:8180/solr-example/dev_search_log/', 'c:\tmp\log\essai.log');
//  $g->run();
//  $g->flush();

// $lines = array();
// $extractor = new solr_log_extractor();
// $extractor->set_file('c:\tmp\log\other_vhosts_access.log');
//$saver = new save_to_Solr();
// $saver->server_setup('http://csdev-seb:8180/solr-example/dev_search_log/');

// $saver->save($extractor->readfile());
//$saver->flush_solr();
//var_dump($saver->save($extractor->readfile()));


// $files = glob('c:\tmp\log\other_vhosts_access.*', GLOB_BRACE);
// foreach($files as $file) {
// 	var_dump($file);
// }

//printf('<pre>%s</pre>', print_r($extractor->readfile(), true));

?>