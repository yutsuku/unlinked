<?php
/**
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
* @version 1.1
* @author moh <moh@yutsuku.net>
* @TO-DO: better variable type checking in IF ==
*/
class template {

	public $template;
	public $assigned_vars;
	public $compiled;
	public $pblocks;
	
	public function set($file) {
		if(file_exists($file)) {
			$this->template=$file;
			//printf("%s is now set at template\n", $file);
		} else {
			//printf("%s is invalid path!\nCWD: %s", $file, getcwd());
		}
	}
	
	public function compile($block, $level=0) {
		if(!file_exists($this->template)) return;
		$h = file_get_contents($this->template);
		$this->assigned_vars=$block;
		foreach($block['.'][0] as $k=>$v){
			$block['.'][0][$k] = '{'.$v.'}';
		}
		$r = str_replace($block['.'][0], $block['.'][1],$h);
		// preg_match_all("/(<!--\s*)(\w+)(\s(\w+)(\s([^\d\w\s]{2})\s*([a-zA-Z0-9]+))?)?(\s*-->)/", $r, $matches);
		preg_match_all("/(<!--\s*)(\w+)(\s(.[^\s]+)(\s([^\d\w\s]{2})\s*([a-zA-Z0-9]+))?)?(\s*-->)/", $r, $matches);
		$this->pblocks=array('.'=>array($matches[0], $matches[2], $matches[4], $matches[6], $matches[7]));
		
		$this->compiled=self::parse_block($r, $level);
		if(!file_exists('cache') || !is_dir('cache')) {
			mkdir('cache', 0777);
			file_put_contents('cache/index.html', '');
			file_put_contents('cache/.htaccess', "Order deny,allow\nDeny from all");
		}
		file_put_contents('cache/'. md5($this->template) . '.php', $this->compiled, LOCK_EX);
	}
	
