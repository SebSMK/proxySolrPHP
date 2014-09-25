<?php

class Pic2Solr {
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
		//var_dump($extractor->readfile());
		
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
	private $pattern = '/globus';
	private $caller_pattern = '"http://www.smk.dk/';
			
	public function set_file($file) {		
		$this->file = $file;
	}
	
	public function readfile() {
		$lines = array();
		$i = 0;
		$file = file($this->file);
		foreach ($file as $line) {
			$la = new line_analyzor($line);	
			$q = array();
			$fq = array();
			$picture_url = '';
			$language = '';
			if ($this->isPicRequest($la)){
				// find the last request of the same user													 				
				
				$last = $this->findLastReq($file, $i, $la->get_ip());
				if(isset($last)){					
// 					var_dump($last->get_q());
// 					var_dump($last->get_fq());
// 					var_dump($last->get_language());
					printf("<img src='http://%s%s'>", $la->get_host(), $la->get_req());	
					$q = $this->isValidData($last->get_q()) ? $last->get_q() : null;
					$fq = $this->isValidData($last->get_fq()) ? $last->get_fq() : null;					
					$picture_url = sprintf("http://%s%s", $la->get_host(), $la->get_req());	
					$language = $this->isValidData($last->get_language()) ? $last->get_language() : null;										
				}				
				
				//if(count($q) + count($fq) > 0){
				if($this->isValidData($q) || $this->isValidData($fq)){	
					$data = array();
					$data['ip'] = $la->get_ip();
					$data['last_update'] = $la->get_date();
					if(count($q) > 0)
						$data['q'] = $q;
					if(count($fq) > 0)
						$data['facet'] = $fq;	
					if($picture_url != '')
						$data['picture_url'] = $picture_url;
					if($language != '')
						$data['language'] = $language;
					$data['req'] = $la->get_req();
					
					$lines[] = $data;					
				}
			}			
			
			$i++;
		}	
		return $lines;
	}
	
	/* 
	 * private 
	 *  * */
	private function isPicRequest($la){	
		return substr( $la->get_req(), 0, strlen($this->pattern) ) === $this->pattern &&
			   substr( $la->get_caller(), 0, strlen($this->caller_pattern) ) === $this->caller_pattern;				
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
	
	private function findLastReq($file, $i, $ip){
		$j = 0;
		$jmax = 50;
		while($i >= 0 && $j < $jmax){
			$line = $file[$i];
			$la = new line_analyzor($line);
			if($ip == $la->get_ip()){				
				if(count($la->get_q()) > 0){					
					if(strpos(implode("", $la->get_q()), 'id_s') === false){						
						return $la;												
					}					
				}								
			}
			$i--;
			$j++;						
		}	

		return null;
	}	
	
}

/*
 * 
 * */
class line_analyzor{
	
	private $host;
	private $ip;
	private $date;
	private $req;
	private $q;
	private $fq;
	private $caller;
	private $language;
	private $line;	
	private $solr_pattern = '/solr/prod_all_dk/select?';
	private $solr_pattern_en = '/solr/prod_all_en/select?';
	
	public function __construct($line) {
		$this->line = $line;
		$this->run_analyse();		
	}

	public function get_host(){
		return $this->host;		
	}
	
	public function get_ip(){
		return $this->ip;
	}
	
	public function get_date(){
		return $this->date;
	}
	
	public function get_req(){
		return $this->req;
	}
	
	public function get_q(){
		return $this->q;
	}
	
	public function get_fq(){
		return $this->fq;
	}	

	public function get_caller(){
		return $this->caller;
	}
	
	public function get_language(){		
		return $this->language;
	}
	
	private function run_analyse(){
		$parts = explode(' ', $this->line);
		$this->host = isset($parts[0]) ? $parts[0] : '';
		$this->ip = isset($parts[1]) ? $parts[1] : '';
		$this->date = isset($parts[4]) ? $this->convert_log_time(ltrim($parts[4], '[')) : '';
		$this->req = isset($parts[7]) ? $parts[7] : '';
		$this->caller = isset($parts[11]) ? $parts[11] : '';
// 		var_dump('----------');
// 		var_dump($this->req);
		
		if(substr( $this->req, 0, strlen($this->solr_pattern) ) === $this->solr_pattern)
			$this->language = 'dk';
		if(substr( $this->req, 0, strlen($this->solr_pattern_en) ) === $this->solr_pattern_en)
			$this->language = 'en';
				
// 		var_dump($this->language);
// 		var_dump('********');

		$request_extractor = new solr_request_extractor();		
		
		
		$request_extractor->set_request(str_replace($this->solr_pattern, '', $this->req));
		$request_extractor->extract_params();
		
		$this->q = $request_extractor->get_q();
		$this->fq = $request_extractor->get_fq();
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
			 
			//if(!isset($params['start'])){
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
			//}						
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
				
				$document->id = uniqid();
					
				$document->ip = $data['ip'];
				$document->last_update = $data['last_update'];
					
				if (isset($data['q']) && count($data['q'])> 0)
					$document->prev_q = $data['q'];
					
				if (isset($data['facet']) && count($data['facet'])> 0 )
					$document->prev_facet = $data['facet'];
				
				if (isset($data['picture_url'])){
					$document->picture_url = $data['picture_url'];
					$document->numfound = 1;
				}
				
				if (isset($data['language']))
					$document->language = $data['language'];
				
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