<?php

	require "class.netsol_api.php";

	$partner_id 		= "xxxxxxxx";
	$partner_pass 		= "xxxxxxxx";
	//$netsol 	 	= new netsol_api($partner_id, $partner_pass, 1); // production API
	$netsol 	 	= new netsol_api($partner_id, $partner_pass, 2); // test API

	$result = $netsol->get_partner_domains();

	if(!is_object($result)){
		die("Failed to get data from Network Solutions.");
	}

	if(strval($result->Body->Status->StatusCode) != "11702"){
		$msg = 'The NetSol operation has failed. The status code returned was ('. $result->Body->Status->StatusCode .') '. $result->Body->Status->Description;
		die($msg);
	}

	foreach($result->Body->Domain as $domain){
		$domain_name = trim($domain->DomainName);	
		echo $domain_name . "<br />";
	}

?>