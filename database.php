<?php
class Controller_Exception extends Exception {
	public $code;
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
class Controller {
	public $db;
	public $errorCode;
	public $resultPerPage = 24;
	private $table_prefix = "unlinked_";
	public $debug = false;
	
	const ROOT_THUMBS = "/vi";
	const ROOT_BANNERS = "/u";
	const ROOT_AVATARS = "/i";
	
	public function __construct($host="localhost",$port=3306,$username="root",$password="",$database=false) {
		$this->db = new mysqli($host, $username, $password, $database, $port);
		if ( $this->db->connect_error ) {
			throw new Controller_Exception($this->db->connect_error, $this->db->connect_errno);
		}
		$this->db->set_charset("utf8");
		//self::install();
	}
	
	public function Close() {
		$this->db->close();
		return true;
	}
	/**
	* @access public
	* @return true | false
	*/
	public function install() {
		if ( !is_dir(SITE_ROOT . self::ROOT_THUMBS) ) {
			mkdir(SITE_ROOT .self::ROOT_THUMBS, 0777);
		}
		if ( !is_dir(SITE_ROOT . self::ROOT_BANNERS) ) {
			mkdir(SITE_ROOT . self::ROOT_BANNERS, 0777);
		}
		if ( !is_dir(SITE_ROOT . self::ROOT_AVATARS) ) {
			mkdir(SITE_ROOT . self::ROOT_AVATARS, 0777);
		}
		$query = "CREATE TABLE IF NOT EXISTS channels (id INTEGER PRIMARY KEY, userid TEXT, timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP, etag TEXT, channel_id TEXT, published TEXT, avatars TEXT, banners TEXT, title TEXT, description TEXT, views NUMERIC, subscribers NUMERIC, videos NUMERIC); CREATE TABLE IF NOT EXISTS videos (id INTEGER PRIMARY KEY, timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP, etag TEXT, video_id TEXT, published TEXT, title TEXT, description TEXT, thumb TEXT, channel_id TEXT, channel_name TEXT, category NUMERIC, duration TEXT, definition TEXT);";
		$this->db->multi_query($query);
		return true;
	}
	/**
	* @access public
	* @param mixed $videoID | $channelID, $mode = 1
	* @return true | false
	* @note $mode == 1 => video, $mode == 2 => channel
	*/
	public function addJob($job, $mode=1) {
		if ( is_writable(QUEUE_INPUT) && $mode == 1 || $mode == 2 ) {
			if ( $this->debug ) {
				echo "addJob(): added ", $job, PHP_EOL;
			}
			$job = $this->db->real_escape_string($job);
			if ( !file_exists(QUEUE_INPUT) ) {
				umask(0);
				if(!posix_mkfifo(QUEUE_INPUT,0666)) {
					return false;
				}
			}
			file_put_contents(QUEUE_INPUT, $mode.",".$job . PHP_EOL, FILE_APPEND);
			if ( $this->debug ) {
				file_put_contents(QUEUE_INPUT.".log", $mode.",".$job . PHP_EOL, FILE_APPEND);
			}
		} else {
			if ( $this->debug ) {
				echo "addJob(): failed to add ", $job, PHP_EOL;
			}
			return false;
		}
	}
	/**
	* @access public
	* @param string $job
	* @return true | false
	* @note $job = "mode,video-or-channel-id";
	*/
	public function processJob($job) {
		if ( !preg_match("#^[1-2],[A-Za-z0-9_-]+$#", $job) ) {
			//invalid format
			if ( $this->debug ) { echo "processJob(): invalid format of job", PHP_EOL; }
			return false;
		}
		$job = explode(",", $job);
		$mode = $job[0];
		$job = $job[1];
		if ( $mode == 1 ) {
		// video
			if ( $this->debug ) { echo "processJob(): downloading video info", PHP_EOL; }
			$Data = self::videoDownloadInfo($job);
			if ( !$Data ) { 
				if ( $this->debug ) { echo "processJob(): downloading data failed", PHP_EOL; }
				return false;
			}
			if ( $this->debug ) { echo "processJob(): calling method videoAdd()", PHP_EOL; }
			self::videoAdd($Data);
			if ( $this->debug ) { echo "processJob(): job done", PHP_EOL; }
		} else {
		// channel
			if ( $this->debug ) { echo "processJob(): downloading channel info", PHP_EOL; }
			$Data = self::channelDownloadUploadsID($job);
			if ( !$Data ) { 
				if ( $this->debug ) { echo "processJob(): downloading data failed", PHP_EOL; }
				return false;
			}
			if ( !self::channelExists($job) ) {
				$Channel = self::channelDownloadInfo($job);
				if ( $Channel ) {
					self::channelAdd($Channel);
					if ( $this->debug ) { echo "processJob(): addedd channel ", $job, PHP_EOL; }
				}
			}
			if ( $this->debug ) { echo "processJob(): processing channel data", PHP_EOL; }
			foreach($Data["items"] as $resource) {
				// extra check if we got correct data
				if ( $resource["id"]["kind"] == "youtube#video" ) {
					if ( $this->debug ) { echo "processJob(): calling method addJob()", PHP_EOL; }
					self::addJob($resource["id"]["videoId"], 1);
				}
			}
		}
	}
	/**
	* @access public
	* @param $name, $email, $message
	* @return true | false
	*/
	public function pushVisitorMessage($name=false,$email=false,$message=false) {
		if ( !$message || !$email || !$name ) { return false; }
		
		//echo "<pre>", $name, "\n", $email, "\n", $message, "</pre>", PHP_EOL;
		
		$name = $this->db->real_escape_string($name);
		$email = $this->db->real_escape_string($email);
		$message = $this->db->real_escape_string($message);
		$this->db->query("SET NAMES utf8;");
		$query = "INSERT INTO %svisitor_msg (name,email,message) VALUES ('%s','%s','%s');";
		$status = $this->db->query(sprintf($query, $this->table_prefix, $name, $email, $message));
		//echo "<pre>", sprintf($query, $this->table_prefix, $name, $email, $message), "</pre>", PHP_EOL;
		$message = str_replace('\r\n', "\r\n", $message);
		if ( $this->db->affected_rows == 1 ) {
			$to      = 'moh@yutsuku.net';
			$subject = 'unlinked - message from ' . $name;
			$msg = "unlinked daemon" . "\r\n\r\n" .
				"You have got new message:" . "\r\n" .
				"From: " . $name . "\r\n" .
				"E-Mail: " . $email . "\r\n" .
				"----------------------------------------------------------------------------" . "\r\n\r\n" .
				wordwrap(stripslashes(htmlspecialchars_decode($message)), 70, "\r\n\r\n") .
				"\r\n\r\n" .
				"----------------------------------------------------------------------------" . "\r\n" .
				"End of message" . "\r\n\r\n";
			$headers = 'From: admin@yutsuku.net' . "\r\n" .
				'Reply-To: ' . $email . "\r\n" .
				'X-Mailer: PHP/' . phpversion() . "\r\n" .
				'MIME-Version: 1.0' . "\r\n" . 
				'Content-type: text/plain; charset=UTF-8'. "\r\n" .
				'Content-Transfer-Encoding: 8bit';

			mail($to, $subject, $msg, $headers);
			return true;
		} else {
			return false;
		}
	}
	
