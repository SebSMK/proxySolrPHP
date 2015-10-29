<?php

require_once(dirname(__FILE__) . '/SolrPhpClient/Apache/Solr/Service.php');
require_once(dirname(__FILE__) . '/Foto2Solr/Foto2Solr.php');
error_reporting(E_ALL);

//$files = glob('c:\tmp\log\v2\other_vhosts_access.*', GLOB_BRACE);
// foreach($files as $file) {	
// 	set_time_limit(300);
// 	$g = new Foto2Solr('http://solr-02.smk.dk:8080/solr-h4dk/prod_collection', $file);
// 	$g->run();	
// }

 //$g = new Foto2Solr('http://csdev-seb:8180/solr-example/dev_DAM/', 'c:\tmp\fotostation\fileIIessai.csv');
 $g = new Foto2Solr('http://csdev-seb:8180/solr-example/dev_DAM/', 'c:\tmp\fotostation\fotostation_extr.csv');
 $g->flush();
 $g->run();


?>