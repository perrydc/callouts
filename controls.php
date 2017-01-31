<?php
// DOCUMENTATION:
// firebase: https://firebase.google.com/docs/dynamic-links/create-links
// bit.ly: https://dev.bitly.com/links.html#v3_user_link_edit

//contact dperry@npr.org for keys and tokens
//header('Content-Type: application/json');
$firebase_key = "YOURKEY";
$npr_token = "YOURTOKEN";
$bitly_key = "YOURKEY"; //API KEY
$bitly_token = "YOURTOKEN"; //BEARER TOKEN

function get_npr_data($url) {
    global $npr_token;
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: Bearer ' . $npr_token
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function post_firebase_data($data) {
    global $firebase_key;
    $ch = curl_init();
    $timeout = 5;
    $data_array = (array) json_decode($data);
    $data_string = json_encode($data_array);
    $ch = curl_init('https://firebasedynamiclinks.googleapis.com/v1/shortLinks?key='.$firebase_key);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                     
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                              
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                  
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data_string))
        );
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function get_bitly_data($url) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                  
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json'
        ));
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function linkOutput($query) {
    global $bitly_key;
    global $bitly_token;
    $url = "https://api.npr.org/stationfinder/v3/stations?q=" . $query;
    //echo get_npr_data($url, $npr_token);
    $stationData = json_decode(get_npr_data($url));
    if(isset($stationData->items[0]->attributes->eligibility->nprOne)){
        $eligible = $stationData->items[0]->attributes->eligibility->nprOne;
        if ($eligible == 1){
            if(isset($stationData->items[0]->attributes->newscast->id)){
                $name = $stationData->items[0]->attributes->brand->name;
                $op = "name: " . $name . "<BR>";
            }
            if(isset($stationData->items[0]->attributes->orgId)){
                $id = $stationData->items[0]->attributes->orgId;
                $domain = $stationData->items[0]->links->brand[0]->href;
                    $domain = preg_replace('/(http|https)+:\/\/(www.)?/', '', $domain);
                $logo = $stationData->items[0]->links->brand[1]->href;
                $call = strtolower($stationData->items[0]->attributes->brand->call);
                $title = urlencode($stationData->items[0]->attributes->brand->name . " on NPR One");
                $description = urlencode($stationData->items[0]->attributes->brand->frequency . $stationData->items[0]->attributes->brand->band . " in " . $stationData->items[0]->attributes->brand->marketCity . ", " . $stationData->items[0]->attributes->brand->marketState . " - " . $stationData->items[0]->attributes->brand->tagline);
                $link = "http://one.npr.org/localize?org_id=$id";
                $campaign = "donationPilot";
                $medium = "social";
                $data = <<<JSONDATA
{
  "dynamicLinkInfo": {
    "dynamicLinkDomain": "rpb3r.app.goo.gl",
    "link": "$link",
    "androidInfo": {
      "androidPackageName": "org.npr.one"
    },
    "iosInfo": {
      "iosBundleId": "org.npr.one",
      "iosCustomScheme": "nprone",
      "iosAppStoreId": "874498884"
    },
    "analyticsInfo": {
      "googlePlayAnalytics": {
        "utmSource": "$call",
        "utmMedium": "$medium",
        "utmCampaign": "$campaign"
      },
      "itunesConnectAnalytics": {
        "ct": "$campaign",
        "mt": "8",
        "pt": "284886"
      }
    },
    "socialMetaTagInfo": {
      "socialTitle": "$title",
      "socialDescription": "$description",
      "socialImageLink": "$logo"
    }
  },
  "suffix": {
    "option": "SHORT"
  }
}
JSONDATA;
                if (file_exists("redirects/" . $call)){
                    return array ("station"=>str_replace("//","/",$domain."/alwayson"),"npr"=>"n.pr/" . $call,"fileLink"=>"file.php?q=".$call);    
                } else {
                    $firebase_data = json_decode(post_firebase_data($data));
                    $shortLink = $firebase_data->shortLink;
                    $longLink = str_replace("&d=1","",$firebase_data->previewLink);
                    //$longlink = "https://rpb3r.app.goo.gl?link=$link&apn=org.npr.one&isi=874498884&ibi=org.npr.one&ius=nprone&st=$description&si=$logo&utm_source=$call&utm_medium=social&utm_campaign=donationPilot&ct=donationPilot&mt=8&pt=284886";
                    $shortLink_api_url = "https://api-ssl.bitly.com/v3/shorten?access_token=$bitly_token&longUrl=$shortLink";
                    $bitlyShortLinkData = json_decode(get_bitly_data($shortLink_api_url));
                    $bitlyShort = $bitlyShortLinkData->data->url;
                    $keyword_api_url = "https://api-ssl.bitly.com/v3/user/save_custom_domain_keyword?keyword_link=http://n.pr/$call&target_link=$bitlyShort&access_token=$bitly_token&overwrite=true"; //&overwrite=true
                    $bitlyShortLinkData = json_decode(get_bitly_data($keyword_api_url));
                    $bitlyKeywordLink = str_replace("http://","",$bitlyShortLinkData->data->keyword_link);
                    mkdir("redirects/" . $call . "/alwayson",$mode = 0777,$recursive = true);
                    $page = <<<HEREDOC
<!doctype html>
<html lang="en">
<head>
<script>
window.location = 'http://$bitlyKeywordLink';
</script>
</head>
<body>
This page redirects to <a href="http://$bitlyKeywordLink">$bitlyKeywordLink</a>
</body>
</html>
HEREDOC;
                    $myfile = fopen("redirects/" . $call . "/alwayson/index.html", "w") or die("Unable to open file.");
                    fwrite($myfile, $page);
                    fclose($myfile);
                    //return $bitlyKeywordLink;
                    return array ("station"=>str_replace("//","/",$domain."/alwayson"),"npr"=>$bitlyKeywordLink,"fileLink"=>"file.php?q=".$call);
                }
            }
        } else {
            //$error = 'Not eligible for NPR One localization. Enter a new query.';
            //return $error;
            die('Not eligible for NPR One localization. Enter a new query.');
        }
    } else {
        //$error = 'No station found. Enter a new query.';
        //return $error;
        die('No station found. Enter a new query.');
    }
}

if(isset($_GET['q'])){
    $links = linkOutput($_GET['q']);
    $nprLink = $links[npr];
    $stationLink = $links[station];
    $fileLink = $links[fileLink];
}

?>