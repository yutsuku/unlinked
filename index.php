<?php
include "config.php";
include "database.php";
include "class.template.php";

define("QUERY_TYPE_VIDEO", 1);
define("QUERY_TYPE_TITLE", 2);
define("QUERY_TYPE_USER", 3);
define("QUERY_TYPE_CHANNEL", 4);

function setLang() {
	if ( isset($_GET["lang"]) ) {
		if(file_exists('locale/' . htmlspecialchars($_GET["lang"]) . '.php')) {
			setcookie("lang", htmlspecialchars($_GET["lang"]), time()+7776000); // +/- 3 months
			if ( isset($_SERVER['HTTP_REFERER']) ) {
				header('Location: ' . $_SERVER['HTTP_REFERER']);
				exit;
			}
		}
	}
	if ( isset($_COOKIE["lang"]) ) {
		if(file_exists('locale/' . htmlspecialchars($_COOKIE["lang"]) . '.php')) {
			global $UN_LOCALE;
			$UN_LOCALE = htmlspecialchars($_COOKIE["lang"]);
		}
	}
}
/* 
	locale(&$array)
	gets user translation
	returns void
*/
function locale(array &$assigned_vars, $deep=false) {
	global $UN_LOCALE;
	if(file_exists('locale/' . $UN_LOCALE . '.php')) {
		include 'locale/' . $UN_LOCALE . '.php';
	} else {
		( UN_SHOW_ERRORS ? die(sprintf('Error: localization file not found (%s)', $UN_LOCALE)) : exit );
	}
	foreach($L as $key=>$value) {
		array_push($assigned_vars['.'][0], 'L_'.str_replace(' ', '_', strtoupper($key)));
		array_push($assigned_vars['.'][1], $value);
	}
	// additional search for on-fly variables
	if ( $deep ) {
		foreach($assigned_vars['.'][1] as $key=>$value) {
			if ( substr($value, 0, 2) == "L_" && $k = array_search($value, $assigned_vars['.'][0]) ) {
				$assigned_vars['.'][1][$key] = $assigned_vars['.'][1][$k];
			}
		}
	}
	if ( ($pos = array_search('ERROR_MSG', $assigned_vars['.'][0])) !== false || ($pos = array_search('MESSAGE', $assigned_vars['.'][0])) !== false ) {
		$assigned_vars['.'][1][$pos] = (isset($L[substr(strtolower($assigned_vars['.'][1][$pos]), 2)])?$L[substr(strtolower($assigned_vars['.'][1][$pos]), 2)]:'Localization not found for "' . $assigned_vars['.'][1][$pos] .'"');
	}
}
function humanDateDiff($userDate, $print=false) {
	global $UN_LOCALE, $L;
	
	if ( !isset($L) || !is_array($L) && file_exists("locale/" . $UN_LOCALE . ".php") ) {
		include "locale/" . $UN_LOCALE . ".php";
	}
	
	$userDate = new DateTime($userDate);
	$now = new DateTime(null, new DateTimeZone('UTC'));
	$diff = $userDate->diff($now);
	
	if ( $diff->y > 4 ) {
		$format = sprintf($L["n_year_ago"], $diff->y);
	} if ( $diff->y > 1 ) {
		$format = sprintf($L["234_year_ago"], $diff->y);
	} elseif ( $diff->y == 1 ) {
		$format =  sprintf($L["1_year_ago"], $diff->y);
	} elseif ( $diff->m > 4 ) {
		$format =  sprintf($L["n_month_ago"], $diff->m);
	} elseif ( $diff->m > 1 ) {
		$format =  sprintf($L["234_month_ago"], $diff->m);
	} elseif ( $diff->m == 1 ) {
		$format =  sprintf($L["1_month_ago"], $diff->m);
	} elseif ( $diff->d > 4 ) {
		$format =  sprintf($L["n_day_ago"], $diff->d);
	} elseif ( $diff->d > 1 ) {
		$format =  sprintf($L["234_day_ago"], $diff->d);
	} elseif ( $diff->d == 1 ) {
		$format =  sprintf($L["1_day_ago"], $diff->d);
	} elseif ( $diff->h > 4 ) {
		$format =  sprintf($L["n_hour_ago"], $diff->h);
	} elseif ( $diff->h > 1 ) {
		$format =  sprintf($L["234_hour_ago"], $diff->h);
	} elseif ( $diff->h == 1 ) {
		$format =  sprintf($L["1_hour_ago"], $diff->h);
	} elseif ( $diff->i > 4 ) {
		$format =  sprintf($L["n_minute_ago"], $diff->i);
	} elseif ( $diff->i > 1 ) {
		$format =  sprintf($L["234_minute_ago"], $diff->i);
	} elseif ( $diff->i == 1 ) {
		$format =  sprintf($L["1_minute_ago"], $diff->i);
	} elseif ( $diff->s > 10 ) {
		$format =  sprintf($L["n_second_ago"], $diff->s);
	} elseif ( $diff->s <= 10 ) {
		$format =  sprintf($L["1_second_ago"], $diff->s);
	} else {
		$expection = true;
		$format = $L["past_time"];
	}
	
	if ( isset($expection) ) {
		$time = new DateTime($diff, $format);
	} else {
		$time = $format;
	}
	if ( $print ) {
		echo $time;
	} else {
		return $time;
	}
}
function humanTime($ISO8601, $print=false) {
	$interval = new DateInterval($ISO8601);
	$out = "";
	if ( $interval->d > 0 ) {
		$out .= $interval->d . "d:";
	}
	if ( $interval->h > 0 ) {
		$out .= $interval->h . ":";
	}
	if ( $interval->i > 0 ) {
		$out .= $interval->i . ":";
	}
	$out .= $interval->s;
	if ( $print ) {
		echo $out;
	} else {
		return $out;
	}
}

