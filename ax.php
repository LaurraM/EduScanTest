<?php

global $_CONFIG;
include "config.php";

global $lastresp;
$lastresp='';

function sendToMyAI($textuser,$systext=false)
{
	$request = curl_init('http://95.76.226.104:8090/?key=aiforme'.(isset($systext)?'&system='.urlencode($systext):''));
	curl_setopt($request, CURLOPT_POST, true);
	curl_setopt($request, CURLOPT_POSTFIELDS,$textuser);
	curl_setopt($request, CURLOPT_HEADER, false);
	curl_setopt($request, CURLOPT_VERBOSE, 0);
	curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($request, CURLOPT_TIMEOUT, ( int ) 180 );
	$rasp=curl_exec($request);
	return $rasp;
}


function sendToVertexAPI($textuser,$systext=false)
{
	$parts=array();
	if($systext!==false)
	{
		foreach($systext as $k=>$v)
		{
			$parts[]=array("role"=>$k,"parts"=>array(array("text"=>$v)));
		}
	}
	$parts[]=array("role"=>"user","parts"=>array(array("text"=>$textuser)));

	//print_r($parts);

	$API_ENDPOINT="us-central1-aiplatform.googleapis.com";
	$PROJECT_ID="asiguram-1045";
	$MODEL_ID="gemini-1.5-pro-preview-0409";
	$LOCATION_ID="us-central1";
	
	$bearer='NA';

	$header = array ();
	//"Authorization: Bearer $(gcloud auth print-access-token)"
	$header [] = 'Connection: close';
	$header [] = 'Content-Type: application/json';
	$header [] = 'Cache-Control: no-cache';
	$header [] = 'Authorization: Bearer '.$bearer;
	$header [] = 'User-Agent: asiguram';
	$session = curl_init();
		
	$xml='{
	 "contents": '.json_encode($parts).',
		"generation_config": {
			"maxOutputTokens": 2048,
			"temperature": 0.4,
			"topP": 1,
			"topK": 32
		}
	}';
	// Set the POST options.
	curl_setopt($session, CURLOPT_URL, "https://$API_ENDPOINT/v1beta1/projects/$PROJECT_ID/locations/$LOCATION_ID/publishers/google/models/$MODEL_ID:streamGenerateContent");
	
	curl_setopt($session, CURLOPT_POST, true);
	curl_setopt($session, CURLOPT_POSTFIELDS, $xml);
	curl_setopt($session, CURLOPT_HTTPHEADER, $header);
	curl_setopt($session, CURLOPT_HEADER, false);
	curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($session, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($session, CURLOPT_FOLLOWLOCATION, true); 
	curl_setopt($session, CURLOPT_TIMEOUT, ( int ) 20 );
	
	//echo $xml;
	// Do the POST and then close the session
	$response = curl_exec($session);
	//echo $response;
  global $lastresp;
  $lastresp.=$response;
	$all=json_decode($response,true);
	$resp='';
  if(is_array($all))
	foreach($all as $k=>$v)
	{

    if(isset($v['candidates']))
		{
      foreach($v['candidates'] as $kk=>$vv)
      {

        foreach($vv['content']['parts'] as $kkk=>$vvv)
        {
          $resp.=$vvv['text'];
        }
    
      }
    }
    else
    {
      $resp.="AI-ul nu a raspuns..".$v['message'];
    }
	}
	if($resp=="")
	{
		if(true || isset($_POST['debug']))
			print_r($all);
		return false;
	}
	//echo $resp;
	return $resp;
}
function extractJsonFromAi($text)
{
	$pos1=strpos($text,'{');
	if($pos1===false)
		$pos1=strpos($text,'[');
	$term=substr($text,$pos1,1);
	if($term=="{" || $term=="[")
	{
		if($term=="{") $term2="}";
		if($term=="[") $term2="]";
		$pos2=strpos($text,$term2,$pos1+1);
		return json_decode(substr($text,$pos1,$pos2-$pos1+1),true);
	}
	return false;
}
if(isset($_POST['ax']) && $_POST['ax']!="")
switch($_POST['ax'])
{

  case 'quizz':

    $ret=false;
    $retries=5;
    while($retries && $ret===false)
    {
      $retries--;
      $resp=sendToVertexAPI("Creaza o intreabare, raspunde in format JSON {intrebare, raspunsA,raspunsB,raspunsC,raspunsD,correct:A or B or C or D} din textul urmator: --- ".$_POST['txt']." ----"
        .($_POST['intrebari']!=""?"Evita sa pui urmatoarele intrebari: ".$_POST['intrebari']:""));
      $ret=extractJsonFromAi($resp);
      sleep(2);
    }
    if($ret===false)
    {
      echo json_encode(array("err"=>$resp,"last"=>$lastresp));
    }
    else
    {
      $ret['last']=$lastresp;
      echo json_encode($ret);
    }
    die();
  break;
  case 'quizz_solve':

      $ret=false;
      $retries=5;
      while($retries && $ret===false)
      {
        $retries--;
        $resp=sendToVertexAPI("De ce la intrabarea --- ".$_POST['intrebare']." ---"." Cu raspunsurile: "
        ." A:".$_POST['raspunsA']
        .", B:".$_POST['raspunsB']
        .", C:".$_POST['raspunsC']
        .", D:".$_POST['raspunsD']
        .", raspunsul corect este ".$_POST['correct']."? Raspunde in format JSON {explicatii}"
        ." Intrebare este extrasa din textul urmator: --- ".$_POST['txt']." ----");
        $ret=extractJsonFromAi($resp);
        sleep(2);
      }
      if($ret===false)
      {
        echo json_encode(array("err"=>$resp,"last"=>$lastresp));
      }
      else
      {
        $ret['last']=$lastresp;
        echo json_encode($ret);
      }
      die();
      break;

  default:
    jsonerror("Unable to find ".$_GET['ax']);
  die();
  break;

}


?>
