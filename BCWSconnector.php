<?php
require_once("oauth/OAuth.php");

$categories = [
    1=> "Real EstateProductivity",
    2=> "Computer and Internet SecurityProductivity",
    3=> "Financial ServicesProductivity",
    4=> "Business and EconomyProductivity",
    5=> "Computer and Internet InfoProductivity",
    6=> "AuctionsProductivity",
    7=> "ShoppingProductivity",
    8=> "Cult and OccultLegal Liability",
    9=> "TravelProductivity",
    10=> "Abused DrugsProductivity",
    11=> "Adult and PornographyLegal Liability",
    12=> "Home and GardenProductivity",
    13=> "MilitaryProductivity",
    14=> "Social NetworkProductivity",
    15=> "Dead Sites (db Ops only)",
    16=> "Stock Advice and ToolsProductivity",
    17=> "Training and ToolsProductivity",
    18=> "Dating",
    19=> "Sex EducationLegal Liability",
    20=> "ReligionLegal Liability",
    21=> "Entertainment and ArtsProductivity",
    22=> "Personal sites and BlogsProductivity",
    23=> "LegalProductivity",
    24=> "Local InformationProductivity",
    25=> "Streaming MediaIT Resources",
    26=> "Job SearchProductivity",
    27=> "GamblingLegal Liability",
    28=> "TranslationProductivity",
    29=> "Reference and ResearchProductivity",
    30=> "Shareware and FreewareIT Resources",
    31=> "Peer to PeerIT Resources",
    32=> "MarijuanaProductivity",
    33=> "Hacking",
    34=> "GamesProductivity",
    35=> "Philosophy and Political AdvocacyProductivity",
    36=> "WeaponsProductivity",
    37=> "Pay to SurfIT Resources",
    38=> "Hunting and FishingProductivity",
    39=> "SocietyProductivity",
    40=> "Educational InstitutionsProductivity",
    41=> "Online Greeting cardsIT Resources",
    42=> "SportsProductivity",
    43=> "Swimsuits and Intimate ApparelLegal Liability",
    44=> "QuestionableLegal Liability",
    45=> "KidsProductivity",
    46=> "Hate and RacismLegal Liability",
    47=> "Personal StorageIT Resources",
    48=> "ViolenceLegal Liability",
    49=> "Keyloggers and MonitoringIT Resources",
    50=> "Search EnginesProductivity",
    51=> "Internet PortalsProductivity",
    52=> "Web AdvertisementsIT Resources",
    53=> "CheatingIT Resources",
    54=> "GrossIT Resources",
    55=> "Web based emailIT Resources",
    56=> "Malware SitesSecurity",
    57=> "Phishing and Other FraudsSecurity",
    58=> "Proxy Avoid and AnonymizersSecurity",
    59=> "Spyware and AdwareSecurity",
    60=> "MusicProductivity",
    61=> "GovernmentProductivity",
    62=> "NudityLegal Liability",
    63=> "News and MediaProductivity",
    64=> "IllegalProductivity",
    65=> "CDNs",
    66=> "Internet CommunicationsIT Resources",
    67=> "Bot NetsSecurity",
    68=> "AbortionLegal Liability",
    69=> "Health and MedicineProductivity",
    70=> "Confirmed SPAM SourcesSecurity",
    71=> "SPAM URLsSecurity",
    72=> "Unconfirmed SPAM SourcesSecurity",
    73=> "Open HTTP ProxiesSecurity",
    74=> "Dynamic CommentProductivity",
    75=> "Parked DomainsProductivity",
    76=> "Alcohol and TobaccoProductivity",
    77=> "Private IP AddressesProductivity",
    78=> "Image and Video SearchProductivity",
    79=> "Fashion and BeautyProductivity",
    80=> "Recreation and HobbiesProductivity",
    81=> "Motor VehiclesProductivity",
    82=> "Web Hosting SitesProductivity",
    83=> "Food and DiningProductivity"
];
/*
********************************************** VARIABILI ******************************************
*/
//definisco la variabile globale
$GLOBALS["buffer"] = "";
$targeturl = $_GET['key'];
//definisco il metodo per la richiesta
$http_method = 'GET';
//Estrazione URL dalla richiesta proveniente da GRAYLOG e costruzione dell'url da chiamare
$APIurl = 'http://thor.brightcloud.com/rest/uris/'.$targeturl;
//Chiavi applicazione FSA
$consumer_key = 'yourkey';
$consumer_secret = 'yoursecret';
//forzo l'applicazione a mandare la request
$action = 'sign_send';
$postdata = null;
$dbhost='localhost:3306';
$dbuser='youruser';
$dbpass='yourpassword';
$dbname='yourdb';

/*
********************************************** CODICE ******************************************
*/
//controllo se l'url è già nel DB
$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
$result = $mysqli->query("SELECT url FROM url_cache WHERE url LIKE '".$targeturl."';");
//il seguente if restituisce la categoria ed esce nel caso in cui la query sopra da come risultato una row
if($result->num_rows == 1) {
    $query = $mysqli->query("SELECT category FROM url_cache WHERE url LIKE '".$targeturl."';");
    $obj = mysqli_fetch_object($query);
    print $obj->category;
    exit(0);
} 
$mysqli->close();