	/**
	* @access public
	* @param mixed $channelID | $videoID, $type
	* @return true | false
	* @note $type == 1 => video, $type == 2 => channel
	*/
	public function pushCounter($id=false, $type=false) {
		if ( !$id || !$type ) { return false; }
		if ( !preg_match("#^[A-Za-z0-9_-]+$#", $id) || !preg_match("#^[1-2]$#", $type) ) { return false; }
		
		$query = "INSERT INTO %spageviews (pageid,type) VALUES ('%s','%d');";
		
		$id = $this->db->real_escape_string($id);
		$type = $this->db->real_escape_string($type);
		
		$status = $this->db->query(sprintf($query, $this->table_prefix, $id, $type));
		if ( $this->db->affected_rows == 1 ) {
			return true;
		} else {
			return false;
		}
	}
	/**
	* @access public
	* @param mixed $channelID | $videoID
	* @return int views | false
	*/
	public function updateCounter($id=false) {
		if ( !$id ) { return false; }
		if ( !preg_match("#^[A-Za-z0-9_-]+$#", $id) ) { return false; }
		
		$query = "UPDATE %spageviews SET count=count+1 WHERE pageid = '%s';";
		
		$id = $this->db->real_escape_string($id);
		
		$status = $this->db->query(sprintf($query, $this->table_prefix, $id));
		if ( $this->db->affected_rows == 0 ) {
			return false;
		} else {
			$query = "SELECT count FROM %spageviews WHERE pageid = '%s';";
			
			$status = $this->db->query(sprintf($query, $this->table_prefix, $id));
			if ( $this->db->affected_rows != 1 ) {
				return false;
			}
			while ($row = $status->fetch_array(MYSQLI_ASSOC)) {
				$data = $row["count"];
			}
			return $data;
		}
		
	}
	/**
	* @access public
	* @param $channelID
	* @return array | false
	* @note fetches ALL videos ids from youtube api from selected channel
	*/
	public function channelDownloadUploadsID($channel) {
		$channel = strip_tags(htmlspecialchars($channel));
		$json = file_get_contents(API_ENDPOINT . "/search?part=id&channelId=$channel&maxResults=25&order=date&type=video&key=" . API_KEY);
		$array = json_decode($json, true);
		if ( $array["pageInfo"]["totalResults"] == 0 ) {
			return false;
		} else {
			// get next page if needed
			if ( isset($array["nextPageToken"]) ) {
				$gotAll = false;
				$token = $array["nextPageToken"];
				$query = API_ENDPOINT . "/search?part=id&channelId=$channel&maxResults=25&order=date&pageToken=%s&type=video&key=" . API_KEY;
				while(!$gotAll) {
					$data = json_decode(file_get_contents(sprintf($query, $token)), true);
					foreach($data["items"] as $resource) {
						$array["items"][] = $resource;
					}
					if ( !isset($data["nextPageToken"]) ) {
						$gotAll = true;
					} else {
						$token = $data["nextPageToken"];
					}
				}
			}
			return $array;
		}
	}
	/**
	* @access public
	* @param $userID [, $precise=true [, $searchInTitle=false]]
	* @return channelID | array(channelID, ...) | false
	* @note if $searchInTitle is set to true then array will be returned instead of string
	*/
	public function channelGetID($username, $precise=true, $searchInTitle=false) {
		if ( $precise ) {
			$query = "SELECT channel_id FROM %schannels WHERE userid = '%s' LIMIT 1;";
		} else {
			if ( $searchInTitle ) {
				$query = "SELECT channel_id FROM %schannels WHERE userid LIKE '%%%s%%' OR title LIKE '%%%s%%';";
			} else {
				$query = "SELECT channel_id FROM %schannels WHERE userid LIKE '%%%s%%' LIMIT 1;";
			}
		}
		$username = $this->db->real_escape_string($username);
		if ( $searchInTitle ) {
			$status = $this->db->query(sprintf($query, $this->table_prefix, $username, $username));
		} else {
			$status = $this->db->query(sprintf($query, $this->table_prefix, $username));
		}
		if ( $status->num_rows == 0 ) return false;
		while ($row = $status->fetch_array()) {
			$data[] = $row["channel_id"];
		}
		
		if ( count($data) == 1 ) {
			$data = $data[0];
		}
		return $data;
	}
	/**
	* @access public
	* @param $title
	* @return array | false
	* @note index is as follow: 0, 1, 2, ..., 10, 11, ... while key is holding video ID
	*/
	public function searchVideoByTitle($title) {
		$query = "SELECT video_id FROM %svideos WHERE title LIKE '%%%s%%' ORDER BY timestamp DESC LIMIT %d;";
		// $this->resultPerPage
		$title = $this->db->real_escape_string($title);
		$status = $this->db->query(sprintf($query, $this->table_prefix, $title, $this->resultPerPage));
		if ( $this->db->affected_rows == 0 ) {
			return false;
		}
		while ($row = $status->fetch_array(MYSQLI_ASSOC)) {
			$data[] = $row["video_id"];
		}
		return $data;
	}
	/**
	* @access public
	* @param $channelID [, $page=1]
	* @return array | false
	*/
	public function channelGetUploads($id, $page=1, $sort="desc") {
		//SELECT * FROM videos WHERE channel_id = 'UCzPg4vEL37ZqXL47rJbBv9w' ORDER By id LIMIT <count> OFFSET <skip>
		$query = "SELECT timestamp,video_id,published,title,description,thumb,channel_id,channel_name,category,duration,definition FROM %svideos WHERE channel_id = '%s' ORDER By published %s LIMIT %d OFFSET %d;";
		$id = $this->db->real_escape_string($id);
		$sortType = $this->db->real_escape_string($sort);
		$page = intval($page);
		$offset = ($page-1) * $this->resultPerPage;
		$results = $this->db->query(sprintf("SELECT count(id) FROM %svideos WHERE channel_id = '%s';", $this->table_prefix, $id));
		while ($row = $results->fetch_array(MYSQL_NUM)) {
			$data["maxresults"] = $row[0];
		}
		if ( $data["maxresults"] == 0 ) { 
			return false;
		}
		if ( $offset > $data["maxresults"] ) {
			return false;
		}
		
		$data["pages"] = ceil($data["maxresults"] / $this->resultPerPage);
		$data["page"] = $page;
		$status = $this->db->query(sprintf($query, $this->table_prefix, $id, $sortType, $this->resultPerPage, $offset));
		while ($row = $status->fetch_array(MYSQLI_ASSOC)) {
			$row["thumb"] = json_decode($row["thumb"], true);
			foreach($row["thumb"] as $k=>$v) {
				$row["thumb"][$k] = SITE_URL . self::ROOT_THUMBS . "/" . $v;
			}
			$data[] = $row;
		}
		if ( !isset($data) ) {
			return false;
		} else {
			return $data;
		}
	}
	public function videoGetLatest($count=4) {
		$query = "SELECT id,timestamp,video_id,title,description,thumb,channel_id,channel_name,category,duration FROM %svideos ORDER BY timestamp DESC LIMIT %d;";
		$count = $this->db->real_escape_string(intval($count));
		$results = $this->db->query(sprintf($query, $this->table_prefix, $count));
		if ( $this->db->affected_rows == 0 ) {
			return false;
		}
		while ($row = $results->fetch_array(MYSQLI_ASSOC)) {
			$row["thumb"] = json_decode($row["thumb"], true);
			foreach($row["thumb"] as $k=>$v) {
				$row["thumb"][$k] = SITE_URL . self::ROOT_THUMBS . "/" . $v;
			}
			$data[] = $row;
		}
		return $data;
	}
	/**
	* @access public
	* @param array $array
	* @return true | false
	* @note sets $this->errorCode to 1 on failure (already exists)
	*/
	public function videoAdd($array) {
		$query = "INSERT INTO %svideos (etag,video_id,published,title,description,thumb,channel_id,channel_name,category,duration,definition) VALUES ('%s','%s','%s','%s','%s','%s','%s','%s',%d,'%s','%s');";
		$arraySimple = $array["items"][0];
		
		$id = $this->db->real_escape_string($arraySimple["id"]);
		if ( $this->debug ) { echo "videoAdd(): checking thumbnails", PHP_EOL; }
		$thumb = self::downloadVideoThumb($arraySimple["snippet"]["thumbnails"], true);
		if ( self::videoExists($id) && $thumb != false ) {
			if ( $this->debug ) { echo "videoAdd(): we already got all, aborting", PHP_EOL; }
			$this->errorCode = 1;
			return false;
		} else {
			// download thumbnail
			if ( $this->debug ) { echo "videoAdd(): downloading thumbnails", PHP_EOL; }
			$thumb = self::downloadVideoThumb($arraySimple["snippet"]["thumbnails"]);
			$thumb = json_encode($thumb);
			// prepare sql data
			$etag = $this->db->real_escape_string($arraySimple["etag"]);
			$published = $this->db->real_escape_string($arraySimple["snippet"]["publishedAt"]);
			$title = $this->db->real_escape_string($arraySimple["snippet"]["title"]);
			$description = $this->db->real_escape_string($arraySimple["snippet"]["description"]);
			$channel_id = $this->db->real_escape_string($arraySimple["snippet"]["channelId"]);
			$channel_name = $this->db->real_escape_string($arraySimple["snippet"]["channelTitle"]);
			$category = $this->db->real_escape_string($arraySimple["snippet"]["categoryId"]);
			$duration = $this->db->real_escape_string($arraySimple["contentDetails"]["duration"]);
			$definition = $this->db->real_escape_string($arraySimple["contentDetails"]["definition"]);
			
			if ( $this->debug ) { echo "videoAdd(): query: ", sprintf($query, $this->table_prefix, $etag, $id, $published, $title, $description, $thumb, $channel_id, $channel_name, $category, $duration, $definition), PHP_EOL; }
			$status = $this->db->query(sprintf($query, $this->table_prefix, $etag, $id, $published, $title, $description, $thumb, $channel_id, $channel_name, $category, $duration, $definition));
			if ( $this->debug ) { echo "videoAdd(): affected rows: ", $this->db->affected_rows, PHP_EOL; }
			self::pushCounter($id, 1);
			return true;
		}
	}
	/**
	* @access public
	* @param array $thumbnails [, bool $skipDownload=false]
	* @return true | false | array
	* @note if $skipDownload is set to false function will return array, otherwise it will return true if all files are present or false
	*/
	public function downloadVideoThumb($thumbnails, $skipDownload=false) {
		$localThumb = array();
		$skipDownloadExit = true;
		foreach($thumbnails as $key => $resource) {
			$filename = explode("/", $resource["url"]);
			$videoID = $filename[count($filename)-2];
			$filename = $filename[count($filename)-1];
			// just checking if we have thumbs in local system
			if ( !file_exists(SITE_ROOT . self::ROOT_THUMBS . "/" . $videoID . "/" . $filename) && $skipDownload == true ) {
				$skipDownloadExit = false;
				break;
			// download them if we dont have
			} elseif ( !file_exists(SITE_ROOT . self::ROOT_THUMBS . "/" . $videoID . "/" . $filename) && $skipDownload == false ) {
				if ( !is_dir(SITE_ROOT . self::ROOT_THUMBS . "/" . $videoID) ) {
					mkdir(SITE_ROOT . self::ROOT_THUMBS . "/" . $videoID, 0777);
				}
				file_put_contents(SITE_ROOT . self::ROOT_THUMBS . "/" . $videoID . "/" . $filename, file_get_contents($resource["url"]), LOCK_EX);
				$localThumb[$key] = $videoID . "/" . $filename;
			// we already have them
			} else {
				$localThumb[$key] = $videoID . "/" . $filename;
			}
		}
		if ( $skipDownload ) {
			return $skipDownloadExit;
		} else {
			return $localThumb;
		}
	}
	
