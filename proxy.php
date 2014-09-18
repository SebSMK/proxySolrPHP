<?php

/**
 * @file
 * Implements a Solr proxy.
 *
 * Currently requires json_decode which is bundled with PHP >= 5.2.0.
 *
 * You must download the SolrPhpClient and store it in the same directory as this file.
 *
 *   http://code.google.com/p/solr-php-client/
 */

require_once(dirname(__FILE__) . '/SolrPhpClient/Apache/Solr/Service.php');

solr_proxy_main();

/**
 * Executes the Solr query and returns the JSON response.
 */
function solr_proxy_main() {

	if (isset($_GET['solrUrl'])){

		$spliturl = parse_url($_GET['solrUrl']);
		$host = $spliturl['host'] == 'solr.smk.dk' ? 'solr-02.smk.dk' : $spliturl['host'];
		$port = $spliturl['host'] == 'solr.smk.dk' ? '8080' : $spliturl['port'];								
		$core_log = $spliturl['host'] == 'solr.smk.dk' ? 'prod_search_log' : 'preprod_search_log';
		
		$path = explode("/", trim($spliturl['path'], '/')) ;
		
		$core = array_pop($path);
		$path = implode("/", $path);
		
							
		
		$solr = new Apache_Solr_Service($host, $port, '/' . $path . '/' . $core . '/' );
		//$solr = new Apache_Solr_Service('csdev-seb', 8180, '/solr-example/preprod_all_dk/');
		//var_dump($solr);		
				
		$solr_search_log = new Apache_Solr_Service($host, $port, '/' . $path . '/' . $core_log . '/' );
		//$solr_search_log = new Apache_Solr_Service('solr-02.smk.dk', 8080, '/solr/prod_search_log/');	
		//var_dump($solr_search_log);
		
		$document = new Apache_Solr_Document();
		$q_default = "-(id_s:(*/*) AND category:collections) -(id_s:(*verso) AND category:collections)";
		$fq_tag = "tag";
		$fq_prev = array();
		$q_prev = array();
		$picture_url = '';
		$numfound = 0;
		
		if (isset($_GET['prev_query'])) {
			$params = array();			
			$params['q'] = '*:*';
			$keys = '';
			$core = '';
		
			//error_log($_GET['query']);
		
			// The names of Solr parameters that may be specified multiple times.
			$multivalue_keys = array('bf', 'bq', 'facet.date', 'facet.date.other', 'facet.field', 'facet.query', 'fq', 'pf', 'qf');
		
			$pairs = explode('&', $_GET['prev_query']);
		
			foreach ($pairs as $pair) {
				if ($pair != ''){
					list($key, $value) = explode('=', $pair, 2);
					$value = urldecode($value);
					if (in_array($key, $multivalue_keys)) {
						$params[$key][] = $value;
					}
					elseif ($key == 'q') {
						//error_log($value);
						$keys = $value;
					}
					elseif ($key == 'core') {
						$core = "$value/";
					}
					else {
						$params[$key] = $value;
					}						
				}				
			}				
		
			// 		try {
			// 			$response = $solr->search($keys, $params['start'], $params['rows'], $params);
			// 		}
			// 		catch (Exception $e) {
			// 			die($e->__toString());
			// 		}
		
			//error_log($response->getRawResponse());
			//print $response->getRawResponse();
		
			/*ררררררררר*/

			$fq = array();
			$q = array();

			// proceed only if 'start' param was null ('start' is set when the user uses pagination in website, and we want to avoid duplication on search string)
			if(!isset($params['start'])){
				// process q
				if($keys <> ''){
					$q = explode(",", $keys);
					// remove default 'q' value
					if(($key = array_search($q_default, $q)) !== false) {
						unset($q[$key]);
					}
					array_filter($q);
				};
				
				// process fq
				if(isset($params['fq'])){
					$fq = $params['fq'];
					// remove 'tag' facet
					$matches = array_filter($fq, function($var) use ($fq_tag) {
						return preg_match("/\b$fq_tag\b/i", $var);
					});
					foreach ($matches as $key => $value){
						unset($fq[$key]);
					};
					array_filter($fq);
				};
				
				if((count($q) + count($fq)) > 0){
					$fq_prev = $fq;
					$q_prev = $q;
				}								
			}
		}
		
		if (isset($_GET['query'])) {
			$params = array();
			$params['q'] = '*:*';
			$keys = '';
			$core = '';
			 
			//error_log($_GET['query']);
			 
			// The names of Solr parameters that may be specified multiple times.
			$multivalue_keys = array('bf', 'bq', 'facet.date', 'facet.date.other', 'facet.field', 'facet.query', 'fq', 'pf', 'qf');
		
			$pairs = explode('&', $_GET['query']);
			 
			foreach ($pairs as $pair) {
				list($key, $value) = explode('=', $pair, 2);
				$value = urldecode($value);
				if (in_array($key, $multivalue_keys)) {
					$params[$key][] = $value;
				}
				elseif ($key == 'q') {
					//error_log($value);
					$keys = $value;
				}
				elseif ($key == 'core') {
					$core = "$value/";
				}
				else {
					$params[$key] = $value;
				}
			}
			 
			try {
				$response = $solr->search($keys, $params['start'], $params['rows'], $params);
				//var_dump($response);
				
				$numfound = $response->response->numFound;
				
				foreach ($response->response->docs as $doc)
				{
					foreach ($doc as $field => $value)
					{
						if ($field == "medium_image_url"){
							$picture_url = $value;
							break;
						}
				
					}
				}
			}
			catch (Exception $e) {
				die($e->__toString());
			}
		
			//error_log($response->getRawResponse());
			//print $response->getRawResponse();
			 
			/*ררררררררר*/
			$fq = array();
			$q = array();

			// proceed only if 'start' param was null ('start' is set when the user uses pagination in website, and we want to avoid duplication on search string)
			if(!isset($params['start'])){
				// process q
				if($keys <> ''){
					$q = explode(",", $keys);
					// remove default 'q' value
					if(($key = array_search($q_default, $q)) !== false) {
						unset($q[$key]);
					}
					array_filter($q);
				};
				
				// process fq
				if(isset($params['fq'])){
					$fq = $params['fq'];
					// remove 'tag' facet
					$matches = array_filter($fq, function($var) use ($fq_tag) {
						return preg_match("/\b$fq_tag\b/i", $var);
					});
					foreach ($matches as $key => $value){
						unset($fq[$key]);
					};
					array_filter($fq);
				};
				
				if((count($q) + count($fq)) > 0){
					//$solr_search_log = new Apache_Solr_Service('csdev-seb', 8180, '/solr-example/dev_search_log/' . $core);
				
					//$document = new Apache_Solr_Document();
					$document->id = uniqid(); //or something else suitably unique
				
					$document->q = $q;
					$document->facet = $fq;
				
					$document->ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ?  $_SERVER['REMOTE_ADDR'] + "-" + $_SERVER['HTTP_X_FORWARDED_FOR'] :  $_SERVER['REMOTE_ADDR'];
					$document->last_update = gmdate('Y-m-d\TH:i:s\Z', strtotime("now"));
				
					$document->numfound = $numfound;
				
					// user called for detailed view of an artwork?
					$artwork = "id_s";
					$matches = array_filter($q, function($var) use ($artwork) {
						return preg_match("/\b$artwork\b/i", $var);
					});
					if (count($matches) > 0 ){
				
						if (count($q_prev) > 0)
							$document->prev_q = $q_prev;
							
						if (count($fq_prev) > 0)
							$document->prev_facet = $fq_prev;
							
						if($picture_url != '')
							$document->picture_url = $picture_url;
					}
				
				
					$solr_search_log->addDocument($document); 	//if you're going to be adding documents in bulk using addDocuments with an array of documents is faster
					//$solr_search_log->deleteByQuery('*:*');
					$solr_search_log->commit();
				
					//echo 'ok';
				}
			}						
		
			/*ררררררררררר*/
		
			echo $_GET['callback']. '('. $response->getRawResponse() . ')';
		
		}					
	}	 
}
?>