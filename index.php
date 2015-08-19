<?php
/**
 * Simple script to push a job to Castlab Encoding.
 * User: Temitope Omotunde <topeomot@gmail.com>
 * Date: 8/19/2015
 * Time: 4:12 PM
 */
//error_reporting(E_ALL);
//ini_set('display_errors', '1');
include_once("/var/www/vendor/autoload.php");


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$ticket_url = "https://auth.drmtoday.com/cas/v1/tickets";
$vtk = "http://vtk.castlabs.com/api/";
$vtk_jobs_url = "http://vtk.castlabs.com/api/jobs/";
$merchant = "***********************";
$username = "*****@*****.***";
$password = "***********************";


//s3 source bucket details
$bucket_source = "****************";
$bucket_source_key = "****************";
$bucket_source_secret = "****************";

//s3 destination bucket details
$bucket_destination = "****************";
$bucket_destination_key = "****************";
$bucket_destination_secret = "****************";

//notification email
$email = "*****@*****.***";

$logger = new Logger('pathLogger');
$logger->pushHandler(new StreamHandler('path.log', Logger::DEBUG));

//asset id for the job. this should be unique;
 $assetId = time();

//example job
$job=<<<EO
{
    "tasks": [
        {
            "tool": "storage:get",
            "parameters": {
                "location": "s3://$bucket_source_key:$bucket_source_secret@$bucket_source/",
                "files": [
                    "videoTeaser.mp4"
                ]
            }
        },
		{
			"tool": "dash:drmtoday",
			"parameters": {
				"license_acq_ui_url": "https://playready.com",
				"license_acq_url": "https://lic.staging.drmtoday.com/license-proxy-headerauth/drmtoday/RightsManager.asmx",
				"widevine_provider": "castlabs",
				"clear_lead": "0",
				"environment": "PROD",
				"merchant": "$merchant",
				"password": "$password",
				"user": "$username",
				"tracks": "*.mp4",
				"outputdir": "$assetId/",
				"asset_id": "$assetId"
			}
		},
		{
            "tool": "storage:put",
            "parameters": {
                "location": "s3://$bucket_destination_key:$bucket_destination_secret@$bucket_destination/",
                "files": ["$assetId/*"]
            }
        }
    ],
    "notify": "$email"
}
EO;


$client = new Client();

        try {
            $response = $client->post($ticket_url, [
                'form_params' => [
                    'username' => $username,
                    'password' => $password
                ]
            ]);




            $code = $response->getStatusCode();

            if ($code == '200' || $code == '201') {

                $ticket_location_array = $response->getHeader('location');
                $ticket_location = $ticket_location_array[0];



                $logger->addInfo("Ticket Location : ".$ticket_location);

                $response_get_ticket = $client->post($ticket_location, [

                    'form_params' => [
                        'service' => $vtk
                    ]
                ]);

                $code_get_ticket = $response_get_ticket->getStatusCode();

                if ($code_get_ticket == '200' || $code_get_ticket == '201') {

                    $ticket = $response_get_ticket->getBody();

                    $logger->addInfo("Ticket : ".$ticket);

                    $response_job = $client->post($vtk_jobs_url, [
                        'headers' => [
                            'Authorization' => 'Ticket '.$ticket,
                            'content-type' => 'application/json'
                        ],
                        'body' => $job,
                    ]);


                    $code_job = $response_job->getStatusCode();



                    if ($code_job == '200' || $code_job == '201') {

                        $logger->addInfo("Job Successful Submitted: ".$response_job->getBody());
                        echo "Job Successfully Submitted";

                    }
                    else{
                        $logger->addError("Job Submission Failed at the third stage : ", array($code_job, $response_job->getReasonPhrase(),
                            $response_job->getBody()));
                        echo 'Job Submission Failed at the third stage, Check Logs for details.';

                    }



                } else {
                    $logger->addError("Job Submission Failed at the second stage : ", array($code, $response->getReasonPhrase(),
                        $response->getBody()));
                    echo 'Job Submission Failed at the second stage, Check Logs for details.';

                }





            } else {

                $logger->addError("Job Submission Failed at the first stage : ", array($code, $response->getReasonPhrase(),
                    $response->getBody()));
                echo 'Job Submission Failed at the first stage, Check Logs for details.';
            }

        }catch (RequestException $e) {


            $host = $e->getRequest()->getUri()->getHost();
            $path = $e->getRequest()->getUri()->getPath();

            if(($host== "auth.drmtoday.com")){

                if($path == "/cas/v1/tickets"){
                    $level = "first";
                }
                else{
                    $level = "second";
                }
            }
            else{
                $level = "third";
            }


            if($e->hasResponse()) {
                $res = $e->getResponse();


                $logger->addError("Job Submission Failed at the $level stage : ", array($e->getCode(), $res->getReasonPhrase()));


            }
            else{


                $logger->addError("Job Submission Failed at the $level stage : ", array($e->getCode(), $e->getMessage()));
            }


            echo "Job Submission Failed at the $level stage, Check Logs for details.";

        }
        ?>