	/**
	* @access public
	* @param string $id
	* @return true | false
	*/
	public function videoExists($id) {
		$query = "SELECT id FROM %svideos WHERE video_id = '%s' LIMIT 1;";
		$id = $this->db->real_escape_string($id);
		$status = $this->db->query(sprintf($query, $this->table_prefix, $id));
		while ($row = $status->fetch_array()) {
			$data = $row;
		}
		if ( isset($data["id"]) ) {
			return true;
		} else {
			return false;
		}
	}
	/**
	* @access public
	* @param string $id
	* @return array | false
	* @note sets $this->errorCode to 2 on failure (no info about video)
	*/
	public function videoGetInfo($id) {
		$query = "SELECT timestamp,video_id,published,title,description,thumb,channel_id,channel_name,category,duration,definition FROM %svideos WHERE video_id = '%s' LIMIT 1;";
		$id = $this->db->real_escape_string($id);
		if ( self::videoExists($id) ) {
			$status = $this->db->query(sprintf($query, $this->table_prefix, $id));
			while ($row = $status->fetch_array(MYSQLI_ASSOC)) {
				$data = $row;
			}
			$data["thumb"] = json_decode($data["thumb"], true);
			foreach($data["thumb"] as $k=>$v) {
				$data["thumb"][$k] = SITE_URL . self::ROOT_THUMBS . "/" . $v;
			}
			return $data;
		} else {
			$this->errorCode = 2;
			return false;
		}
	}
	/**
	* @access public
	* @param string $VIDEO_ID
	* @return array | false
	*/
	public function videoDownloadInfo($id) {
		$id = strip_tags(htmlspecialchars($id));
		$json = file_get_contents(API_ENDPOINT . "/videos?part=snippet%2C+contentDetails&id=$id&key=" . API_KEY);
		$array = json_decode($json, true);
		if ( $this->debug ) { echo "videoDownloadInfo(): URI: ", API_ENDPOINT . "/videos?part=snippet%2C+contentDetails&id=$id&key=" . API_KEY, PHP_EOL;
		}
		if ( $array["pageInfo"]["totalResults"] == 0 ) {
			return false;
		} else {
			return $array;
		}
	}
	/**
	* @access public
	* @param string $CHANNEL_ID
	* @return array | false
	*/
	public function channelDownloadInfo($id) {
		$id = strip_tags(htmlspecialchars($id));
		$json = file_get_contents(API_ENDPOINT . "/channels?part=snippet%2Cstatistics%2CbrandingSettings&id=$id&key=" . API_KEY);
		$json1 = file_get_contents(API_ENDPOINT . "/search?part=snippet&channelId=$id&maxResults=1&key=" . API_KEY);
		$array0 = json_decode($json, true);
		$array1 = json_decode($json1, true);
		if ( $array0["pageInfo"]["totalResults"] == 0 || $array1["pageInfo"]["totalResults"] == 0 ) {
			return false;
		} else {
			$array0["userID"] = $array1["items"][0]["snippet"]["channelTitle"];
			return $array0;
		}
	}
	/**
	* @access public
	* @param string $CHANNEL_ID
	* @return array | false
	*/
	public function channelGetInfo($id) {
		$query = "SELECT published,avatars,banners,title,userid,description,views,subscribers,videos FROM %schannels WHERE channel_id = '%s' LIMIT 1;";
		$id = $this->db->real_escape_string($id);
		$status = $this->db->query(sprintf($query, $this->table_prefix, $id));
		while ($row = $status->fetch_array(MYSQLI_ASSOC)) {
				$data = $row;
		}
		if ( !isset($data) ) {
			return false;
		}
		$data["avatars"] = json_decode($data["avatars"], true);
		$data["banners"] = json_decode($data["banners"], true);
		foreach($data["avatars"] as $k=>$v) {
			$data["avatars"][$k] = SITE_URL . self::ROOT_AVATARS . "/" . $v;
		}
		foreach($data["banners"] as $k=>$v) {
			$data["banners"][$k] = SITE_URL . self::ROOT_BANNERS . "/" . $v;
		}
		return $data;
	}
	/**
	* @access public
	* @param string $CHANNEL_ID
	* @return true | false
	*/
	public function channelExists($id) {
		$query = "SELECT id from %schannels WHERE channel_id = '%s' LIMIT 1;";
		$id = $this->db->real_escape_string($id);
		try {
			$status = $this->db->query(sprintf($query, $this->table_prefix, $id));
		} catch (Exception $e) {
			$status = $this->db->query(sprintf($query, $this->table_prefix, $id));
			if ( !$status ) {
				//throw new Controller_Exception("query failed for " . $id, 1);
				return $this->channelExists($id);
			}
		}
		while ($row = $status->fetch_array(MYSQLI_ASSOC)) {
				$data = $row;
		}
		if ( !isset($data) ) {
			return false;
		}
		return true;
	}
	/**
	* @access public
	* @param array $channel
	* @return true | false
	*/
	public function channelAdd($array) {
		$arraySimple = $array["items"][0];
		$id = $this->db->real_escape_string($arraySimple["id"]);
		$avatars = self::downloadChannelImage($arraySimple["snippet"]["thumbnails"], $id, true);
		$banners = self::downloadChannelImage($arraySimple["brandingSettings"]["image"], $id, true);
		
		if ( self::channelGetInfo($id) &&  $avatars != false && $banners != false ) {
			$this->errorCode = 1;
			return false;
		} else {
			$query = "INSERT INTO %schannels (etag,channel_id,published,avatars,banners,title,userid,description,views,subscribers,videos) VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d');";
			$avatars = json_encode(self::downloadChannelImage($arraySimple["snippet"]["thumbnails"], $id));
			$banners = json_encode(self::downloadChannelImage($arraySimple["brandingSettings"]["image"], $id));
			$etag = $this->db->real_escape_string($arraySimple["etag"]);
			$title = $this->db->real_escape_string($arraySimple["snippet"]["title"]);
			$userid = $this->db->real_escape_string($array["userID"]);
			$description = $this->db->real_escape_string($arraySimple["snippet"]["description"]);
			$published = $this->db->real_escape_string($arraySimple["snippet"]["publishedAt"]);
			$viewCount = $this->db->real_escape_string($arraySimple["statistics"]["viewCount"]);
			$subscriberCount = $this->db->real_escape_string($arraySimple["statistics"]["subscriberCount"]);
			$videoCount = $this->db->real_escape_string($arraySimple["statistics"]["videoCount"]);
			$status = $this->db->query(sprintf($query, $this->table_prefix, $etag, $id, $published, $avatars, $banners, $title, $userid, $description, $viewCount, $subscriberCount, $videoCount));
			self::pushCounter($id, 2);
			return true;
		}
	}
	/**
	* @access public
	* @param array $images, string $id [, $skipDownload=false]
	* @return By default will return array with pathes to files as provided by array $images. If $skipDownload is set to TRUE it will return true if no image is missing or false
	*/
	public function downloadChannelImage($images, $id, $skipDownload=false) {
		// download only the "main" banner used by channel on standard site (PC)
		$acceptKeys = array("bannerImageUrl");
		$type = 0; // 0 - avatar, 1 - banner
		$localImages = array();
		$skipDownloadExit = true;
		// detect if array contains avatars or banners
		foreach($images as $key => $resource) {
			if ( isset($resource["url"]) ) {
				$type = 0;
				break;
			} elseif ( $key == "bannerImageUrl" ) {
				$type = 1;
				break;
			}
		}
		if ( $type == 0 ) {
			// avatars
			foreach($images as $key => $resource) {
				$filename = explode("/", $resource["url"]);
				$filename = $filename[count($filename)-1];
				// just checking if we have thumbs in local system
				if ( !file_exists(SITE_ROOT . self::ROOT_AVATARS . "/" . $id . "/" . $filename) && $skipDownload == true ) {
					$skipDownloadExit = false;
					break;
				// download them if we dont have
				} elseif ( !file_exists(SITE_ROOT . self::ROOT_AVATARS . "/" . $id . "/" . $filename) && $skipDownload == false ) {
					if ( !is_dir(SITE_ROOT . self::ROOT_AVATARS . "/" . $id) ) {
						mkdir(SITE_ROOT . self::ROOT_AVATARS . "/" . $id, 0777);
					}
					file_put_contents(SITE_ROOT . self::ROOT_AVATARS . "/" . $id . "/" . $filename, file_get_contents($resource["url"]), LOCK_EX);
					$localImages[$key] = $id . "/" . $filename;
				// we already have them
				} else {
					$localImages[$key] = $id . "/" . $filename;
				}
			}
		} else {
			// banners
			foreach($images as $key => $resource) {
				// skip current key if not accepted
				if ( array_search($key, $acceptKeys) === false ) {
					continue;
				}
				$filename = explode("/", $resource);
				$filename = $filename[count($filename)-1];
				$filename = explode("?", $filename); // blahblah.jpg?v=522fcd5d
				$filename = $filename[0];
				// just checking if we have thumbs in local system
				if ( !file_exists(SITE_ROOT . self::ROOT_BANNERS . "/" . $id . "/" . $filename) && $skipDownload == true ) {
					$skipDownloadExit = false;
					break;
				// download them if we dont have
				} elseif ( !file_exists(SITE_ROOT . self::ROOT_BANNERS . "/" . $id . "/" . $filename) && $skipDownload == false ) {
					if ( !is_dir(SITE_ROOT . self::ROOT_BANNERS . "/" . $id) ) {
						mkdir(SITE_ROOT . self::ROOT_BANNERS . "/" . $id, 0777);
					}
					file_put_contents(SITE_ROOT . self::ROOT_BANNERS . "/" . $id . "/" . $filename, file_get_contents($resource), LOCK_EX);
					$localImages[$key] = $id . "/" . $filename;
				// we already have them
				} else {
					$localImages[$key] = $id . "/" . $filename;
				}
			}
		}
		if ( $skipDownload ) {
			return $skipDownloadExit;
		} else {
			if ( empty($localImages) ) {
				return false;
			} else {
				return $localImages;
			}
		}
	}
}
?>