<?php

/*
Client area login notifications for WHMCS (works with versions 6-8)
Created by whmcsguru
Contributions by brian!
*/

use WHMCS\Database\Capsule;
$myver = get_whmcs_version();
$isadmin = $_SESSION['adminid'];

$admnotify = TRUE;
//change this to true you want to send notifications when admin logged in..
//NOT advisable. this will let your clients know when you're logging into their account

if (!empty($isadmin))
{
	if (!$admnotify)
	{
		return;
	}
}
function hook_client_login_notify($vars)
{
	$mailsent=FALSE;


	global $myver;
	$myver = get_whmcs_version();
	if ($myver < 8)
	{
		$userid = $vars['userid'];

		send_login_notify($userid);
		return;
	}
	if ($myver >= 8)
	{
		$user = $vars['user'];
		$userid = $user->id;
		//a dirty hack to try to work around a couple of things, maybe

		$acctowner = Capsule::table('tblusers_clients')
		->where('auth_user_id', '=', $userid)
		->where('owner', '=', 1)
		->count();

		$numrows = Capsule::table('tblusers_clients')
		->where('auth_user_id', '=', $userid)
		->count();

		//we own our account. We must always notify us directly
		if ($acctowner > 0)
		{
			send_login_notify($userid);
			return;
		}

		//we don't own our account, so, notify the owner, if we only exist once.
		if ($numrows < 2)
		{
			foreach (Capsule::table('tblusers_clients')->WHERE('auth_user_id', '=', $userid)->get() as $userstuff){
				$userid = $userstuff->auth_user_id;
				$clientid = $userstuff->client_id;
				$owner = $owner;
				if ($acctowner < 1)
				{
					send_login_notify($clientid, $userid);
					return;
				}

			}
		}

		return;
	}



}


function send_login_notify($myclient, $theuserid="")
{
	global $myver;

	$ip = $_SERVER['REMOTE_ADDR'];

	$res = json_decode(file_get_contents('https://www.iplocate.io/api/lookup/'.$ip));
	$city = $res->city;
	$hostname = gethostbyaddr($ip);

	if ($myver < 8)
	{

		$clientinfo = Capsule::table('tblclients')->select('firstname', 'lastname')->WHERE('id', $myclient)->get();
		foreach ($clientinfo as $clrow)
		{
			$firstname = $clrow->firstname;
			$lastname = $clrow->lastname;
		}
	}
	if ($myver >= 8)
	{

		$clientinfo = Capsule::table('tblusers')->select('first_name', 'last_name')->WHERE('id', $myclient)->get();
		foreach ($clientinfo as $clrow)
		{
			$firstname = $clrow->first_name;
			$lastname = $clrow->last_name;
		}
	}


	$command = "sendemail";
	$values["customtype"] = "general";
	if (empty($theuserid))
	{
		$values["customsubject"] = "Account Login from $ip";
		$values["custommessage"] = "<p>Hello $firstname $lastname,<p>Your account was successfully accessed by a remote user recently. If this was not you, please do contact us immediately<p>ISP: $hostname<br/>City: $city<br/>Country: $res->country<br /><br/>$signature";
	}

	elseif ($theuserid > 0)
	{
		$moreinfo = Capsule::table('tblusers')->select('first_name', 'last_name', 'email')->WHERE('id', $theuserid)->get();
		//greet them
		foreach ($moreinfo as $userrow)
		{
			$ufirst = $userrow->first_name;
			$ulast = $userrow->last_name;
			$uemail = $userrow->email;
		}

		$values["customsubject"] = "Subaccount Login from $ip";
		$values["custommessage"] = "<p>Hello
		$firstname $lastname,<p>
		A subaccount of yours just logged in successfully. Please see the details of the login below
		<p>
		Name: $ufirst $ulast
		Email: $uemail
		ISP: $hostname
		City: $city
		Country: $res->country<br />
		$signature<br />";
	}
	$values["id"] = $myclient;

	$results = localAPI($command, $values);
	
}
if ($myver < 8)
{
	add_hook('ClientLogin', 1, 'hook_client_login_notify');
}
if ($myver >= 8)

{
	add_hook('UserLogin', 1, 'hook_client_login_notify');
}

function get_whmcs_version()
{
        $theversion = Capsule::table('tblconfiguration')->where('setting', '=', 'Version')->value('value');
        $retver = substr($theversion, 0,1);

        return ($retver);

}