/*
 * Main part is down below
*/
class Action {
	public $view = "INDEX";
	public $id = false;
	public $query = false;
	public $query_type = false;
	public $action = false;
	public $page = false;
	public $sortType = "desc";
	
	public function Get() {
		$PARM_VIEW = (isset($_GET["view"]) ? self::validate($_GET["view"], "view") : false);
		$PARM_ID = (isset($_GET["id"]) ? self::validate($_GET["id"], "videoID") : false);
		$PARM_ACTION = (isset($_GET["action"]) ? self::validate($_GET["action"], "action") : false);
		$PARM_PAGE = (isset($_GET["page"]) ? self::validate($_GET["page"], "int") : false);
		$PARM_QUERY = (isset($_GET["q"]) ? self::validate($_GET["q"]) : false);
		$PARM_QUERY_TYPE = (isset($_GET["t"]) ? self::validate($_GET["t"], "int") : false);
		$PARM_SORT_TYPE = (isset($_GET["sort"]) ? self::validate($_GET["sort"], "sort") : false);
		if ( $PARM_QUERY_TYPE && $PARM_QUERY ) {
			$this->view = "SEARCH";
			$this->query = $PARM_QUERY;
			$this->query_type = $PARM_QUERY_TYPE;
		} elseif ( $PARM_VIEW && $PARM_ID ) {
			$this->view = strtoupper($PARM_VIEW);
			$this->id = $PARM_ID;
			$this->action = $PARM_ACTION;
			$this->page = $PARM_PAGE;
			$this->sortType = $PARM_SORT_TYPE;
		} elseif ( $PARM_VIEW && $PARM_VIEW != "user" && $PARM_VIEW != "video" ) {
			$this->view = strtoupper($PARM_VIEW);
		}
		return array("view"=>$this->view, "id"=>$this->id, "query"=>$this->query, "type"=>$this->query_type, "action"=>$this->action, "page"=>$this->page, "sort"=>$this->sortType);
	}
	
