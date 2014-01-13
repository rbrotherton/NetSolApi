<?php
	
/** 
 * NetSol Api Class
 * 
 * A class for the Network Solutions API
 * 
 * @author Ryan Brotherton <rbrotherton@gmail.com> 
 * @license Unrestricted, unlicensed, free for everyone to use in any way.
 * @version V1. Written on PHP 5.3 for API version 6.8
 * @copyright 2013 Ryan Brotherton
*/  

	class netsol_api
	{

	    private $partner_id;
	    private $partner_password;
	    private $api_url;
	    private $availability_url;

	    // Construct and determine what mode we're in
	    public function __construct($id, $pass, $mode){
	    	
	    	$this->partner_id 		= $id;
			$this->partner_password = $pass;

			if($mode == 1){ // Production
				$this->availabiltiy_url = "https://partners.networksolutions.com:8010/invoke/vpp/AvailabilityService";
				$this->api_url 			= "https://partners.networksolutions.com:8020/invoke/vpp/TransactionService";
			} elseif($mode == 2){ // test
				$this->availabiltiy_url = "https://partners.pte.networksolutions.com:8010/invoke/vpp/AvailabilityService";
				$this->api_url 			= "https://partners.pte.networksolutions.com:8020/invoke/vpp/TransactionService";
			}
	    }

	    // PHP Magic function in case the object is used as a string.
	    public function __toString(){
	    	echo "NetSol API object.";
	    }

	    // Sanitize characters the API doesn't want to see
	    public function sanitize_for_xml($str){
	    	
	    	$str = str_replace("&", "&amp;", $str);
	    	$str = str_replace("<", "&lt;", $str);
	    	$str = str_replace(">", "&gt;", $str);
	    	$str = str_replace("“", "&quot;", $str);
	    	$str = str_replace("‘", "&apos;", $str);

	    	return $str;
	    }

	    // Convert xml response to an object
	    private function xml_to_object($xml){
	        return simplexml_load_string($xml);
	    }

	    // Fire of a cURL request and return the result
	    private function send_request($xml, $url){

	    	$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
			curl_setopt($ch, CURLOPT_POSTFIELDS, "$xml");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$output = curl_exec($ch);

			if(curl_error($ch)){
				echo "<hr />cURL Error: ". curl_error($ch) . "<hr />";
				return false;
			}

			curl_close($ch);

			return $output;
	    }
	    
	    //Get availability of $domain
	    public function check_available($domain){
	    	
	    	$xml = "
	    	<?xml version=\"1.0\" encoding=\"UTF-8\"?>
			 <AvailableRequest>
			  <RequestHeader>
			   <VERSION_5_0></VERSION_5_0>
			   <Authentication>
			    <PartnerID> $this->partner_id </PartnerID>
			    <PartnerPassword>". $this->partner_password ."</PartnerPassword>
			   </Authentication>
			   <Comments></Comments>
			  </RequestHeader>
			  <Body>
			   <VerifyDomainName>". $this->sanitize_for_xml($domain) ."</VerifyDomainName>
			  </Body>
			</AvailableRequest>
			";

			if($response = $this->send_request($xml, $this->availabiltiy_url)){
				
				$data =  $this->xml_to_object($response);
				$result = array();

				if(is_object($data->Body->NotAvailable)){
					$result['available'] = 0;
					$result['domain'] = strval($data->Body->NotAvailable->DomainName);
				} elseif(is_object($data->Body->Available)){
					$result['available'] = 1;
					$result['domain'] = strval($data->Body->Available->DomainName);
				}

				return $result;

			} else {
				return false;
			}
	    }

	    /*
	     *	Create a customer
	     *
	     *	@param array $customer of customer data.
	     *  See the XML below or the test file 
	     *  for expected values.
	     *
	     *  @return An array containing
	     *	description of transaction, newly created 
		 *	user id, and login name.  Returns error on failure.
	     */
	    public function create_customer($customer){

	    	if(!is_array($customer)){
	    		return false;
	    	}

	    	$xml = "
		    	<?xml version=\"1.0\" encoding=\"UTF-8\"?>
				 <UserRequest>
				  <RequestHeader>
				   <VERSION_5_0></VERSION_5_0>
				   <Authentication>
				    <PartnerID>". $this->partner_id ."</PartnerID>
				    <PartnerPassword>". $this->partner_password ."</PartnerPassword>
				   </Authentication>
				  </RequestHeader>
				  <Body>
				   <CreateIndividual>
				    <LoginName>". $customer['login_name'] ."</LoginName>
				    <Password>".  $customer['password'] ."</Password>
				    <FirstName>". $customer['first_name'] ."</FirstName>
				    <MiddleName>".$customer['middle_name'] ."</MiddleName>
				    <LastName>".  $customer['last_name'] ."</LastName>
				    <Address>
				     <AddressLine1>". $customer['address'] ."</AddressLine1>
				     <City>". $customer['city'] ."</City>
				     <State>". $customer['state'] ."</State>
				     <PostalCode>". $customer['zip'] ."</PostalCode>
				     <CountryCode>". $customer['country'] ."</CountryCode>
				    </Address>
				    <Phone>". $customer['phone'] ."</Phone>
				    <Fax>". $customer['fax'] ."</Fax>
				    <Email>". $customer['email'] ."</Email>
				    <AuthQuestion>". $customer['auth_question'] ."</AuthQuestion>
				    <AuthAnswer>". $customer['auth_answer'] ."</AuthAnswer>
				   </CreateIndividual>
				  </Body>
				 </UserRequest>
			";


			// TODO: CheckLoginNameAvailability first via UserLookupRequest

			if($response = $this->send_request($xml, $this->api_url)){
				
				$data =  $this->xml_to_object($response);

				// Something went wrong - validation error handling
				if(strval($data->Body->Status->Description) == "Data Validation Error"){
					
					$error 		= strval($data->Body->Status->ValidationError->Description);
					$error 	   .= " in ". strval($data->Body->Status->ValidationError->PathName);
					$error_num  = strval($data->Body->Status->ValidationError->StatusCode);

					return "Error ". $error_num . ": ". $error;

				} else {
					
					$result = array();
					$result['description'] 	= strval($data->Body->Status->Description);
					$result['user_id'] 		= strval($data->Body->UserID);
					$result['login_name'] 	= strval($data->Body->LoginName);
					
					return $result;
				}

			} else {
				return false;
			}
		}


	    /*
 	     *	Get domains associated with the current partner ID of this object 
	     *  registered in the specified range of time.
	     *
	     *	@args:	starting and ending timestamps.  If neither are supplied, 
	     *			an indefinite time range is assumed and the API will return all
	     *  		domains registered by this parner.
	     *
	     *  stamp format: yyyy-mm-ddThh:mm:ssZ where Z is timezone
	     */
	    public function get_partner_domains($start_stamp = "", $end_stamp = ""){

	    	if($start_stamp == ""){
	    		$start_stamp = "1990-01-01T00:00:00EST";
	    	}

	    	if($end_stamp == ""){
	    		$end_stamp = "2030-01-01T00:00:00EST";
	    	}

	    	$xml = "
	    		<?xml version=\"1.0\" encoding=\"UTF-8\"?>
				 <PartnerManagerRequest>
				  <RequestHeader>
				   <VERSION_6_3></VERSION_6_3>
				   <Authentication>
				    <PartnerID>". $this->partner_id ."</PartnerID>
				    <PartnerPassword>". $this->partner_password ."</PartnerPassword>
				   </Authentication>
				  </RequestHeader>
				  <Body>
				   <FindAllDomainsForPartner>
				    <PurchaseDateRange>
				     <DateRange>
				      <BeginDate>
				       <Date>". $start_stamp ."</Date>
				      </BeginDate>
				      <EndDate>
				       <Date>". $end_stamp ."</Date>
				      </EndDate>
				     </DateRange>
				    </PurchaseDateRange>
				   </FindAllDomainsForPartner>
				  </Body>
				 </PartnerManagerRequest>
	    	";
	    	
	    	//  
	    	if($response = $this->send_request($xml, $this->api_url)){
				
				$data =  $this->xml_to_object($response);
				return $data;
			}

	    }

	}
?>