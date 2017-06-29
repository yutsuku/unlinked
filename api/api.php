<?php
chdir(dirname(__FILE__));

include "../database.php";

class unlinked_API_Exception extends Exception {
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
	public function API_Error() {
		$response = array(
			"error" => 1,
			"message" => (isset($this->message) ? $this->message : ""),
			"code" => $this->code
		);
		return $response;
	}
}
class unlinked_API {
	public $response = array();
	public $errorList = array();
	public $Controller = false;
	public $queueJobs = true;
	public $config = array();
	
	public function __construct($config=false) {
		$this->response["error"] = 0;
		$this->errorList = array(
			0 => "unknown error",
			1 => "invalid request",
			2 => "not found",
			3 => "No Data (server fault)",
			4 => "No Data (youtube fault)"
		);
		$this->config["host"] = (isset($config["host"]) ? $config["host"] : false);
		$this->config["port"] = (isset($config["port"]) ? $config["port"] : false);
		$this->config["user"] = (isset($config["user"]) ? $config["user"] : false);
		$this->config["pwd"] = (isset($config["pwd"]) ? $config["pwd"] : false);
		$this->config["schema"] = (isset($config["schema"]) ? $config["schema"] : false);
	}
	
	public function respond() {
		header('Access-Control-Allow-Origin: *');
		$callback = isset($_GET['callback']) ? preg_replace('/[^a-z0-9$_]/si', '', $_GET['callback']) : false;
		header('Content-Type: ' . ($callback ? 'application/javascript' : 'application/json') . ';charset=UTF-8');
		echo ($callback ? $callback . '(' : '') . json_encode($this->response) . ($callback ? ')' : '');
	}
	
	public function validate($variable, $method=false) {
		if ( empty($variable) ) {
			return false;
		}
		if ( !$method ) {
			$variable = htmlspecialchars($variable, ENT_QUOTES, "UTF-8");
			return $variable;
		}
		
		switch($method) {
			case "action":
				$allowedActions = array("get", "beacon");
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
			case "userID": {
				if ( preg_match("#^[A-Za-z0-9]+$#", $variable) ) {
					return $variable;
				} else {
					return false;
				}
			}
			case "userName": {
				$variable = htmlspecialchars(trim($variable), ENT_QUOTES, "UTF-8");
				if ( !empty($variable) && strlen($variable) > 0 && strlen($variable) < 120 ) {
					return $variable;
				} else {
					return false;
				}
			}
			case "int": {
				if ( !empty($variable) && strlen($variable) > 0 && strlen($variable) < 6 ) {
					return intval(htmlspecialchars(trim($variable), ENT_QUOTES, "UTF-8"));
				} else {
					return false;
				}
			}
			default:
				return false;
		}
		return false; // should not get here anyway
	}
	