	public function postForm() {
		if ( isset($_POST["name"]) && isset($_POST["email"]) && isset($_POST["message"]) ) {
			$name = self::validate($_POST["name"], "failsafe");
			$email = self::validate($_POST["email"], "email");
			$message = self::validate($_POST["message"], "failsafe");
			if ( $name && $email && $message ) {
				$domain = explode('@',$email, 2);
				$domain_end = end($domain);
				if ( checkdnsrr($domain_end . '.', 'MX') ) {
					return array("name"=>$name, "email"=>$email, "message"=>$message);
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	public function validate($variable, $method=false) {
		$variable = trim($variable);
		if ( empty($variable) ) {
			return false;
		}
		if ( !$method ) {
			$variable = htmlspecialchars(trim($variable), ENT_QUOTES, "UTF-8");
			return $variable;
		}
		
		switch($method) {
			case "email":
				return filter_var($variable, FILTER_VALIDATE_EMAIL);
			case "unsafe":
				return $variable;
			case "view":
				$allowedActions = array("video", "user", "about", "contact", "privacy-policy", "plugin", "api");
				if ( in_array($variable, $allowedActions) ) {
					return $variable;
				} else {
					return false;
				}
			break;
			case "action":
				$allowedActions = array("videos", "about");
				if ( in_array($variable, $allowedActions) ) {
					return $variable;
				} else {
					return false;
				}
			break;
			case "videoID":
				if ( preg_match("#^[A-Za-z0-9_-]+$#", $variable) ) {
					return $variable;
				} else {
					return false;
				}
			break;
			case "channelID":
				if ( preg_match("#^[A-Za-z0-9-]+$#", $variable) ) {
					return $variable;
				} else {
					return false;
				}
			break;
			case "userID":
				if ( preg_match("#^[A-Za-z0-9]+$#", $variable) ) {
					return $variable;
				} else {
					return false;
				}
			break;
			case "userName":
				$variable = htmlspecialchars($variable, ENT_NOQUOTES, "UTF-8");
				if ( !empty($variable) && strlen($variable) > 0 && strlen($variable) < 120 ) {
					return $variable;
				} else {
					return false;
				}
			break;
			case "failsafe": {
				$variable = htmlspecialchars($variable, ENT_NOQUOTES, "UTF-8");
				if ( !empty($variable) ) {
					return $variable;
				} else {
					return false;
				}
			}
			case "int":
				if ( !empty($variable) && strlen($variable) > 0 && strlen($variable) < 6 ) {
					return intval(htmlspecialchars(trim($variable), ENT_QUOTES, "UTF-8"));
				} else {
					return false;
				}
			break;
			case "sort":
				$allowed = array("asc", "desc");
				if ( in_array($variable, $allowed) ) {
					return $variable;
				} else {
					return false;
				}
			break;
			default:
				return false;
		}
		return false; // should not get here anyway
	}
}
/** 
  * Returns the url query as associative array 
  * 
  * @param    string    query 
  * @return    array    params 
  */ 
function convertUrlQuery($query) { 
     $queryParts = explode('&', $query); 
     
     $params = array(); 
     foreach ($queryParts as $param) { 
         $item = explode('=', $param); 
         $params[$item[0]] = $item[1]; 
     } 
     
     return $params; 
 }

$PAGE_PREFIX = "";
$variables=array(
	'.' => array(
		array(),
		array(),
	),
);

$variables["."][0][] = "LINK_LANG_ENGLISH";
$variables["."][1][] = "?lang=enUS";

$variables["."][0][] = "LINK_LANG_POLISH";
$variables["."][1][] = "?lang=plPL";

$variables["."][0][] = "LINK_ABOUT_US";
$variables["."][1][] = "?view=about";

$variables["."][0][] = "LINK_CONTACT_US";
$variables["."][1][] = "?view=contact";

$variables["."][0][] = "LINK_PP";
$variables["."][1][] = "?view=privacy-policy";

$variables["."][0][] = "LINK_PLUG_IN";
$variables["."][1][] = "?view=plugin";

$variables["."][0][] = "LINK_API";
$variables["."][1][] = "?view=api";

setLang();
try {
	$Controller = new Controller($db["host"], $db["port"], $db["user"], $db["pwd"], $db["schema"]);
} catch (Controller_Exception $e) {
	$PAGE_PREFIX = "WROOOOOOOOONG! - ";
	
	$template=new template();
	$template->set('site/main.html');
	$variables["."][0][] = "ERROR_MSG";
	$variables["."][1][] = $e;
	
	$variables["."][0][] = "PAGE_TITLE";
	$variables["."][1][] = $PAGE_PREFIX . $UN_TITLE;
	
	locale($variables);
	//echo "<pre>", print_r($variables, true), "</pre>", PHP_EOL;
	//echo "<pre>", print_r($L, true), "</pre>", PHP_EOL;
	$template->compile($variables);
	$template->Show();
	exit;
}

$Action = new Action();
$UserAction = $Action->Get();

switch($UserAction["view"]) {
	case "INDEX":
		$LatestVideos = $Controller->videoGetLatest();
		$variables["."][0][] = "VIEW_PAGE";
		$variables["."][1][] = "INDEX";
		
		if ( !$LatestVideos ) {
			$variables["."][0][] = "CONTENT";
			$variables["."][1][] = "";
			$variables["."][0][] = "ERROR_MSG";
			$variables["."][1][] = "L_NO_RESULTS";
			break;
		}
		
		$Content = "";
		
		$template_tmp = new template();
		$template_tmp->set("site/video_block.html");
		
		foreach($LatestVideos as $key) {
		
			$variable_tmp['.'][0][] = "VIDEO_IMG_THUMB";
			$variable_tmp['.'][1][] = $key["thumb"]["medium"];
			
			$variable_tmp['.'][0][] = "VIDEO_TITLE";
			$variable_tmp['.'][1][] = $key["title"];
			
			$variable_tmp['.'][0][] = "VIDEO_ID";
			$variable_tmp['.'][1][] = $key["video_id"];
			
			$variable_tmp['.'][0][] = "VIDEO_ADDED";
			$variable_tmp['.'][1][] = humanDateDiff($key["timestamp"]);
			
			$template_tmp->compile($variable_tmp);
			$Content .= $template_tmp->Show(false);
			unset($variable_tmp);
		}
		
		$template_tmp = new template();
		$template_tmp->set("site/grid.html");
		$variable_tmp['.'][0][] = "CONTENT";
		$variable_tmp['.'][1][] = $Content;
		$template_tmp->compile($variable_tmp);
		$Content = $template_tmp->Show(false);
		
		$variables["."][0][] = "CONTENT";
		$variables["."][1][] = $Content;
	break;
	case "SEARCH":
		$variables["."][0][] = "VIEW_PAGE";
		$variables["."][1][] = "SEARCH";
		
		switch($UserAction["type"]) {
			case QUERY_TYPE_VIDEO:
				$query = $UserAction["query"];
				$q =  parse_url($query, PHP_URL_QUERY);
				if ( $q ) {
					$q = convertUrlQuery($q);
					if ( isset($q["v"]) ) {
						$q = $Action->validate($q["v"], "videoID");
					} elseif ( isset($q["amp;v"]) ) {
						$q = $Action->validate($q["amp;v"], "videoID");
					}
					$query = ($q ? $q : $query);
				}
				$video = $Controller->videoGetInfo($query);
				if ( !$video ) {
					$variables["."][0][] = "CONTENT";
					$variables["."][1][] = "";
					$variables["."][0][] = "ERROR_MSG";
					$variables["."][1][] = "L_NO_RESULTS";
				} else {
					$Content = "";
		
					$template_tmp = new template();
					$template_tmp->set("site/video_block.html");
					
					$variable_tmp['.'][0][] = "VIDEO_IMG_THUMB";
					$variable_tmp['.'][1][] = $video["thumb"]["medium"];
						
					$variable_tmp['.'][0][] = "VIDEO_TITLE";
					$variable_tmp['.'][1][] = $video["title"];
						
					$variable_tmp['.'][0][] = "VIDEO_ID";
					$variable_tmp['.'][1][] = $video["video_id"];
						
					$variable_tmp['.'][0][] = "VIDEO_ADDED";
					$variable_tmp['.'][1][] = humanDateDiff($video["timestamp"]);
					
					$variables["."][0][] = "NUM_RESULTS";
					$variables["."][1][] = 1;
					
					$variables["."][0][] = "SEARCH_QUERY";
					$variables["."][1][] = $UserAction["query"];
					
					$variables["."][0][] = "L_RESULTS";
					$variables["."][1][] = "L_1_RESULT";
						
					$template_tmp->compile($variable_tmp);
					$Content .= $template_tmp->Show(false);
					
					$template_tmp = new template();
					$template_tmp->set("site/grid.html");
					$variable_tmp['.'][0][] = "CONTENT";
					$variable_tmp['.'][1][] = $Content;
					$template_tmp->compile($variable_tmp);
					$Content = $template_tmp->Show(false);
					
					$variables["."][0][] = "CONTENT";
					$variables["."][1][] = $Content;
					locale($variables, true);
				}
				//echo "<pre>", print_r($video, true), "</pre>", PHP_EOL;
			break;
			
			case QUERY_TYPE_TITLE:
				$ids = $Controller->searchVideoByTitle($UserAction["query"]);
				if ( !$ids ) {
					$variables["."][0][] = "CONTENT";
					$variables["."][1][] = "";
					$variables["."][0][] = "ERROR_MSG";
					$variables["."][1][] = "L_NO_RESULTS";
				} else {
					$Content = "";
					foreach($ids as $key=>$id) {
						$video = $Controller->videoGetInfo($id);
						$template_tmp = new template();
						$template_tmp->set("site/video_block.html");
						
						$variable_tmp['.'][0][] = "VIDEO_IMG_THUMB";
						$variable_tmp['.'][1][] = $video["thumb"]["medium"];
							
						$variable_tmp['.'][0][] = "VIDEO_TITLE";
						$variable_tmp['.'][1][] = $video["title"];
							
						$variable_tmp['.'][0][] = "VIDEO_ID";
						$variable_tmp['.'][1][] = $video["video_id"];
							
						$variable_tmp['.'][0][] = "VIDEO_ADDED";
						$variable_tmp['.'][1][] = humanDateDiff($video["timestamp"]);
							
						$template_tmp->compile($variable_tmp);
						$Content .= $template_tmp->Show(false);
						unset($variable_tmp);
					}
					$template_tmp = new template();
					$template_tmp->set("site/grid.html");
					$variable_tmp['.'][0][] = "CONTENT";
					$variable_tmp['.'][1][] = $Content;
					$template_tmp->compile($variable_tmp);
					$Content = $template_tmp->Show(false);
						
					$count = count($ids);
					if ( $count == 1 ) {
						$RESULTS = "L_1_RESULT";
					} elseif ( $count > 1 && $count < 5 ) {
						$RESULTS = "L_234_RESULT";
					} else {
						$RESULTS = "L_N_RESULT";
					}
					$variables["."][0][] = "NUM_RESULTS";
					$variables["."][1][] = $count;
					
					$variables["."][0][] = "SEARCH_QUERY";
					$variables["."][1][] = $UserAction["query"];
					
					$variables["."][0][] = "L_RESULTS";
					$variables["."][1][] = $RESULTS;
					
					$variables["."][0][] = "CONTENT";
					$variables["."][1][] = $Content;
					locale($variables, true);
				}
				
			break;
			
			case QUERY_TYPE_USER:
				$ids = $Controller->channelGetID($UserAction["query"], false, true);
				if ( !$ids ) {
					$variables["."][0][] = "CONTENT";
					$variables["."][1][] = "";
					$variables["."][0][] = "ERROR_MSG";
					$variables["."][1][] = "L_NO_RESULTS";
				} else {
					if ( !is_array($ids) ) {
						// put string into array, too lazy to make IF for both of them
						$results = array($ids);
					} else {
						$results = $ids;
					}
						
					$Content = "";
					foreach($results as $k=>$id) {
						$channel = $Controller->channelGetInfo($id);
						$template_tmp = new template();
						$template_tmp->set("site/page_channel.html");
			
						$variable_tmp["."][0][] = "USER_AVATAR";	
						$variable_tmp["."][1][] = $channel["avatars"]["default"];
						
						$variable_tmp["."][0][] = "USER_BANNER_IMG";	
						$variable_tmp["."][1][] = $channel["banners"]["bannerImageUrl"];
						
						$variable_tmp["."][0][] = "USER_TITLE";
						$variable_tmp["."][1][] = $channel["title"];
						
						$variable_tmp["."][0][] = "USER_ID";	
						$variable_tmp["."][1][] = $channel["userid"];
					
						$variable_tmp["."][0][] = "CHANNEL_ID";	
						$variable_tmp["."][1][] = $id;
						
						$variable_tmp["."][0][] = "USER_DESCRIPTION";
						$variable_tmp["."][1][] = $channel["description"];
						
						$variable_tmp["."][0][] = "DATE_JOINED";
						$variable_tmp["."][1][] = humanDateDiff($channel["published"]);
						
						$variable_tmp["."][0][] = "DATE_JOINED_RAW";
						$variable_tmp["."][1][] = $channel["published"];
						
						$variable_tmp["."][0][] = "LINK_USER_ABOUT";
						//$variable_tmp["."][1][] = "?view=user&id=" . $channel["userid"] . "&action=about";
						$variable_tmp["."][1][] = "?view=user&id=" . $id . "&action=about";
						
						$variable_tmp["."][0][] = "LINK_USER_VIDES";
						//$variable_tmp["."][1][] = "?view=user&id=" . $channel["userid"] . "&action=videos";
						$variable_tmp["."][1][] = "?view=user&id=" . $id . "&action=videos";
						
						$variable_tmp["."][0][] = "LINK_YOUTUBE_PROFILE";
						//$variable_tmp["."][1][] = "http://www.youtube.com/user/" . $channel["userid"];
						$variable_tmp["."][1][] = "http://www.youtube.com/channel/" . $id;
							
						$template_tmp->compile($variable_tmp);
						$Content .= $template_tmp->Show(false);
						unset($variable_tmp);
						//echo "<pre>", print_r($channel, true), "</pre>", PHP_EOL;
					}
					
					$count = count($results);
					if ( $count == 1 ) {
						$RESULTS = "L_1_RESULT";
					} elseif ( $count > 1 && $count < 5 ) {
						$RESULTS = "L_234_RESULT";
					} else {
						$RESULTS = "L_N_RESULT";
					}
					$variables["."][0][] = "NUM_RESULTS";
					$variables["."][1][] = $count;
					
					$variables["."][0][] = "SEARCH_QUERY";
					$variables["."][1][] = $UserAction["query"];
					
					$variables["."][0][] = "L_RESULTS";
					$variables["."][1][] = $RESULTS;
					
					$variables["."][0][] = "CONTENT";
					$variables["."][1][] = $Content;
					locale($variables, true);
				}
			break;
			
			case QUERY_TYPE_CHANNEL:
				$channel = $Controller->channelGetInfo($UserAction["query"]);
				if ( !$channel ) {
					$variables["."][0][] = "CONTENT";
					$variables["."][1][] = "";
					$variables["."][0][] = "ERROR_MSG";
					$variables["."][1][] = "L_NO_RESULTS";
				} else {
					$channel_id = $UserAction["query"];
					$template_tmp = new template();
					$template_tmp->set("site/page_channel.html");
			
					$variable_tmp["."][0][] = "USER_AVATAR";	
					$variable_tmp["."][1][] = $channel["avatars"]["default"];
					
					$variable_tmp["."][0][] = "USER_ID";	
					$variable_tmp["."][1][] = $channel["userid"];
					
					$variable_tmp["."][0][] = "CHANNEL_ID";	
					$variable_tmp["."][1][] = $UserAction["query"];
					
					$variable_tmp["."][0][] = "USER_BANNER_IMG";	
					$variable_tmp["."][1][] = $channel["banners"]["bannerImageUrl"];
						
					$variable_tmp["."][0][] = "USER_TITLE";
					$variable_tmp["."][1][] = $channel["title"];
						
					$variable_tmp["."][0][] = "USER_DESCRIPTION";
					$variable_tmp["."][1][] = $channel["description"];
						
					$variable_tmp["."][0][] = "DATE_JOINED";
					$variable_tmp["."][1][] = humanDateDiff($channel["published"]);
						
					$variable_tmp["."][0][] = "DATE_JOINED_RAW";
					$variable_tmp["."][1][] = $channel["published"];
						
					$variable_tmp["."][0][] = "LINK_USER_ABOUT";
					//$variable_tmp["."][1][] = "?view=user&id=" . $channel["userid"] . "&action=about";
					$variable_tmp["."][1][] = "?view=user&id=" . $channel_id . "&action=about";
						
					$variable_tmp["."][0][] = "LINK_USER_VIDES";
					//$variable_tmp["."][1][] = "?view=user&id=" . $channel["userid"] . "&action=videos";
					$variable_tmp["."][1][] = "?view=user&id=" . $channel_id . "&action=videos";
						
					$variable_tmp["."][0][] = "LINK_YOUTUBE_PROFILE";
					//$variable_tmp["."][1][] = "http://www.youtube.com/user/" . $channel["userid"];
					$variable_tmp["."][1][] = "http://www.youtube.com/channel/" . $channel_id;
							
					$template_tmp->compile($variable_tmp);
					$Content = $template_tmp->Show(false);
					
					$variables["."][0][] = "NUM_RESULTS";
					$variables["."][1][] = 1;
					
					$variables["."][0][] = "SEARCH_QUERY";
					$variables["."][1][] = $UserAction["query"];
					
					$variables["."][0][] = "L_RESULTS";
					$variables["."][1][] = "L_1_RESULT";
					
					$variables["."][0][] = "CONTENT";
					$variables["."][1][] = $Content;
					locale($variables, true);
				}
			break;
		}
		//echo "<pre>", print_r($UserAction, true), "</pre>", PHP_EOL;
	break;
	case "VIDEO":
		$videoInfo = $Controller->videoGetInfo($UserAction["id"]);
		if ( !$videoInfo ) { 
			$variables["."][0][] = "VIEW_PAGE";
			$variables["."][1][] = "NA";
			$variables["."][0][] = "CONTENT";
			$variables["."][1][] = "L_NOT_FOUND";
		} else {
			$channelInfo = $Controller->channelGetInfo($videoInfo["channel_id"]);
			$views = $Controller->updateCounter($UserAction["id"]);
			
			$variables["."][0][] = "VIEWS";
			$variables["."][1][] = $views;
			
			if ( $views == 1 ) {
				$variables["."][0][] = "L_VIEWS";
				$variables["."][1][] = "L_1_VIEW";
			} elseif ( $views > 1 && $views < 5 ) {
				$variables["."][0][] = "L_VIEWS";
				$variables["."][1][] = "L_234_VIEW";
			} else {
				$variables["."][0][] = "L_VIEWS";
				$variables["."][1][] = "L_N_VIEW";
			}
			locale($variables, true);
			
			$PAGE_PREFIX .= htmlspecialchars($videoInfo["title"]) . " - ";
			
			$variables["."][0][] = "VIEW_PAGE";
			$variables["."][1][] = "VIDEO";
			
			$variables["."][0][] = "VIDEO_ID";
			$variables["."][1][] = htmlspecialchars($UserAction["id"]);
			
			$variables["."][0][] = "VIDEO_TITLE";
			$variables["."][1][] = htmlspecialchars($videoInfo["title"]);
			
			$variables["."][0][] = "VIDEO_IMG_THUMB";
			$variables["."][1][] = $videoInfo["thumb"]["medium"];
			
			$variables["."][0][] = "DATE_PUBLISHED";
			$variables["."][1][] = humanDateDiff($videoInfo["published"]);
			
			$variables["."][0][] = "VIDEO_ADDED";
			$variables["."][1][] = humanDateDiff($videoInfo["timestamp"]);
			
			$variables["."][0][] = "VIDEO_DESCRIPTION";
			$variables["."][1][] = ($videoInfo["description"] == "" ? "L_NO_DESCRIPTION" : $videoInfo["description"]);
			
			$variables["."][0][] = "USER_TITLE";
			$variables["."][1][] = htmlspecialchars($videoInfo["channel_name"]);
			
			$variables["."][0][] = "LINK_USER_CHANNEL";
			//$variables["."][1][] = "?view=user&id=" . $channelInfo["userid"];
			$variables["."][1][] = "?view=user&id=" . $videoInfo["channel_id"];
			
			$variables["."][0][] = "USER_AVATAR";	
			$variables["."][1][] = $channelInfo["avatars"]["default"];
			
			$template_tmp = new template();
			$template_tmp->set("site/videodetails.html");
		}
	break;
	case "USER":	
		//$ChannelID = $Controller->channelGetID($UserAction["id"], true);
		$ChannelID = $Controller->channelExists($UserAction["id"]);
		if ( !$ChannelID ) { 
			$variables["."][0][] = "VIEW_PAGE";
			$variables["."][1][] = "NA";
		
			$variables["."][0][] = "CONTENT";
			$variables["."][1][] = "L_NOT_FOUND";
			
		} else {
			$ChannelID = $UserAction["id"];
			$channelInfo = $Controller->channelGetInfo($ChannelID);
			
			$PAGE_PREFIX .= htmlspecialchars($channelInfo["title"]) . " (";
			$PAGE_PREFIX .= htmlspecialchars($channelInfo["userid"]) . ") - ";
			
			$views = $Controller->updateCounter($ChannelID);
			
			$variables["."][0][] = "VIEWS";
			$variables["."][1][] = $views;
			
			if ( $views == 1 ) {
				$variables["."][0][] = "L_VIEWS";
				$variables["."][1][] = "L_1_VIEW";
			} elseif ( $views > 1 && $views < 5 ) {
				$variables["."][0][] = "L_VIEWS";
				$variables["."][1][] = "L_234_VIEW";
			} else {
				$variables["."][0][] = "L_VIEWS";
				$variables["."][1][] = "L_N_VIEW";
			}
			locale($variables, true);
			
			$variables["."][0][] = "VIEW_PAGE";
			$variables["."][1][] = "USER";
			
			$variables["."][0][] = "USER_ACTION";
			$variables["."][1][] = strtoupper(($UserAction["action"] ? $UserAction["action"] : "ABOUT"));
			
			$variables["."][0][] = "BUTTON_" . strtoupper(($UserAction["action"] ? $UserAction["action"] : "ABOUT")) . "_DISABLED";
			$variables["."][1][] = true;
			
			$variables["."][0][] = "USER_ID";	
			$variables["."][1][] = $channelInfo["userid"];
					
			$variables["."][0][] = "CHANNEL_ID";	
			$variables["."][1][] = $ChannelID;
			
			$variables["."][0][] = "USER_AVATAR";	
			$variables["."][1][] = $channelInfo["avatars"]["default"];
			
			$variables["."][0][] = "USER_BANNER_IMG";	
			$variables["."][1][] = $channelInfo["banners"]["bannerImageUrl"];
			
			$variables["."][0][] = "USER_TITLE";
			$variables["."][1][] = $channelInfo["title"];
			
			$variables["."][0][] = "USER_DESCRIPTION";
			$variables["."][1][] = $channelInfo["description"];
			
			$variables["."][0][] = "DATE_JOINED";
			$variables["."][1][] = humanDateDiff($channelInfo["published"]);
			
			$variables["."][0][] = "DATE_JOINED_RAW";
			$variables["."][1][] = $channelInfo["published"];
			
			$variables["."][0][] = "LINK_USER_ABOUT";
			//$variables["."][1][] = "?view=user&id=" . $channelInfo["userid"] . "&action=about";
			$variables["."][1][] = "?view=user&id=" . $ChannelID . "&action=about";
			
			$variables["."][0][] = "LINK_USER_VIDES";
			//$variables["."][1][] = "?view=user&id=" . $channelInfo["userid"] . "&action=videos";
			$variables["."][1][] = "?view=user&id=" . $ChannelID . "&action=videos";
			
			$variables["."][0][] = "LINK_YOUTUBE_PROFILE";
			//$variables["."][1][] = "http://www.youtube.com/user/" . $channelInfo["userid"];
			$variables["."][1][] = "http://www.youtube.com/channel/" . $ChannelID;
			
			if ( strtoupper($UserAction["action"]) == "VIDEOS" ) {
			
				$page = ($UserAction["page"] ? $UserAction["page"] : 1);
				$sort = ($UserAction["sort"] ? $UserAction["sort"] : "desc");
				$videos = $Controller->channelGetUploads($ChannelID, $page, $sort);
				
				if ( !$videos ) { 
					$variables["."][0][] = "VIEW_PAGE";
					$variables["."][1][] = "NA";
					$variables["."][0][] = "CONTENT";
					$variables["."][1][] = "L_NOT_FOUND";
				} else {
					$Content = "";
			
					$template_tmp = new template();
					$template_tmp->set("site/video_block.html");
					
					foreach($videos as $key=>$value) {
					
						if ( !is_array($value) ) continue;

						//echo "<pre>key: ", $key, ", value: ", print_r($value, true), "</pre>", PHP_EOL;
						$variable_tmp['.'][0][] = "VIDEO_IMG_THUMB";
						$variable_tmp['.'][1][] = $value["thumb"]["medium"];
						
						$variable_tmp['.'][0][] = "VIDEO_TITLE";
						$variable_tmp['.'][1][] = $value["title"];
						
						$variable_tmp['.'][0][] = "VIDEO_ID";
						$variable_tmp['.'][1][] = $value["video_id"];
						
						$variable_tmp['.'][0][] = "VIDEO_ADDED";
						$variable_tmp['.'][1][] = humanDateDiff($value["timestamp"]);
						
						$template_tmp->compile($variable_tmp);
						$Content .= $template_tmp->Show(false);
						unset($variable_tmp);
					}
					
					$template_tmp = new template();
					$template_tmp->set("site/grid.html");
					$variable_tmp['.'][0][] = "CONTENT";
					$variable_tmp['.'][1][] = $Content;
					$template_tmp->compile($variable_tmp);
					$Content = $template_tmp->Show(false);
					
					$variables["."][0][] = "CONTENT";
					$variables["."][1][] = $Content;
					
					// LINK_PAGE_PREVIOUS
					// LINK_PAGE_NEXT
					
					if ( $videos["pages"] == 1 ) {
						// only current page
					}
					$page_next = $videos["page"];
					$page_previous = $videos["page"];
					if ( $videos["page"] < $videos["pages"] ) {
						// next page is present
						$variables["."][0][] = "LINK_PAGE_NEXT";
						$variables["."][1][] = sprintf("?view=user&id=%s&action=videos&sort=%s&page=%s", urlencode($UserAction["id"]), $sort, ++$page_next);
					}
					if ( $videos["pages"] > 1 && $videos["page"] > 1 ) {
						// previous page is present
						$variables["."][0][] = "LINK_PAGE_PREVIOUS";
						$variables["."][1][] = sprintf("?view=user&id=%s&action=videos&sort=%s&page=%s", urlencode($UserAction["id"]), $sort, --$page_previous);
					}
					if ( $videos["pages"] > 1 ) {
						$variables["."][0][] = "SORT_ORDER";
						$variables["."][1][] = 1;
						
						$variables["."][0][] = "L_SORT_ORDER";
						$variables["."][1][] = ($sort == "asc" ? "L_DESCENDING" : "L_ASCENDING");
						
						$variables["."][0][] = "LINK_SORT_ORDER";
						$variables["."][1][] = sprintf("?view=user&id=%s&action=videos&sort=%s&page=%s", urlencode($UserAction["id"]), ($sort == "asc" ? "dedc" : "asc"), $videos["page"]);
					}
					locale($variables, true);
					
				}
			}
			
		}
	// USER
	break;
	case "ABOUT":
		$variables["."][0][] = "VIEW_PAGE";
		$variables["."][1][] = "ABOUT";
	break;
	case "CONTACT":
		$variables["."][0][] = "VIEW_PAGE";
		$variables["."][1][] = "CONTACT";
		
		$variables["."][0][] = "LINK_CONTACT_US_FORM";
		$variables["."][1][] = "?view=contact&post=1";
		
		if ( isset($_GET["post"]) ) {
			$Data = $Action->postForm();
			if ( isset($_GET["confirm"]) ) {
				// sent
				$variables["."][0][] = "FORM_SENT";
				$variables["."][1][] = "1";
				//echo "<pre style='font-size:26px;' class='hot-red'>", "note to self: insert sent data into database", "</pre>", PHP_EOL;
			} else {
				if ( $Data ) {
					// sent, redirect user to avoid submitting form second time & make sure its stored
					if ( $Controller->pushVisitorMessage($Data["name"], $Data["email"], $Data["message"]) ) {
						header("Location: ?view=contact&post=1&confirm=1");
						exit;
					} else {
						$variables["."][0][] = "FORM_ERROR";
						$variables["."][1][] = "1";
					}
				} else {
					// not sent, invalid input?
					$variables["."][0][] = "FORM_ERROR";
					$variables["."][1][] = "1";
				}
			}
		} else {
			// normal view... do nothing extra
		}
		
	break;
	case "PRIVACY-POLICY":
		$variables["."][0][] = "VIEW_PAGE";
		$variables["."][1][] = "PRIVACY";
	break;
	case "PLUGIN":
		$variables["."][0][] = "VIEW_PAGE";
		$variables["."][1][] = "PLUGIN";
	break;
	case "API":
		$variables["."][0][] = "VIEW_PAGE";
		$variables["."][1][] = "API";
	break;
}

$variables["."][0][] = "PAGE_TITLE";
$variables["."][1][] = $PAGE_PREFIX . $UN_TITLE;

$template=new template();
$template->set('site/main.html');

locale($variables);
//echo "<pre>", print_r($variables, true), "</pre>", PHP_EOL;
$template->compile($variables);
$template->Show();
?>