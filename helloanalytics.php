<?php 
session_start();

//jezsuitak@gmail.com code.google.com/apis/console?api=analytics
$host = 'localhost';

include_once 'db.php';

if(array_key_exists('startdate',$_GET)) $startdate = $_GET['startdate']; else $startdate = date('Y-m-d',strtotime('-1 month -1 day'));
if(array_key_exists('enddate',$_GET)) $enddate = $_GET['enddate']; else $enddate = date('Y-m-d',strtotime('-1 day'));

db_query("UPDATE domains SET visits = 0;");
db_query("TRUNCATE ga_source;");

set_time_limit(600);

require_once 'google-api-php-client/src/apiClient.php';
require_once 'google-api-php-client/src/contrib/apiAnalyticsService.php';

$domains = array();

$client = new apiClient();
$client->setApplicationName('Hello Analytics API Sample');

// Visit //code.google.com/apis/console?api=analytics to generate your
// client id, client secret, and to register your redirect uri.
$client->setClientId('472870238683-nh7nebcvmc54uaoo3rs3c1sd9pjk1puf.apps.googleusercontent.com');
$client->setClientSecret('tcQhh1R0XlaGgXUFM9MF7jAm');
$client->setRedirectUri('http://'.$host.'/github/GoogleAnalytics-to-yEd/index.php');
$client->setDeveloperKey('AIzaSyDq3GwNwCi3lFZ9n47Wys6eF5fEvJyfeio');
$client->setScopes(array('https://www.googleapis.com/auth/analytics.readonly'));

// Magic. Returns objects from the Analytics Service instead of associative arrays.
$client->setUseObjects(true);



if (isset($_GET['code'])) {
	$client->authenticate();
	$_SESSION['token'] = $client->getAccessToken();
	$redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
	header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
}

if (isset($_SESSION['token'])) {
	$client->setAccessToken($_SESSION['token']);
}

if (!$client->getAccessToken()) {
	$authUrl = $client->createAuthUrl();
	print "<a class='login' href='$authUrl'>Connect Me!</a>";
	exit;

} else {
	$analytics = new apiAnalyticsService($client);
	runMainDemo($analytics);
	saveDatas();
	// Create analytics service object. See next step below.
}

//include 'graphhtml.php';


function runMainDemo(&$analytics) {
	global $domains;
	global $enddate;
	global $startdate;
	$doms = db_query('SELECT * FROM domains');
	foreach($doms as $dom) {
		if($dom['tableId']!='') $domains[$dom['domain']] = $dom['tableId'];
	}
	//print_r($domains); 	exit;
	
	try {
		$profiles = getProfiles($analytics);
		$c=0;
		foreach($profiles as $profile) {
			if($c<150) {
			
		    
			$results = $analytics->data_ga->get(
					'ga:' . $profile->id,
					$startdate,
					$enddate,
					'ga:visits');
			$rows = $results->getRows();
			if(count($results->getRows())>0) {
				//print_R($rows);
				$profileId = $results->getProfileInfo()->getProfileId();
				$profileName = $results->getProfileInfo()->getProfileName();
				db_query("UPDATE domains SET visits = '".$rows[0][0]."' WHERE tableId = '".$profileId."'");
				db_query("INSERT INTO domains (domain,name,visits,tableId) VALUES ('".$profileName."','".$profileName."','".$rows[0][0]."','".$profileId."')");
				echo $profileName."-".$profileId."<br>";
			}
			//print_r($profile); echo "--"; print_R($rows); echo"<br>";
			
			$results = $analytics->data_ga->get(
					'ga:' . $profile->id,
					$startdate,
					$enddate,
					'ga:visits',
					array('sort'=>'-ga:visits',
							'dimensions'=>'ga:source',
							'max-results'=>40
							));

			if(count($results->getRows())>0) {
				foreach($results->getRows() as $source) {
					if(array_key_exists($source[0],$domains)) $source[0]=$domains[$source[0]];
					saveSource($source[0],$profile->id,$source[1]);
				}
			}
			
			//print_R($results);
			
			$c++;
			}
		}
	} catch (apiServiceException $e) {
		// Error from the API.
		//	print_R($e);
		print 'There was an API error : ' . $e->getCode() . ' : ' . $e->getMessage().'; // ---'.$e->errors->message();

	}
}

function getProfiles(&$analytics) {
	$items = array();
	$accounts = $analytics->management_accounts->listManagementAccounts();
	if (count($accounts->getItems()) > 0) {
		foreach($accounts->getItems() as $account) {
			$webproperties = $analytics->management_webproperties->listManagementWebproperties($account->getId());
			if (count($webproperties->getItems()) > 0) {
				foreach($webproperties->getItems() as $webproperties) {
					$profiles = $analytics->management_profiles->listManagementProfiles($account->getId(), $webproperties->getId());
					if (count($profiles->getItems()) > 0) {
						//print_R($profiles->getItems());
						$items = array_merge($items,$profiles->getItems());
					}
							
				}
			}
		}	
		
	}
	return $items;
}

function saveSource($from,$to,$value) {
	//echo $from."->".$to.": ".$value."<br>";
	if($from == $to) return;
	$tunnels = db_query('SELECT * FROM ga_source WHERE `to`  = "'.$to.'" AND `from`  = "'.$from.'" LIMIT 0,1');
	//print_R($urls);  "<br>";
	if($tunnels!=1) {
			db_query('UPDATE ga_source SET `value` = "'.$value.'" WHERE `to`  = "'.$to.'" AND `from`  = "'.$from.'"  LIMIT 1');
	} else {
		db_query('INSERT INTO ga_source VALUES ("'.$from.'","'.$to.'","'.$value.'")');
	}
	
}

function getResults(&$analytics, $profileId) {
	global $startdate;
	global $enddate;
	return $analytics->data_ga->get(
			'ga:' . $profileId,
			$startdate,
			$enddate,
			'ga:visits');


}

function printResults(&$results) {
	$profileName = $results->getProfileInfo()->getProfileName();
	print "Profile found: $profileName<br>";
	print "Profile id: ".$results->getProfileInfo()->getProfileId()."<br>";
	if (count($results->getRows()) > 0) {
		
		$rows = $results->getRows();
		$visits = $rows[0][0];

		
		print "Total visits: $visits<br><br>";

	} else {
	print 'No results found.<br><br>';
	}
}

function saveDatas($filename = 'ganalytics') {
	global $startdate;
	global $enddate;
	$json['domains'] = db_query("SELECT * FROM domains;");
	$json['ga_source'] = db_query("SELECT * FROM ga_source;");
	$json['date'] = date("Y-m-d H:i");
	
	file_put_contents('jsons/'.$filename."_".$startdate."-".$enddate.".json",json_encode($json));

}

?>