// Establish an OAuth Consumer based on read credentials
$consumer = new OAuthConsumer($consumer_key, $consumer_secret, NULL);

// Setup OAuth request
$oauth_request = OAuthRequest::from_consumer_and_token($consumer, NULL, $http_method, $APIurl, NULL);

//Sign the constructed OAuth request using HMAC-SHA1  
$sign_method = new OAuthSignatureMethod_HMAC_SHA1();
$oauth_request->sign_request($sign_method, $consumer, NULL);
$oauth_header = $oauth_request->to_header();

// Break-up service endpoint into various URL components to be used for sending request to server
$parts = parse_url($APIurl);

$scheme = $parts['scheme'];
$host = $parts['host'];
$port = @$parts['port'];
$port or $port = ($scheme == 'https') ? '443' : '80';
$path = @$parts['path'];

// Generate signed OAuth request for the BCWS server
$http_request = generate_request($http_method, $scheme, $host, $port, $path, $oauth_header, $postdata, "1.0");
print($postdata);

// Send signed OAuth request to the BCWS server
$fp = fsockopen ($host, $port, $errno, $errstr); 
if($fp){
    //eseguo la richiesta
    fwrite($fp, $http_request);
    //leggo la risposta
    read_response($fp);
    $xml = simplexml_load_string($GLOBALS["buffer"]);
    $catid = $xml->response->categories->cat->catid;
    $cat_description = $categories[(int)$catid];
    fclose($fp);
    //il seguente comando è quello che dà la risposta a graylog sencondo le specifiche dello stesso
    $output = json_encode(["category" => $cat_description]);
    print($output);
    //registro il risultato nel DB locale
    $mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
    $mysqli->query("INSERT INTO url_cache (url,category) VALUES ('".$targeturl."','".$output."');");
    $mysqli->close();
} else {
    print "Fatal error\n";
}
exit(0);


/* ********************************************** FUNCTIONS ****************************************** */

function generate_request($http_method, $scheme, $host, $port, $path, $oauth_header, $postdata = NULL, $http_version = "1.0"){

  $http_req = strtoupper($http_method). " $path ". strtoupper($scheme). "/$http_version\r\n";
  $http_req .= "HOST: $host:$port\r\n";

  if($http_version == "1.0") {
    $http_req .= "Connection: close\r\n";
  } else if($http_version == "1.1") {
    $http_req .= "Connection: Keep-Alive\r\n";
  }

  $http_req .= "$oauth_header\r\n";

  if(($http_method == 'PUT' || $http_method == 'POST')){
    if(!is_null($postdata)){
      $http_req .= "Content-Type: text/xml\r\n";
      $http_req .= "Content-Length: ". strlen($postdata). "\r\n";
      $http_req .= "\r\n";
      $http_req .= $postdata;
    }
  } 

  $http_req .= "\r\n";
  return $http_req;
}

function read_response(&$fp){

  $chunked = false;
  $content_length = 0;

  read_response_headers($fp, $content_length, $chunked);
  if(!$chunked){
    read_regular_body($fp, $content_length);
  } else{
    
    read_chunked_body($fp, $content_length);
  }
}

function read_response_headers(&$fp, &$content_length, &$chunked){

  $HEADER_CONTENT_LENGTH = "Content-Length: ";
  $HEADER_TRANSFER_ENCODING = "Transfer-Encoding: chunked";
  
  $content_length = 0;
  $chunked = false;

  while (!feof($fp)) {

    $header_line = fgets($fp);
    $trimmed_header_line = trim($header_line, "\r\n ");

    if($content_length == 0 && stripos($trimmed_header_line, $HEADER_CONTENT_LENGTH) === 0){
      $content_length = substr($trimmed_header_line, strlen($HEADER_CONTENT_LENGTH));
    }

    if(!$chunked && strcasecmp($trimmed_header_line, $HEADER_TRANSFER_ENCODING) == 0){
      $chunked = true;
    }

    if(strlen($trimmed_header_line) == 0){
      break;
    }

    header($header_line);
  }
}

function read_regular_body(&$fp, $content_length){

  $curr_len = 0;
  while (!feof($fp)) {
    if($content_length > 0){
      $buffer = fread($fp, $content_length - $curr_len);
      $GLOBALS["buffer"] .= $buffer;
      $curr_len += strlen($buffer);
      if($curr_len >= $content_length){
        break;
      }
    } else {
      $buffer = fgets($fp);
      $GLOBALS["buffer"] .= $buffer;
    }
  }
}

function read_chunked_body(&$fp, $content_length){

  $last_chunk = false;

  while (!feof($fp)) {

    $buffer = fgets($fp);
    $trimmed_buffer = trim($buffer, "\r\n ");

    if(is_numeric("0x". $trimmed_buffer)) {
      if($trimmed_buffer == '0') {
        $last_chunk = true;
      }
    } else {
      if($trimmed_buffer == '' && $last_chunk == true) {
        print($buffer);
        $BUFFERDEF += $buffer;
        break;
      }
    }
    print($buffer);
    $BUFFERDEF += $buffer;
  }
}
?>
