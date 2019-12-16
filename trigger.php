<?php
/**
 * This is the backend script for the example of triggering automated
 * from TestRail's UI. This scripts executes the tests of the given
 * test run (passed in as GET parameter) and reports back the results
 * to TestRail via TestRail's API.
 * 
 * Copyright Gurock Software GmbH. See license.md for details.
 *
 * http://www.gurock.com/testrail/
 */
// This is the place to configure the API settings for your TestRail
// installation.
define('TESTRAIL_API_ADDRESS', 'https://helm360.testrail.com/');
define('TESTRAIL_API_USER', 'gpandey@helm360.com');
define('TESTRAIL_API_PASSWORD', 'Gyan1234@#');
define('HTTP_ORIGIN', 'https://helm360.testrail.com/');

/**
 *  An example CORS-compliant method.  It will allow any GET, POST, or OPTIONS requests from any
 *  origin.
 *
 *  In a production environment, you probably want to be more restrictive, but this gives you
 *  the general idea of what is involved.  For the nitty-gritty low-down, read:
 *
 *  - https://developer.mozilla.org/en/HTTP_access_control
 *  - http://www.w3.org/TR/cors/
 *
 */
function cors() {

    // Allow from any origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        // Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
        // you want to allow, and if so:
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }

    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            // may also be using PUT, PATCH, HEAD etc
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

        exit(0);
    }

    echo "You have CORS!";
}



function execute_test($run_id, $case_id, $test_id)
{
	// This is the place to execute/process the actual test, e.g. by
	// triggering an external command line tool or API. This function
	// is expected to return a valid TestRail status ID. We just
	// generate a random status ID as a placeholder.
	
	
	$statuses = array(1, 2, 3, 4, 5);
	$status_id = $statuses[rand(0, count($statuses) - 1)];
	if ($status_id == 3) // Untested?
	{
		return null;	
	}
	else 
	{
		return $status_id;
	}
}
@set_time_limit(0);
$run_id = null;
if (isset($_GET['run_id']))
{
	if (preg_match('/^[0-9]+$/', $_GET['run_id']))
	{
		$run_id = (int) $_GET['run_id'];
	}
}
if (!$run_id)
{
	throw new TestRailException(
		'Run ID not available, exiting immediately'
	);
}
$api = new TestRail_api(
	TESTRAIL_API_ADDRESS,
	TESTRAIL_API_USER,
	TESTRAIL_API_PASSWORD
);
$tests = $api->send_command('GET', 'get_tests/' . $run_id);
foreach ($tests as $test)
{
	$status_id = execute_test($run_id, $test->case_id, $test->id);
	if ($status_id)
	{
		$api->send_command(
			'POST', 
			'add_result/' . 
			$test->id,
			array(
				'status_id' => $status_id
			)
		);
	}
}
class TestRail_api
{
	private $_address;
	private $_userpwd;
	public function __construct($address, $user, $password)
	{		
		$this->_address = rtrim($address, '/') . '/';
		$this->_user = $user;
		$this->_password = $password;
		$this->_userpwd = "$user:$password";
	}
	private function _send_request($method, $url, $data,
		&$status_code)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		if ($method == 'POST')
		{
			if ($data)
			{
				curl_setopt(
					$ch,
					CURLOPT_POSTFIELDS, 
					json_encode($data)
				);
			}
			else
			{
				$headers['Content-Length'] = '0';
				curl_setopt($ch, CURLOPT_POSTFIELDS, '');
			}
		}
		else
		{
			curl_setopt($ch, CURLOPT_POST, false);
		}
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Expect:',
				'Content-Type: application/json'
			)
		);
		curl_setopt($ch, CURLOPT_USERPWD, "$this->_userpwd");
		$data = curl_exec($ch);
		if ($data === false)
		{
			throw new TestRailException(curl_error($ch));
		}
		$info = curl_getinfo($ch);
		curl_close($ch);
		$status_code = $info['http_code'];
		return $data;
	}
	public function send_command($method, $uri, $data = null)
	{
		$url = $this->_address . "index.php?api/v2/$uri";
		$response = $this->_send_request($method, $url, $data,
			$status_code);
		$obj = null;
		if ($response && is_string($response))
		{
			$obj = json_decode($response);
		}
		if ($status_code != 200)
		{
			throw new TestRailException(
				'HTTP status code: ' . $status_code
			);
		}
		return $obj;
	}
}
class TestRailException extends Exception
{
}