	public function parse_block($block, $level=0) {
		$var=$this->pblocks['.'];
		$parse='';
		for($i=0;$i<count($var[0]);$i++) {
			$arg=strtoupper($var[1][$i]);
			switch($arg){
				case 'IF':
					if(!$var[3][$i]) {
						if ( substr($var[2][$i], 0, 1) == "!" ) { // !NOT_SOMETHING
							$parse= '<?php if ( !in_array("' . substr($var[2][$i], 1) . '", $this->assigned_vars["."][0] )) { ?>';
							$block = str_replace($var[0][$i], $parse, $block);
						} else {
							$parse= '<?php if ( in_array("' . $var[2][$i] . '", $this->assigned_vars["."][0] )) { ?>';
							$block = str_replace($var[0][$i], $parse, $block);
						}
					} else {
						$parm=$var[3][$i];
						switch($parm){
							case '==':
								if($key = array_search($var[2][$i], $this->assigned_vars["."][0])) {
									//echo 'searching in array ' . $key ."\n";
									//echo gettype($var[4][$i]);
									$parse= '<?php if ( ' . (is_bool($this->assigned_vars["."][1][$key])? ($this->assigned_vars["."][1][$key] ? 'true' : 'false') : '\'' . $this->assigned_vars["."][1][$key] . '\'' ) . ' == ' . (is_bool($var[4][$i])?($var[4][$i]?'true':'false'):sprintf("'%s'",$var[4][$i])) . ' ) { ?>';
									$block = str_replace($var[0][$i], $parse, $block);
									//echo $parse;
								} else {
									$parse= '<?php if ( \'' . (is_bool($this->assigned_vars["."][1][($level != 0 ? $level-1 : $i)])? ($this->assigned_vars["."][1][($level != 0 ? $level-1 : $i)] ? 'true' : 'false') : addslashes($this->assigned_vars["."][1][($level != 0 ? $level-1 : $i)])) . '\' == \'' . $var[4][$i] . '\' ) { ?>';
									$block = str_replace($var[0][$i], $parse, $block);
								}
							break;
							case '!=':
								if($key = array_search($var[2][$i], $this->assigned_vars["."][0])) {
									//echo 'searching in array ' . $key ."\n";
									//echo gettype($var[4][$i]);
									$parse= '<?php if ( ' . (is_bool($this->assigned_vars["."][1][$key])? ($this->assigned_vars["."][1][$key] ? 'true' : 'false') : '\'' . $this->assigned_vars["."][1][$key]) . '\' != ' . (is_bool($var[4][$i])?($var[4][$i]?'true':'false'):sprintf("'%s'",$var[4][$i])) . ' ) { ?>';
									$block = str_replace($var[0][$i], $parse, $block);
									//echo $parse;
								} else {
									$parse= '<?php if ( ' . (is_bool($this->assigned_vars["."][1][($level != 0 ? $level-1 : $i)])? ($this->assigned_vars["."][1][($level != 0 ? $level-1 : $i)] ? 'true' : 'false') : $this->assigned_vars["."][1][($level != 0 ? $level-1 : $i)]) . ' != ' . $var[4][$i] . ' ) { ?>';
									$block = str_replace($var[0][$i], $parse, $block);
								}
							break;
						}
					}
				break;
				case 'ENDIF':
					$parse='<?php } ?>';
					$block = str_replace($var[0][$i], $parse, $block);
				break;
				case 'ELSE':
					$parse='<?php } else { ?>';
					$block = str_replace($var[0][$i], $parse, $block);
				break;
				case 'ELSEIF':
					if(!$var[3][$i]) {
						$parse= '<?php } elseif ( in_array("' . $var[2][$i] . '", $this->assigned_vars["."][0] )) { ?>';
						$block = str_replace($var[0][$i], $parse, $block);
					} else {
						$parm=$var[3][$i];
						switch($parm){
							case '==':
								if($key = array_search($var[2][$i], $this->assigned_vars["."][0])) {
									//echo 'searching in array ' . $key ."\n";
									//echo gettype($var[4][$i]);
									$parse= '<?php } elseif ( ' . (is_bool($this->assigned_vars["."][1][$key])? ($this->assigned_vars["."][1][$key] ? 'true' : 'false') : '\'' . $this->assigned_vars["."][1][$key] . '\'' ) . ' == ' . (is_bool($var[4][$i])?($var[4][$i]?'true':'false'):sprintf("'%s'",$var[4][$i])) . ' ) { ?>';
									$block = str_replace($var[0][$i], $parse, $block);
									//echo $parse;
								} else {
									//echo 'searching in array ' . $key ."\n";
									//echo gettype($var[4][$i]);
									$parse= '<?php } elseif ( ' . (is_bool($this->assigned_vars["."][1][$key])? ($this->assigned_vars["."][1][$key] ? 'true' : 'false') : '\'' . $this->assigned_vars["."][1][$key] . '\'' ) . ' == ' . (is_bool($var[4][$i])?($var[4][$i]?'true':'false'):sprintf("'%s'",$var[4][$i])) . ' ) { ?>';
									$block = str_replace($var[0][$i], $parse, $block);
									//echo $parse;
								}
							break;
							case '!=':
								//printf("loop  %d, level %d\n", $i, $level);
								//print_r($var);
								//print_r($this->assigned_vars["."][1]);
								$parse= '<?php } elseif ( ' . $this->assigned_vars["."][1][($level != 0 ? $level-1 : $i-1)] . ' != ' . $var[4][$i] . ' ) { ?>';
								$block = str_replace($var[0][$i], $parse, $block);
							break;
						}
					}
				break;
				case 'INCLUDE':
					// WILL break things
					$t = new template();
					$t->set($var[2][$i]);
					$t->compile($this->assigned_vars, $i);
					//$parse = "<!-- START " .  $var[2][$i] . " -->\n";
					$parse = (file_exists('cache/'. md5($var[2][$i]) . '.php' ) ? file_get_contents('cache/'. md5($var[2][$i]) . '.php') : '' );
					//$parse .= "<!-- END " .  $var[2][$i] . " -->\n";
					$block = str_replace($var[0][$i], $parse, $block);	
				break;
				default:
				break;
			}
		}
		return $block;
	}
	
	public function Show($include=true) {
		if ( $include ) {
			//echo "<pre>", print_r($this->assigned_vars, true), "</pre>", PHP_EOL;
			if(file_exists('cache/'. md5($this->template) . '.php' )) @include('cache/'. md5($this->template) . '.php');
		} else {
			if(file_exists('cache/'. md5($this->template) . '.php' )) return $this->compiled;
		}
	}
	
}

/*
$c=new template;
$c->set('template.html');

$a=array(
	'.' => array(
		array('SIMPLE_ONE','NOT_SIMPLE','ADVANCED', 'PARSE_EXT', 'INCLUDE', 'DIFFERENT'),
		array('hurr durr im simple', 'LIKE A BOSS', 3, 15, 'THIS FILE WAS INCLUDED BY PARSER', 99),
	),
);
$c->compile($a);
$c->Show();
*/

?>
