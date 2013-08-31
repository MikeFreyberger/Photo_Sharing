
<?php

    //include the libary 
    if (!defined("PATH_SEPARATOR")) { 
    	if (strpos($_ENV["OS"], "Win") !== FALSE) { 
    		define("PATH_SEPARATOR", ";");
    	} else { 
    		define("PATH_SEPARATOR", ":"); 
    	}
    }
    ini_set('include_path',ini_get('include_path').PATH_SEPARATOR.BASEPATH.'../application/libraries/');


    class Imagegallery extends CI_Controller {
	

	// --------------------------------------------------------------------
	
	/**
	 *	The constructor
	 */
	function __construct()
	{
		
		parent::__construct();	
		$this->load->helper('file');
		$this->load->library('email');
		//$this->load->model('put_your_model_here');
		
		
		//include the Zend GData Loader	
		require_once 'Zend/Loader.php';
		Zend_Loader::loadClass('Zend_Gdata');
		Zend_Loader::loadClass('Zend_Gdata_AuthSub');
		Zend_Loader::loadClass('Zend_Gdata_Photos');
		Zend_Loader::loadClass('Zend_Gdata_Photos_UserQuery');
		Zend_Loader::loadClass('Zend_Gdata_Photos_AlbumQuery');
		Zend_Loader::loadClass('Zend_Gdata_Photos_PhotoQuery');
		Zend_Loader::loadClass('Zend_Gdata_App_Extension_Category');
		Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
		Zend_Loader::loadClass('Zend_Gdata_Media_Extension_MediaKeywords');
		Zend_Loader::loadClass('Zend_Gdata_Geo_Extension_GeoRssWhere');
		Zend_Loader::loadClass('Zend_Gdata_Geo_Extension_GmlPos');
		Zend_Loader::loadClass('Zend_Gdata_Geo_Extension_GmlPoint');

	}
	
	// --------------------------------------------------------------------
	
	
	
	//this function can be used to determine
    //the album title based on the from email.
    //make a call to your model with this method
	function setAlbumName($email) {
		//make call to model
        return $albumName;

	}
	
	
	//return array of mail numbers 
    //that are unread
	function getNewMessages($inbox) {
		return imap_search($inbox, 'UNSEEN');
	}
	
	//return the tags by parsing
	//through the subject.
    //new tags are identified by a space
    //or commaa
	function getTags($inbox, $mail) {
		$header = imap_header($inbox, $mail);
		if (isset($header->subject)) {
			$subject = $header->subject;
			$tags = preg_split("/[\s,]+/", $subject);
			return $tags;
		}
		else
			return null;
	}
	
	//return the body of the email
	//parse out info after '--' in order
	//to avoid signatures, then remove any
    //html tags on body
	function getCaption($connection, $emailnumber) {
		$bodyText = imap_fetchbody($connection,$emailnumber,1.2);
		if(!strlen($bodyText)>0){
				$bodyText = imap_fetchbody($connection,$emailnumber,1);
		}
		$body = explode('--', $bodyText, 2);
		$caption = $body[0];
		return strip_tags($caption);
	}
	
	
	//return the sender email address
	//of message object
	function getFromAddress($inbox, $message) {
		$header = imap_header($inbox, $message);
		$personal = $header->from[0]->mailbox;
		$host = $header->from[0]->host;
		$email = "$personal" . "@" . "$host"; 
		return $email;
	}
	
	//return array of pictures from
	//message object
	//return attachments("is_attachment" => '1' (true)
	//									 "filename" => (path)
	//									 "attachment"=> (data))
	function getAttachments($inbox, $message) {
		$structure = imap_fetchstructure($inbox, $message);
		$attachments = array();
		if(isset($structure->parts) && count($structure->parts)) {

			for($i = 0; $i < count($structure->parts); $i++) {

				$attachments[$i] = array(
					'is_attachment' => false,
					'filename' => '',
					'name' => '',
					'attachment' => ''
				);
				
				if($structure->parts[$i]->ifdparameters) {
					foreach($structure->parts[$i]->dparameters as $object) {
						if(strtolower($object->attribute) == 'filename') {
							$attachments[$i]['is_attachment'] = true;
							$attachments[$i]['filename'] = $object->value;
						}
					}
				}
				
				if($structure->parts[$i]->ifparameters) {
					foreach($structure->parts[$i]->parameters as $object) {
						if(strtolower($object->attribute) == 'name') {
							$attachments[$i]['is_attachment'] = true;
							$attachments[$i]['name'] = $object->value;
						}
					}
				}
				
				if($attachments[$i]['is_attachment']) {
					$attachments[$i]['attachment'] = imap_fetchbody($inbox, $message, $i+1);
					if($structure->parts[$i]->encoding == 3) { // 3 = BASE64
						$attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
					}
					elseif($structure->parts[$i]->encoding == 4) { // 4 = QUOTED-PRINTABLE
						$attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
					}
				}
			}
		}
		return $attachments;
	}
	
	//send email to chaperone
	//confirming their upload
	function sendEmail($email) {
		$this->email->from('your@example.com', 'Your Name');
		$this->email->to($email); 

		$this->email->subject('Upload confirmed!');
		$this->email->message('Some message to confirm the upload...');	

		$this->email->send();

		//echo $this->email->print_debugger();
	}
	
	//return boolean to see if attachments
	//are actually present in the email
	function attachmentsExist($attachments) {
		foreach($attachments as $file) {
			if($file['is_attachment']) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Adds a new album to the specified user's album
	 *
	 * @param  Zend_Http_Client $client The authenticated client
	 * @param  string           $name   The name of the new album
	 * @return void
	 */
	function addAlbum($client, $name){
		$photos = new Zend_Gdata_Photos($client);
		$entry = new Zend_Gdata_Photos_AlbumEntry();
		$entry->setTitle($photos->newTitle($name));

		$result = $photos->insertAlbumEntry($entry);
		if ($result) {
			//outputUserFeed($client, $user);
			//echo "Album created";
		} 
		else {
			//echo "There was an issue with the album creation.";
		}
	}
	

	
	/**
	 * Adds a new photo to the specified album
	 *
	 * @param  Zend_Http_Client $client The authenticated client
	 * @param  string           $user     The user's account name
	 * @param  integer          $albumId  The album's id
	 * @param  string           $filename Path to the photo
	 * @param  string						$title 		Title of photo
	 * @param  array						$tags		Tags to be added to the photo
	 * @param  string						$caption	Caption to be added to the photo
	 * @return void
	 */
	function addPhoto($client, $user, $albumId, $filename, $title, $tags, $caption) {
		$photos = new Zend_Gdata_Photos($client);
		$fd = $photos->newMediaFileSource($filename);
		$fd->setContentType("image/jpeg");

		$entry = new Zend_Gdata_Photos_PhotoEntry();
		$entry->setMediaSource($fd);
		$entry->setTitle($photos->newTitle($title));
		$entry->setSummary($photos->newSummary($caption));

		$albumQuery = new Zend_Gdata_Photos_AlbumQuery;
		$albumQuery->setUser($user);
		$albumQuery->setAlbumId($albumId);

		$albumEntry = $photos->getAlbumEntry($albumQuery);

		$result = $photos->insertPhotoEntry($entry, $albumEntry);
		if (!$result) {
			echo "There was an issue with the file upload.";
		} 
		elseif ($tags) {
		foreach($tags as $tag) {
			$newtag = $photos->newTagEntry();
			$newtag->setTitle($photos->newTitle($tag));
			$createdTag = $photos->insertTagEntry($newtag, $result);
			
			}
		}					
		
	}
	
	 
	 /**
	 * Adds a new comment to the specified photo
	 *
	 * @param  Zend_Http_Client $client The authenticated client
	 * @param  string           $user    The user's account name
	 * @param  integer          $albumId The album's id
	 * @param  integer          $photoId The photo's id
	 * @param  string           $comment The comment to add
	 * @return void
	 */
	function addComment($client, $user, $album, $photo, $comment) {
		$photos = new Zend_Gdata_Photos($client);
		$entry = new Zend_Gdata_Photos_CommentEntry();
		$entry->setTitle($photos->newTitle($comment));
		$entry->setContent($photos->newContent($comment));

		$photoQuery = new Zend_Gdata_Photos_PhotoQuery;
		$photoQuery->setUser($user);
		$photoQuery->setAlbumId($album);
		$photoQuery->setPhotoId($photo);
		$photoQuery->setType('entry');

		$photoEntry = $photos->getPhotoEntry($photoQuery);

		$result = $photos->insertCommentEntry($entry, $photoEntry);
		if ($result) {
				//outputPhotoFeed($client, $user, $album, $photo);
		} else {
				echo "There was an issue with the comment creation.";
		}
	}
	 
	//return boolean for album already exists
	function albumExists($client, $user, $albumName) {
		$photos = new Zend_Gdata_Photos($client);
		$query = new Zend_Gdata_Photos_AlbumQuery();
		$query->setUser($user);
		$query->setAlbumName($albumName);
		try {
			$albumFeed = $photos->getAlbumFeed($query);
			return true;
		}
		catch (Zend_Gdata_App_Exception $e) {
			return false;
		}	

	}	 
	
	//returns an understandable location
	//for photo data that could 
	//attached to the photo.
	// @param GPhoto_Photo_Entry $entry
	// @return string ['formatted_address'] detailed loc data in english
	function getPhotoLocation($entry) {
		if (isset($entry->geoRssWhere)) {
			$where = $entry->geoRssWhere;
			$point = $where->point;
			$pos = $point->pos;
			$pos = str_replace(' ', ',', $pos);
			// set your API key here
			//$api_key = "";
			// format this string with the appropriate latitude longitude
			//$url = 'http://maps.googleapis.com/maps/api/geocode/json?latlng=37.794,-122.48&sensor=true';
			$url = 'http://maps.googleapis.com/maps/api/geocode/json?latlng=' . $pos . '&sensor=true';
			// make the HTTP request
			$loc = @file_get_contents($url);
			// parse the json response
			$jsondata = json_decode($loc,true);
			// if we get a placemark array and the status was good, get the addres
			if(is_array($jsondata) && $jsondata['status'] == 'OK')
			{
				return $jsondata['results'][0]['formatted_address'];
			}
			else	
				//echo "test2";
		}
		else 
			return '';
	}

	//main function that should be triggered
	//on new email or via a time trigger
	//connects to email and Picasa
	//grabs attachments and puts them
	//in the appropriate albums
	function updatePhotos() {	
			/*user and pass info*/
		$username = //your username for Google Plus Photos/Picasa Web Albums, and Gmail Account
		$password = //your password for Google Plus Photos/Picasa Web Albums, and Gmail Account
		$subject = 'test';
					/* connect to gmail */
		$hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
		$inbox = imap_open($hostname,$username,$password) or die('Cannot connect to Gmail: ' . imap_last_error());
				/*connect to picasa*/
		$serviceName = Zend_Gdata_Photos::AUTH_SERVICE_NAME;
		$client = Zend_Gdata_ClientLogin::getHttpClient($username, $password, $serviceName);
		$albumName = '';
		$photos = new Zend_Gdata_Photos($client);
		$newMessages = $this->getNewMessages($inbox);
		if($newMessages) {
		foreach($newMessages as $mail) {
			$email = $this->getFromAddress($inbox, $mail);
			$caption = $this->getBody($inbox, $mail);
			$attachments = $this->getAttachments($inbox, $mail);
			
			
			//if attachments exist, add to Picasa Account
			if($this->attachmentsExist($attachments)){
				//$albumName = $this->setAlbumName($email);
				$albumName = "sample_album_name";
				$tags = $this->getTags($inbox, $mail);
				$query = new Zend_Gdata_Photos_AlbumQuery();
				$query->setUser($username);
				$query->setAlbumName($albumName);
				
				try {
					$exists = $this->albumExists($client, $username, $albumName);
					
					//get the albumId if exists
					//make the album and get the new
					//albumid if does not exist
					if ($exists) {
						//if the album exists, get album Id
						$albumFeed = $photos->getAlbumFeed($query);
						$albumId = $albumFeed->getGphotoId();
					}
					else {
						$this->addAlbum($client, $albumName);
						$albumFeed = $photos->getAlbumFeed($query);
						$albumId = $albumFeed->getGphotoId();
					}

					//foreach attachment in the message
					//add the photo to the server
					//copy photo from server to Picasa
					//remove photo from server
					$thisRoundCount = 0;
					foreach($attachments as $at){
						if($at['is_attachment']==1) {
							$currentPhotoCount++;
							$thisRoundCount++;
							$title = $at['filename'];
							$contents = $at['attachment'];
                            $dir = /*YOUR_DIRECTORY*/
							$filename = $dir . $title;
							file_put_contents($filename, $contents);
							$this->addPhoto($client, $username, $albumId, $filename, $title, $tags, $caption); 
							unlink($filename);
						}
					}
					$this->sendEmail($email);
				}
								
				catch (Zend_Gdata_App_Exception $e) {
					echo "Error: " . $e->getMessage() . "<br />\n"; 
				}		
			}
         }
		}
		else {
			//echo "no new messages";
		}
		/* close the connection */
		imap_close($inbox);
	}
  
  function index() {
    $this->updatePhotos();
  }
}
?>
