<?php

require_once(dirname(__FILE__) . '/Log2Solr.php');

class Proxy2Solr {
	private $server_url;
	private $request;	
	

	public function __construct($server_url, $request) {
		$this->server_url = $server_url;
		$this->request = $request;
	}

	public function run() {
		printf("Start proceeding %s<br>", $this->request);
		$lines = array();
		$extractor = new params_from_solr_req_extractor($this->request);		
		$saver = new save_to_Solr();
		$saver->server_setup($this->server_url);
		$saver->save($extractor->extract());
		printf("Finish proceeding %s<br><br>", $this->request);
	}

	public function flush() {
		$saver = new save_to_Solr();
		$saver->server_setup($this->server_url);
		$saver->flush_solr();
	}
}


/*
 * 
* **/
class params_from_solr_req_extractor
{
	private $request;
	private $pattern = '/solr/prod_all_dk/select?';
	private $pattern_en = '/solr/prod_all_en/select?';
		
	public function __construct($request) {
		$this->request = $request;		
	}

	public function extract() {
		$lines = array();
		
		if(isset($this->request)) {
			$la = new line_analyzor($this->request);
			$q = array();
			$fq = array();
			$picture_url = '';
			$language = '';
			if ($this->isRequest($la->get_req()) && !$this->isDetailViewReq($la->get_q())){
				if($this->isValidData($la->get_q()) || $this->isValidData($la->get_fq())){
					$data = array();
					$data['ip'] = $la->get_ip();
					$data['last_update'] = $la->get_date();
					if($this->isValidData($la->get_q()))
						$data['q'] = $la->get_q();
					if($this->isValidData($la->get_fq()))
						$data['facet'] = $la->get_fq();
					if($this->isValidData($la->get_language()))
						$data['language'] = $la->get_language();
					$data['req'] = $la->get_req();

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
		return substr( $data, 0, strlen($this->pattern) ) === $this->pattern ||
		substr( $data, 0, strlen($this->pattern_en) ) === $this->pattern_en;
	}

	// call to an artwork's detail view - 'q' param contains 'id_s'
	private function isDetailViewReq($q){
		return strpos(implode("", $q), 'id_s') === false ? false : true;
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

	private function convert_log_time($s)
	{
		$s = preg_replace('#:#', ' ', $s, 1);
		$s = str_replace('/', ' ', $s);
		if (!$t = strtotime($s)) return FALSE;
		return gmdate('Y-m-d\TH:i:s\Z', strtotime(date('c', $t)));
	}
}

?>