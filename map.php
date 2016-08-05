<?php

	include("config.php");
	//This example client is not fully complete
	//TODO:
	//Refresh token expire workflow
	//Revoked Tokens workflow
	//Change to database backend instead of storing tokens in cookies

	$access_token = $_COOKIE['active911_wmf_access_token'];
	//Check if we have a previous state or a code redirection
	if(empty($access_token) && empty($_GET['code'])){
		//We redirect the user to the Authorization Server to authorize access
		$authorization_url = 'https://access.active911.com/interface/open_api/authorize_agency.php';
		
		//Set the GET parameters and then redirect the user to the authorization url
		$query_array = array(
			'client_id'			=>	$client_id,
			'response_type'		=>	'code',
			'redirection_uri'	=>	'https://' . $_SERVER[HTTP_HOST] . $_SERVER[REQUEST_URI],
			'scope'				=>	'read_agency read_device'
		);
		header('Location: ' . $authorization_url . '?' . http_build_query($query_array));
	}
	else if(!empty($_GET['code'])){
		//We have been redirected back here after being authorized by the Authorization Server
		//Now we need to exchange our authorization code for an access token and refresh token
		$token_url = 'https://access.active911.com/interface/open_api/token.php';
		
		//Note that we send the secret to authenticate our request.  We did not send the secret to generate an authorization code because then the user would be able to see it.
		//We must send the secret from a server or protected native app, where it cannot be grabbed by the user.
		$post_array = array(
			'client_id'			=>	$client_id,
			'client_secret'		=>	$client_secret,
			'grant_type'		=>	'authorization_code',
			'scope'				=>	'read_agency read_device',
			'code'				=>	$_GET['code']
		);
		
		//Now we have the authorization code.  We send a request to the token endpoint
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $token_url); 
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_array)); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($curlhandle, CURLOPT_VERBOSE, true);
		$output = curl_exec($ch);
		$response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close ($ch);
		$json = json_decode($output);
		
		//Check if we got a OK response
		if($response_code != 200){
			//There was an error.  Show to the user if JSON error was sent
			if(empty($json->error_description)){
				die("Error: Could not generate token");
			}
			else{
				die("Error: " . $json->error_description);
			}
		}
		
		//Set the access token, refresh token, and expiration in the cookies
		//Normally the tokens should be kept serverside, away from the user and also so that it isnt sent everytime the user communicates with your server.
		$access_token = $json->access_token;
		$refresh_token = $json->refresh_token;
		$expiration = time() + $json->expires_in;
		setcookie('active911_wmf_access_token', $access_token);
		setcookie('active911_wmf_refresh_token', $refresh_token);
		setcookie('active911_wmf_expires', (time() + $json->expires_in));
	}
	else if($_COOKIE['active911_wmf_expires'] < time() || $_GET['refresh_access_token'] == true){
		//Our access token expired.  Request a new one using the refresh token.
		$token_url = 'https://access.active911.com/interface/open_api/token.php';
		//Note that we send the secret again to authenticate our request.
		//As previously stated, the refresh token should NOT normally be stored in a COOKIE, it should be stored securely in a database backend or the native app itself
		$post_array = array(
			'client_id'			=>	$client_id,
			'client_secret'		=>	$client_secret,
			'grant_type'		=>	'refresh_token',
			'scope'				=>	'read_agency read_device',
			'refresh_token'		=>	$_COOKIE['active911_wmf_refresh_token']
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $token_url); 
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_array)); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($curlhandle, CURLOPT_VERBOSE, true);
		$output = curl_exec($ch);
		$response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close ($ch);
		$json = json_decode($output);
		
		//Check if we got a OK response
		if($response_code != 200){
			//There was an error.  Show to the user if JSON error was sent
			if(empty($json->error_description)){
				die("Error: Could not generate token");
			}
			else{
				die("Error: " . $json->error_description);
			}
		}
		
		//Set the access token and expiration in the cookies
		//Normally the tokens should be kept serverside, away from the user and also so that it isnt sent everything the user communicates with your server.
		$access_token = $json->access_token;
		$expiration = time() + $json->expires_in;
		setcookie('active911_wmf_access_token', $access_token);
		setcookie('active911_wmf_expires', (time() + $json->expires_in));
	}
?>
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
<style type="text/css">
  html { height: 100% }
  body { height: 100%; margin: 0; padding: 0 }
  div { float: left }
  #map-optionbar-r { margin-right: 5% }
  #map-canvas { height: 100%; width: 70% }
  
