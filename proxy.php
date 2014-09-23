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
require_once(dirname(__FILE__) . '/Proxy2Solr/Proxy2Solr.php');


$get = array();
if (isset($_GET['solrUrl']))
	$get['solrUrl'] = $_GET['solrUrl'];
if (isset($_GET['query']))
	$get['query'] = $_GET['query'];
if (isset($_GET['prev_query']))
	$get['prev_query'] = $_GET['prev_query'];
if (isset($_GET['language']))
	$get['language'] = $_GET['language'];


//* extract parameters from query
$processor = new processRequest($get); 

//* save query's parameters to stats ('dictionary' and 'pictures')
$processor->save();

//* return response to query
echo $_GET['callback']. '('. $processor->getResponse()->getRawResponse() . ')';


/**
 * Executes the Solr query and returns the JSON response.
 */
class processRequest {
	private $get;
	private $solr_url;
	private $query;
	private $prev_query;	
	private $picture_url;
	private $numfound;
	private $language;
	private $response2Query;
	private $data2Save;
	private $comm2Solr;

	public function __construct($get) {
		$this->get = $get;				
		
		if (isset($this->get['solrUrl'])) {
			$this->comm2Solr = new comm2Solr($this->get['solrUrl']);
		}
		
		$this->proxy2Solr();
		
		$this->build_response2Query();
		$this->build_data2Save();
	}

	public function save() {
		if (isset($this->comm2Solr) && isset($this->data2Save)){
			$this->comm2Solr->save($this->data2Save);
		}
	}

	public function getResponse(){
		return $this->response2Query;		
	}
	
	
	private function build_response2Query(){
		if (isset($this->comm2Solr) && isset($this->query)){						
			$this->response2Query = $this->comm2Solr->getResponse($this->query);						
			$this->numfound = $this->response2Query->response->numFound;			
			
			foreach ($this->response2Query->response->docs as $doc)
			{
				foreach ($doc as $field => $value)
				{
					if ($field == "medium_image_url"){
						$this->picture_url = $value;
						break;
					}			
				}
			}			
		}
	}
		
	private function build_data2Save(){
		$data2Save = array();
		$artwork = "id_s";
		$matches = array_filter($this->query->get_q(), function($var) use ($artwork) {
			return preg_match("/\b$artwork\b/i", $var);
		});
	
		$data2Save['id'] = uniqid();
		$data2Save['ip'] = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ?  $_SERVER['REMOTE_ADDR'] + "-" + $_SERVER['HTTP_X_FORWARDED_FOR'] :  $_SERVER['REMOTE_ADDR'];
		$data2Save['last_update'] = gmdate('Y-m-d\TH:i:s\Z', strtotime("now"));
			
		if($this->isValidData($this->numfound))
			$data2Save['numfound'] = $this->numfound;	
		
		if (isset($this->get['language']))
			$data2Save['language'] = $this->get['language'];
		
		if (count($matches) > 0 ){
			// the user called for detailed view of an artwork
			if (count($this->query->get_q()) > 0)
				$data2Save['q'] = $this->query->get_q();
			
			if (count($this->prev_query->get_q()) > 0)
				$data2Save['prev_q'] = $this->prev_query->get_q();
	
			if (count($this->prev_query->get_fq()) > 0)
				$data2Save['prev_fq'] = $this->prev_query->get_fq();
			
			if($this->isValidData($this->picture_url))
				$data2Save['picture_url'] = $this->picture_url;
	
		}else{
			// proceed only if 'start' param was null ('start' is set when the user uses pagination in website, and we want to avoid duplication on search string)			
			$start = $this->query->get_start();
			if (!$this->isValidData($start)){
				if (count($this->query->get_q()) > 0)
					$data2Save['q'] = $this->query->get_q();
				
				if (count($this->query->get_fq()) > 0)
					$data2Save['fq'] = $this->query->get_fq();
			}
		}		
		$this->data2Save = $data2Save;		
	}

	private function isValidData($data){
		if (isset($data)){
			if(is_array($data))
				return count($data) > 0 ? true : false;
			if(is_string($data))
				return $data != '' ? true : false;
			if(is_integer($data))
				return true;
		}	
		return false;	
	}
	
	private function proxy2Solr() {												
		$query_reader = new Proxy2Solr($this->get);
		$this->query = $query_reader->get_query();
		$this->prev_query = $query_reader->get_prev_query();										
 	
	}			
}


class comm2Solr{

	private $solr_search_pict;
	private $solr_search_dict;
	private $solr_collectionspace;
	
	public function __construct($solr_url) {
		$spliturl = parse_url($solr_url);
		$host = $spliturl['host'] == 'solr.smk.dk' ? 'solr-02.smk.dk' : $spliturl['host'];
		$port = $spliturl['host'] == 'solr.smk.dk' ? '8080' : $spliturl['port'];
		$core_pict = $spliturl['host'] == 'solr.smk.dk' ? 'prod_search_pict' : 'preprod_search_pict';
		$core_dict = $spliturl['host'] == 'solr.smk.dk' ? 'prod_search_dict' : 'preprod_search_dict';
		$path = explode("/", trim($spliturl['path'], '/')) ;
		$path_pict = 'solr-stats';
		$path_dict = 'solr-stats';
		$core = array_pop($path);
		$path = implode("/", $path);
		
		$this->solr_search_pict = new Apache_Solr_Service($host, $port, '/' . $path_pict . '/' . $core_pict . '/' );
		$this->solr_search_dict = new Apache_Solr_Service($host, $port, '/' . $path_dict . '/' . $core_dict . '/' );
		$this->solr_collectionspace = new Apache_Solr_Service($host, $port, '/' . $path . '/' . $core . '/' );
	}
	
	public function getResponse($query){
		try {
			if(isset($this->solr_collectionspace)){
				$params = $query->get_params();			
				$keys = $query->get_keys();						
				$start = isset($params['start']) ? $params['start'] : null;
				$rows = isset($params['rows']) ? $params['rows'] : null;
				return $this->solr_collectionspace->search($keys, $start , $rows, $params);				
			}
		}
		catch (Exception $e) {
			//die($e->__toString());
			return ($e->__toString());
		}				
	}
	
	public function save($data2Save){
		try {						
			if(isset($data2Save['picture_url'])){
				//* picture query
				if(isset($this->solr_search_pict)){
					$saver = new save_to_Solr();
					$saver->server_setup($this->solr_search_pict);
					$datas[] = $data2Save;
					$saver->save($datas);
					//$saver->flush_solr(); !!!!!!!!!!!!!!!!!!!!!!!! DELETE THE WHOLE SOLR!!!!!!!!!!!!!!!
				}	
				
			}else{
				//* word query
				if(isset($this->solr_search_dict)){
					$saver = new save_to_Solr();
					$saver->server_setup($this->solr_search_dict);
					$datas[] = $data2Save;
					$saver->save($datas);
					//$saver->flush_solr(); !!!!!!!!!!!!!!!!!!!!!!!! DELETE THE WHOLE SOLR!!!!!!!!!!!!!!!
				}								
			}						
		}
		catch (Exception $e) {
			//die($e->__toString());
			return ($e->__toString());
		}
	}
}

?>