	public function ClientAction() {
		$PARM_ACTION = (isset($_GET["action"]) ? self::validate($_GET["action"], "action") : false);
		if ( !$PARM_ACTION ) {
			throw new unlinked_API_Exception($this->errorList[1], 1);
		}
		$this->Controller = new Controller($this->config["host"], $this->config["port"], $this->config["user"], $this->config["pwd"], $this->config["schema"]);
		
		$PARM_USER = (isset($_GET["user"]) ? self::validate($_GET["user"], "userName") : false);
		$PARM_UPLOADS = (isset($_GET["uploads"]) ? self::validate($_GET["uploads"], "channelID") : false);
		$PARM_PAGE = (isset($_GET["page"]) ? self::validate($_GET["page"], "int") : false);
		$PARM_VIDEO = (isset($_GET["video"]) ? self::validate($_GET["video"], "videoID") : false);
		$PARM_CHANNEL = (isset($_GET["channel"]) ? self::validate($_GET["channel"], "channelID") : false);
		
		if ( $PARM_ACTION == "get" ) {
			if ( !$PARM_USER && !$PARM_UPLOADS && !$PARM_VIDEO && !$PARM_CHANNEL ) {
				throw new unlinked_API_Exception($this->errorList[1], 1);
			} else {
				if ( $PARM_USER ) {
					$ID = $this->Controller->channelGetID($PARM_USER);
					if ( !$ID ) {
						throw new unlinked_API_Exception($this->errorList[2], 2);
					}
					$Data = $this->Controller->channelGetInfo($ID);
					if ( !$Data ) {
						$Check = $this->Controller->channelDownloadInfo($ID);
						if ( $Check ) {
							$this->Controller->channelAdd($Check);
							$Data = $this->Controller->channelGetInfo($ID);
							if ( !$Data ) {
								throw new Controller_Exception("Fetching local data failed", 3);
							}
						} else {
							throw new Controller_Exception("Fetching remote data failed", 4);
						}
					}
					$this->response["channel"] = $Data;
				} elseif ( $PARM_UPLOADS ) {
					if ( $PARM_PAGE ) {
						$Data = $this->Controller->channelGetUploads($PARM_UPLOADS, $PARM_PAGE);
					} else {
						$Data = $this->Controller->channelGetUploads($PARM_UPLOADS);
					}
					if ( !$Data ) {
						throw new unlinked_API_Exception($this->errorList[2], 2);
					}
					$this->response["videos"] = $Data;
				} elseif ( $PARM_VIDEO && $PARM_CHANNEL ) {
					$Data = $this->Controller->videoGetInfo($PARM_VIDEO);
					if ( !$Data ) {
						$Check = $this->Controller->videoDownloadInfo($PARM_VIDEO);
						if ( $Check ) {
							$this->Controller->videoAdd($Check);
							$Data = $this->Controller->videoGetInfo($PARM_VIDEO);
							if ( !$Data ) {
								throw new Controller_Exception("Fetching local data failed", 3);
							}
						} else {
							throw new Controller_Exception("Fetching remote data failed", 4);
						}
					}
					$this->response["video"] = $Data;
					
					$Data = $this->Controller->channelGetInfo($PARM_CHANNEL);
					if ( !$Data ) {
						$Check = $this->Controller->channelDownloadInfo($PARM_CHANNEL);
						if ( $Check ) {
							$this->Controller->channelAdd($Check);
							$Data = $this->Controller->channelGetInfo($PARM_CHANNEL);
							if ( !$Data ) {
								throw new Controller_Exception("Fetching local data failed", 3);
							}
						} else {
							throw new Controller_Exception("Fetching remote data failed", 4);
						}
					}
					$this->response["channel"] = $Data;
					if ( $this->queueJobs ) {
						$this->Controller->addJob($PARM_CHANNEL, 2);
					}
				} elseif ( $PARM_VIDEO ) {
					$Data = $this->Controller->videoGetInfo($PARM_VIDEO);
					if ( !$Data ) {
						$Check = $this->Controller->videoDownloadInfo($PARM_VIDEO);
						if ( $Check ) {
							$this->Controller->videoAdd($Check);
							$Data = $this->Controller->videoGetInfo($PARM_VIDEO);
							if ( !$Data ) {
								throw new Controller_Exception("Fetching local data failed", 3);
							}
						} else {
							throw new Controller_Exception("Fetching remote data failed", 4);
						}
					}
					$this->response = array_merge($this->response, $Data);
					
					if ( $this->queueJobs ) {
						$ChannelExists = $this->Controller->channelExists($Data["channel_id"]);
						if ( !$ChannelExists ) {
							$this->Controller->addJob($Data["channel_id"], 2);
						}
					} else {
						$VideoData = $Data;
						$Data = $this->Controller->channelExists($VideoData["channel_id"]);
						if ( !$Data ) {
							$Check = $this->Controller->channelDownloadInfo($VideoData["channel_id"]);
							if ( $Check ) {
								$this->Controller->channelAdd($Check);
								$Data = $this->Controller->channelExists($VideoData["channel_id"]);
								if ( $this->queueJobs ) {
									$this->Controller->addJob($Check["items"][0]["id"], 2);
								}
								if ( !$Data ) {
									throw new Controller_Exception("Fetching local data failed", 3);
								}
							} else {
								throw new Controller_Exception("Fetching remote data failed", 4);
							}
						}
					}
				} elseif ( $PARM_CHANNEL ) {
					$Data = $this->Controller->channelGetInfo($PARM_CHANNEL);
					if ( !$Data ) {
						$Check = $this->Controller->channelDownloadInfo($PARM_CHANNEL);
						if ( $Check ) {
							$this->Controller->channelAdd($Check);
							$Data = $this->Controller->channelGetInfo($PARM_CHANNEL);
							if ( $this->queueJobs ) {
								$this->Controller->addJob($Check["items"][0]["id"], 2);
							}
							if ( !$Data ) {
								throw new Controller_Exception("Fetching local data failed", 3);
							}
						} else {
							throw new Controller_Exception("Fetching remote data failed", 4);
						}
					}
					$this->response = array_merge($this->response, $Data);
				} else {
					throw new unlinked_API_Exception($this->errorList[1], 1);
				}
			}
		} elseif ( $PARM_ACTION == "beacon" ) {
			if ( !$PARM_VIDEO ) {
				throw new unlinked_API_Exception($this->errorList[1], 1);
			}
			$Data = $this->Controller->videoGetInfo($PARM_VIDEO);
			if ( !$Data ) {
				$Check = $this->Controller->videoDownloadInfo($PARM_VIDEO);
				if ( $Check ) {
					$this->Controller->videoAdd($Check);
					$Data = $this->Controller->videoGetInfo($PARM_VIDEO);
					if ( !$Data ) {
						throw new Controller_Exception("Fetching local data failed", 3);
					}
				} else {
					throw new Controller_Exception("Fetching remote data failed", 4);
				}
			}
			if ( $this->queueJobs ) {
				$ChannelExists = $this->Controller->channelExists($Data["channel_id"]);
				if ( !$ChannelExists ) {
					$this->Controller->addJob($Data["channel_id"], 2);
				}
			} else {
				$VideoData = $Data;
				$Data = $this->Controller->channelExists($VideoData["channel_id"]);
				if ( !$Data ) {
					$Check = $this->Controller->channelDownloadInfo($VideoData["channel_id"]);
					if ( $Check ) {
						$this->Controller->channelAdd($Check);
						$Data = $this->Controller->channelExists($VideoData["channel_id"]);
						if ( !$Data ) {
							throw new Controller_Exception("Fetching local data failed", 3);
						}
					} else {
						throw new Controller_Exception("Fetching remote data failed", 4);
					}
				}
			}
		} else {
			throw new unlinked_API_Exception($this->errorList[1], 1);
		}
		$this->Controller->Close();
	}
}
?>