</style>
<script src='https://ajax.aspnetcdn.com/ajax/jQuery/jquery-2.1.0.js'></script>
<script type="text/javascript"
  src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC4vLRlKPw-fKmA66KjKBD81RU0HG2_aEs&sensor=false">
</script>
<script type='text/javascript'>
	var access_token='<?php echo $access_token ?>';
	var base_api_uri = 'https://access.active911.com/interface/open_api/api/';
	var devices = {};
	var device_id = 0;
	var device_marker;
	var device_infowindow;
	var map;
	$(document).ready(function(){
		//Request the base agency information from the API
		$.ajax({
			type: "GET",
			url: base_api_uri,
			beforeSend: function (xhr) {
				xhr.setRequestHeader('Authorization', "Bearer " + access_token);
			},
			success: function (response) {
				if(response.result=='success'){
					//We successfully fetched the agency's information
					//Initialize the map centered on the agency
					var latitude = response.message.agency.latitude;
					var longitude = response.message.agency.longitude;
					var agency_position = new google.maps.LatLng(latitude, longitude);
					var mapOptions = {
					  center: agency_position,
					  zoom: 14,
					  mapTypeId: google.maps.MapTypeId.SATELLITE
					};
					map = new google.maps.Map(document.getElementById("map-canvas"),
						mapOptions);
					
					//Create a marker for the agency
					var marker = new google.maps.Marker({
						position: agency_position,
						map: map,
						title: response.message.agency.name,
						icon: '750-home.png'
					});
					var infowindow = new google.maps.InfoWindow({
						content: "<div>" + response.message.agency.name + "</div>"
					});
					google.maps.event.addListener(marker, 'click', function() {
						infowindow.open(map,marker);
					});
					
					//Store the uri's for all of this agency's devices
					$( response.message.agency.devices ).each(function(i, device){
						devices[device.id] = {uri: device.uri};
					});
						
					
				}
				else{
					alert('Could not connect to the API');
				}
			},
			dataType: 'json'
		});
	});
	function findDevice(){
		//Remove the old device
		if(device_marker != null){
			device_marker.setMap(null);
			device_marker = null;
			device_infowindow = null;
		}
		//Get the device id entered
		device_id = $("#device_input").val();
		
		//We only need the digits
		device_id = /[0-9]+/.exec(device_id);
		
		if(devices[device_id] != null){
			updateDevicePosition();
			setInterval("updateDevicePosition()", 60000);
		}
		else{
			window.location.search += '&refresh_access_token=true';
			window.location.reload();
		}
	}
	function updateDevicePosition(){
			var device_uri = devices[device_id].uri;
			//Get the info for the device
			$.ajax({
				type: "GET",
				url: device_uri,
				beforeSend: function (xhr) {
					xhr.setRequestHeader('Authorization', "Bearer " + access_token);
				},
				success: function (response) {
					if(response.result=='success'){
						var device = response.message.device;
						var device_position = new google.maps.LatLng(device.latitude, device.longitude);
						//We don't have a marker yet
						if(device_marker == null){
							//Create a marker for the device
							device_marker = new google.maps.Marker({
								position: device_position,
								map: map,
								title: device.name,
								icon: '815-car.png'
							}); 
							//Make it bounce for added visibility
							device_marker.setAnimation(google.maps.Animation.BOUNCE);
							device_infowindow = new google.maps.InfoWindow({
								content: "<div>" + device.name + " was last seen at " + device.position_timestamp + "</div>"
							});
							//Show its last position upda
							google.maps.event.addListener(device_marker, 'click', function() {
								device_infowindow.open(map,device_marker);
							});
						}
						else{
							device_marker.setPosition(device_position);
						}
						map.panTo(device_position);
					}
					else{
						window.location.search += '&refresh_access_token=true';
						window.location.reload();
					}
				},
				dataType: 'json'
			});
	};
</script>

  <body>
	<div id='map-optionbar-r'>
	<h1>Where's my Firefighter?</h1>
    Device Id <input id="device_input" name="device_id" type="text"  />
	<button id="device_find_button" onClick="findDevice();">Find It!</button><br />
	<span id='device_summary'></span>
	<?php
	
	//You can uncomment this if you want to see the contents of the cookies.
	/*
		echo "Previously saved access token: $access_token<br />";
		$refresh_token = $_COOKIE['active911_wmf_refresh_token'];
		echo "Previously saved refresh token: $refresh_token<br />";
		$expiration = $_COOKIE['active911_wmf_expires'];
		echo "Previously saved token expires at: $expiration<br />";
		echo "Time until expiration: " . ($expiration - time()) . "s";
	*/
	?>
  </div> 
    <div id="map-canvas"></div>
  </body>
</html>