<?php

//require_once(dirname(__FILE__) . '/Log2Solr.php');

class Proxy2Solr {	
	private $get;
	private $query;
	private $prev_query;	

	public function __construct($get) {		
		$this->get = $get;
		
		if (isset($this->get['query'])) {			
			$this->query = $this->extract_params($this->get['query']);								
		}
		
		if (isset($this->get['prev_query'])) {
			$this->prev_query = $this->extract_params($this->get['prev_query']);			
		}
					
	}

	private function extract_params($query) {	
		return new solr_request_extractor($query);				
	}				
		
	private function isValidData($data){
		if (isset($data)){
			if(is_array($data))
				return count($data) > 0 ? true : false;
			if(is_string($data))
				return $data != '' ? true : false;
		}
	
		return false;
	
	}
	
	
	public function get_query(){
		return $this->query;
	}

	
	public function get_prev_query(){
		return $this->prev_query;
	}
	
}


/*
 * Extract parameters from a Solr request
* **/
class solr_request_extractor{

	private $keys = '';	
	private $req = '';
	private $params = array();
	private $q = array();
	private $fq = array();
	private $start = '';

	public function __construct($req) {		
		if(isset($req)){
			$this->req = $req;
			$this->extract_params();
		}		
	}	

	private function extract_params(){

		if($this->req != ''){
			$q_default = "-(id_s:(*/*) AND category:collections) -(id_s:(*verso) AND category:collections)";
			$fq_tag = "tag";
			$picture_url = '';
			$fq = array();
			$q = array();			
			$params = array();
			$keys = '';
			$core = '';
				
			// The names of Solr parameters that may be specified multiple times.
			$multivalue_keys = array('bf', 'bq', 'facet.date', 'facet.date.other', 'facet.field', 'facet.query', 'fq', 'pf', 'qf');

			$pairs = explode('&', $this->req);			
			
			foreach ($pairs as $pair) {
				if ($pair != ''){
					list($key, $value) = explode('=', $pair, 2);
					//$key = strtok($pair, "=");
					//$value = strtok("=");
						
					$value = urldecode($value);
					if (in_array($key, $multivalue_keys)) {
						$params[$key][] = $value;
					}
					elseif ($key == 'q') {
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
			
			$this->set_keys($keys);
			$this->set_params($params);
						
			// process q
			if($keys <> ''){
				$q = explode(",", $keys);
				// remove default 'q' value
				if(($key = array_search($q_default, $q)) !== false) {
					unset($q[$key]);
				}
				array_filter($q);
				$this->set_q($q);
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

				if (count($fq) > 0)
					$this->set_fq($fq);
			};
			
			// process start
			if(isset($params['start']) && $params['start'] != ''){
				$this->set_start($params['start']);
			};
									
		}
	}
	
	private function set_keys($keys){
		if(isset($keys))
			$this->keys= $keys;
	}
	
	public function get_keys(){
		return $this->keys;
	}
	
	private function set_params($params){
		if(isset($params))
			$this->params= $params;
	}
	
	public function get_params(){
		return $this->params;
	}
	
	private function set_q($q){
		if(isset($q))
			$this->q= $q;
	}
	
	public function get_q(){
		return $this->q;
	}
	
	private function set_fq($fq){
		if(isset($fq))
			$this->fq= $fq;
	}
	
	public function get_fq(){
		return $this->fq;
	}
	
	public function get_start(){
		return $this->start;
	}
	
	private function set_start($start){
		if(isset($start))
			$this->start= $start;
	}
}


/*
 * Save an array of data to Solr
*  * */
class save_to_Solr{

	private $host;
	private $port;
	private $path;
	private $core;
	private $solr;

	public function server_setup($server){
		$this->solr = $server;
	}

	public function save($datas){

		$documents = array();
		$i = 0;

		foreach($datas as $data){
			if (isset($this->solr) && $this->check_data($data) && isset($data['id']) && count($data['id'])> 0)
			{
				$document = new Apache_Solr_Document();												
				$document->id = $data['id'];
					
				if (isset($data['ip']) && count($data['ip'])> 0)
					$document->ip = $data['ip'];
 				
				if (isset($data['last_update']) && count($data['last_update'])> 0)
					$document->last_update = $data['last_update'];
					
				if (isset($data['q']) && count($data['q'])> 0)
					$document->q = $data['q'];
				
				if (isset($data['prev_q']) && count($data['prev_q'])> 0)
					$document->prev_q = $data['prev_q'];
					
				if (isset($data['fq']) && count($data['fq'])> 0 )
					$document->facet = $data['fq'];
				
				if (isset($data['prev_fq']) && count($data['prev_fq'])> 0 )
					$document->prev_facet = $data['prev_fq'];

				if (isset($data['language']))
					$document->language = $data['language'];
				
				if (isset($data['numfound']))
					$document->numfound = $data['numfound'];
				
				if (isset($data['picture_url']))
					$document->picture_url = $data['picture_url'];
				
				if (isset($data['user']))
					$document->user = $data['user'];
				

				$documents[] = $document;
				
				$i++;
			}
		}

		if(count($documents) > 0){
			try{
				$this->solr->addDocuments($documents);
				$this->solr->commit();
				return true;
			}
			catch (Exception $e) {
				//die($e->__toString());
				return ($e->__toString());
			}
		}

		return false;
	}

	public function flush_solr(){
		if (isset($this->solr)){
			$this->solr->deleteByQuery('*:*');
			$this->solr->commit();
		}
	}

	private function check_data($data){
		return
		isset($data) &&
		isset($data['q']) && count($data['q'])> 0 ||
		isset($data['facet']) && count($data['facet'])> 0 ||
		isset($data['q']) && isset($data['facet']) && (count($data['q']) + count($data['facet']))> 0;
	}
}

?>