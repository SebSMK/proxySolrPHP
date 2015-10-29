<?php

class Foto2Solr {
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
	private $pattern = '/solr/prod_all_dk/select?';
	private $pattern_en = '/solr/prod_all_en/select?';

	public function set_file($file) {
		$this->file = $file;
	}

	public function readfile() {
		$lines = array();
		$file = file($this->file);

		foreach ($file as $line) {
			printf("line %s<br>", $line);
			$la = new line_analyzor($line);
			$data = array();
			
			if(!$this->isValidData($la->get_invnumber()))
				continue;
			
			$data['invnumber'] = $la->get_invnumber();
			$data['link'] = $la->get_link();
			$data['created'] = $la->get_date();
			$data['type'] = $la->get_type();
			$data['size'] = $la->get_size();

			$lines[] = $data;
		}
		return $lines;
	}

	/*
	 * private
	*  * */		
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
			if (isset($this->solr))
			{
				$document = new Apache_Solr_Document();
				
				$document->id = uniqid();

				$document->invnumber = strtolower($data['invnumber']);
				$document->link = $data['link'];
				
				//$document->created = $data['created'];
				$document->type = $data['type'];
				//$document->size = $data['size'];

				$documents[] = $document;
				
				$i++;
			}
		}

		if(count($documents) > 0){								
				try{
				
					var_dump($documents);
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
}

/*
 *
* */
class line_analyzor{

	private $invnumber;
	private $link;
	private $date;
	private $line;
	private $type;
	private $size;

	public function __construct($line) {
		$this->line = $line;
		//$this->run_analyse();
		$this->run_analyse_fotosation();
	}

	public function get_invnumber(){
		return $this->invnumber;
	}

	public function get_link(){
		return $this->link;
	}

	public function get_date(){
		return $this->date;
	}

	public function get_type(){
		return $this->type;
	}
	
	public function get_size(){
		return $this->size;
	}
	
	private function run_analyse_fotosation(){
	
		$parts = explode(';', $this->line);
		$this->link = '\\foto-03\FotoI\VÆRKfotos\KMS\2014\Ikke'; //$parts[0]; //'\\\\fofou\fefw'; //$parts[0];
	
		$this->invnumber = $this->extract_invnumber_fotostation($parts);
		$this->type = $this->extract_filetype($parts[1]);
	}
	
	private function extract_invnumber_fotostation($parts){			
		$pattern = "/(.*)\.[^.]+$/"; // remove file extension
		preg_match($pattern, $parts[1], $filename);	
		var_dump($filename);	
		return isset($parts[2]) ? $parts[2] : count($filename) > 1 && isset($filename[1]) ? $filename[1] : '' ;		
	}
		
	private function run_analyse(){
						
		$parts = explode('"', $this->line);
		$this->link = isset($parts[1]) ? $parts[1] : '';
		$this->size = isset($parts[5]) ? intval($parts[5]) : 0;
		$this->date = isset($parts[3]) ? $this->convert_log_time($parts[3]) : '';

		$this->invnumber = $this->extract_invnumber($this->line);
		$this->type = $this->extract_filetype($this->link);		

		return;	
	}
			
	private function extract_invnumber($input_line){
		$pattern = "/(.+\\\\)*(.+)\\.(.+)$/";
		preg_match($pattern, $input_line, $res);

		return isset($res[2]) ? $res[2] : '';
	}
	
	private function extract_filetype($link){
		var_dump($link);
		$pattern = "/\\.[0-9a-z]+$/i";
		preg_match($pattern, $link, $res);
		var_dump($res);
		return isset($res[0]) ? $res[0] : '';			
	}

	private function convert_log_time($s)
	{
		if (!$t = strtotime($s)) return FALSE;
		return gmdate('Y-m-d\TH:i:s\Z', strtotime(date('c', $t)));
	}
	
	
}

?>