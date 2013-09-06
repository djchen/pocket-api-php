<?php
/*
 * A sample script for obtaining a Pocket access token
 * and retrieving the user's Pocket data
 *
 * @package     php-Pocket
 * @author      Dan Chen
 * @license     MIT License
*/

// Show all errors/warnings
error_reporting(E_ALL);
ini_set('display_errors', '1');

const NEWLINE = '<br /><br />';

require('Pocket.php');

$params = array(
	'consumerKey' => '' // fill in your Pocket App Consumer Key
);

if (empty($params['consumerKey'])) {
	die('Please fill in your Pocket App Consumer Key');
}

$pocket = new Pocket($params);

if (isset($_GET['authorized'])) {
	// Convert the requestToken into an accessToken
	// Note that a requestToken can only be covnerted once
	// Thus refreshing this page will generate an auth error
	$user = $pocket->convertToken($_GET['authorized']);
	/*
		$user['access_token']	the user's access token for calls to Pocket
		$user['username']	the user's pocket username
	*/
	print_r($user);

	// Set the user's access token to be used for all subsequent calls to the Pocket API
	$pocket->setAccessToken($user['access_token']);

	echo NEWLINE;

	// Add a url to the user's pocket
	// http://getpocket.com/developer/docs/v3/add for a list of params
	$params = array(
		'url' => 'https://github.com/djchen/', // required
		'tags' => 'github'
	);
	print_r($pocket->add($params, $user['access_token']));

	echo NEWLINE;

	// Retrieve the user's list of unread items (limit 5)
	// http://getpocket.com/developer/docs/v3/retrieve for a list of params
	$params = array(
		'state' => 'unread',
		'sort' => 'newest',
		'detailType' => 'simple',
		'count' => 5
	);
	$items = $pocket->retrieve($params, $user['access_token']);
	print_r($items);

} else {
	// Attempt to detect the url of the current page to redirect back to
	// Normally you wouldn't do this
	$redirect = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? 'https' : 'http') . '://'  . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?authorized=';

	// Request a token from Pocket
	$result = $pocket->requestToken($redirect);
	/*
		$result['redirect_uri']		this is the URL to send the user to getpocket.com to authorize your app
		$result['request_token']	this is the request_token which you will need to use to
						obtain the user's access token after they have authorized your app
	*/

	/*
	This is a hack to redirect back to us with the requestToken
	Normally you should save the 'request_token' in a session so it can be
	retrieved when the user is redirected back to you
	*/
	$result['redirect_uri'] = str_replace(
		urlencode('?authorized='),
		urlencode('?authorized=' . $result['request_token']),
		$result['redirect_uri']
	);
	// END HACK

	header('Location: ' . $result['redirect_uri']);
}
