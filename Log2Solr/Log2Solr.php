<?php

class Log2Solr {
	public $server_url;
	public $file;

	public function __construct($server_url, $file) {
		$this->server_url = $server_url;
		$this->file = $file;
	}

	public function run() {		
		printf("Start proceeding %s<br>", $this->file);
		$lines = array();
		$extractor = new solr_log_extractor();
		$extractor->set_file($this->file);
		$saver = new save_to_Solr();
		$saver->server_setup($this->server_url);
		$saver->save($extractor->readfile());
		printf("Finish proceeding %s<br><br>", $this->file);
	}
	
	public function flush() {		
		$saver = new save_to_Solr();
		$saver->server_setup($this->server_url);
		$saver->flush_solr();
	}
}

/*
 * Extracts Solr request from an Apache log 
 * **/
class solr_log_extractor
{	
	private $file = '';
			
	public function set_file($file) {		
		$this->file = $file;
	}
	
	public function readfile() {
		$lines = array();
		foreach (file($this->file) as $line) {
			$parts = explode(' ', $line);
			$ip = $parts[1];
			$date = $parts[4];
			$req = $parts[7];
			
			if ($this->isRequest($req)){
				$request_extractor = new solr_request_extractor();
				$request_extractor->set_request($req);
				$request_extractor->extract_params();
				
				$q = $request_extractor->get_q();
				$fq = $request_extractor->get_fq();					 
				
				if(count($q) + count($fq) > 0){
					$data = array();
					$data['ip'] = $ip;
					$data['last_update'] = $this->convert_log_time(ltrim($date, '['));
					if(count($q) > 0)
						$data['q'] = $q;
					if(count($fq) > 0)
						$data['facet'] = $fq;					
					$data['req'] = $req;
					
					$lines[] = $data;
				}									
			}			
		}	
		return $lines;
	}
	
	/* 
	 * private 
	 *  * */
	private function isRequest($data){
		$pattern = '/solr/prod_all_dk/select?';
		return strrpos($data, $pattern) === false ? false : true;				
	}		
	
	private function convert_log_time($s)
	{
	    $s = preg_replace('#:#', ' ', $s, 1);
	    $s = str_replace('/', ' ', $s);
	    if (!$t = strtotime($s)) return FALSE;
	    return gmdate('Y-m-d\TH:i:s\Z', strtotime(date('c', $t)));
	}
}


/*
 * Extract parameters from a Solr request
* **/
class solr_request_extractor{

	private $req = '';
	private $q = array();
	private $fq = array();
	private $start = '';


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

	public function set_request($req){
		if(isset($req))
			$this->req = $req;
	}

	function extract_params(){

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
					//list($key, $value) = explode('=', $pair, 2);
					$key = strtok($pair, "=");
					$value = strtok("=");
					
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
			}						
		}
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
	
	public function server_setup($server_url){
		$spliturl = parse_url($server_url);
		$host = $spliturl['host'] == 'solr.smk.dk' ? 'solr-02.smk.dk' : $spliturl['host'];
		$port = $spliturl['host'] == 'solr.smk.dk' ? '8080' : $spliturl['port'];
		$core_log = $spliturl['host'] == 'solr.smk.dk' ? 'prod_search_log' : 'preprod_search_log';		
		$path = explode("/", trim($spliturl['path'], '/')) ;		
		$core = array_pop($path);
		$path = implode("/", $path);		
		
		$this->solr = new Apache_Solr_Service($host, $port, '/' . $path . '/' . $core . '/' );			
	}
	
	public function save($datas){
		
		$documents = array();
		$i = 0;
		
		foreach($datas as $data){
			if (isset($this->solr) && $this->check_data($data))
			{
				$document = new Apache_Solr_Document();
			
				//var_dump($data);					
				
				$document->id = uniqid();
					
				$document->ip = $data['ip'];
				$document->last_update = $data['last_update'];
					
				if (isset($data['q']) && count($data['q'])> 0)
					$document->q = $data['q'];
					
				if (isset($data['facet']) && count($data['facet'])> 0 )
					$document->facet = $data['facet'];

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
				die($e->__toString());
				//return ($e->__toString());
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