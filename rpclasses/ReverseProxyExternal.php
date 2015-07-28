<?

require_once "ReverseProxy.php";

/**
 * ReverseProxyExternal
 * 
 * Externer Endpunkt des Reverseproxyservers
 * Wird auf einem externen Webserver ausgeführt
 * und leitet die Anfragen an den internen 
 * ReverseproxyGateway-Server weiter.
 * 
 * @author	Peter Völkl <peter@voelkl.at>
 * @version	2015-07-28
 * 
 */
 class ReverseProxyExternal extends ReverseProxy{
	
	protected $keyrotation;
	protected $myuri;
	protected $myfwduri;
	protected $rpgwhost;
	protected $rpgwport;
	protected $rpgwtimeout;
	protected $rpgwurl;
	
	/**
	 * __construct
	 * 
	 * Konstruktor der Klasse
	 * Initialisiert die Klasse und die Klassenvariablen
	 * 
	 * @param string $vwdchost		Adresse des internen Zielservers (kann auch eine IP sein)
	 * @param string $vwdcport		Port des internen Zielservers
	 * @param string $vwdctimeout	Verbindungstimeout
	 * @param string $vwdcurl		Zielpfad am internen Zielserver (Unterverzeichnis)
	 * @param string $key			Schlüssel für ersten Verbindungsaufbau (muss in RP-GW und RP-EXT Server ident sein)
	 * @param string $keyrotation	Nach wie vielen Verbindungen soll der Schlüssel neu ausgehandelt werden
	 * @param string $myuri			Eigener Pfad zum Reverseproxy (wird bei den Anfragen vom weitergeleiteten URI abgezogen)
	 * @param string $rpgwhost		Adresse des RP-Gateways
	 * @param string $rpgwport		Port des internen RP-Gateways
	 * @param string $rpgwtimeout	Verbindungstimeout der Verbindung zum RP-Gateway
	 * @param string $rpgwurl		Zielpfad zum RP-Gateways (wenn in einem Unterverzeichnis)
	 * 
	 */
	function __construct($vwdchost, $vwdcport, $vwdctimeout, $vwdcurl, $key,$keyrotation,$myuri,$myfwduri,$rpgwhost,$rpgwport,$rpgwtimeout,$rpgwurl){
		parent::__construct($vwdchost, $vwdcport, $vwdctimeout, $vwdcurl, $key);
		
		$this->keyrotation=$keyrotation;
		$this->myuri=$myuri;
		$this->myfwduri=$myfwduri;
		$this->rpgwhost=$rpgwhost;
		$this->rpgwport=$rpgwport;
		$this->rpgwtimeout=$rpgwtimeout;
		$this->rpgwurl=$rpgwurl;
	}
		
	/**
	 * ServeRequest
	 * 
	 * Verarbeitet den eingehenden Request des zugreifenden Clients 
	 * und leitet diesen an den ReverseProxyGateway-Server weiter.
	 * 
	 */
	function ServeRequest(){
		
		//Wenn es noch keinen Sitzungsschlüssel gibt, wird der Startschlüssel verwendet
		session_start();
		if(!isset($_SESSION["key"]))
			$_SESSION["key"] = $this->key;
		if(!isset($_SESSION["key_usage"]))
			$_SESSION["key_usage"] = $keyrotation;
	
		////////////////////////////////////////
	    // Request weitersenden.
	    ////////////////////////////////////////		
	
		$request_headers = getallheaders(); //Übergeben bekommene Requesetheader auslesen
	    $request = $_SERVER['REQUEST_METHOD']." ".$this->vwdcurl.$this->myfwduri." HTTP/1.1\r\n";
	    $request.= "Host: ".$this->vwdchost."\r\n";
	    $request.= "Connection: Close\r\n";
	    foreach ($request_headers as $v_key => $value) { //Requesetheader weiterreichen
	    	if(strtoupper($v_key) != "HOST" && strtoupper($v_key) != "CONNECTION" && strtoupper($v_key) != "IF-MODIFIED-SINCE"){
	    		$request .= $v_key.": ".$value."\r\n";
	    	}
		}
	    $request.= "\r\n";
		
		if($_SERVER['REQUEST_METHOD'] != "GET"){
			$request_body = file_get_contents('php://input'); //Payload auslesen
			if($request_body){ //Request Payload weiterschicken
				$request .= $request_body;
				$request .= "\r\n";
			}	
		}
		
		
		//Wenn Sitzungsschlüssel abgelaufen ist, wird er neu angefordert
		if($_SESSION["key_usage"] >= $this->keyrotation){
			//Sitzungsschlüssel neu anfordern, mit bisherigem Schlüssel entschlüsseln und in Session speichern
			
			//Request an RP-Gateway übermitteln
			$fp = fsockopen($this->rpgwhost, $this->rpgwport, $errno, $errstr, $this->rpgwtimeout);
			if($fp)
			{
				$request_k = "GET ".$this->rpgwurl."/?newkey=1 HTTP/1.1\r\n";
			    $request_k.= "Host: ".$this->rpgwhost."\r\n";
			    $request_k.= "Connection: Close\r\n";
				$request_k.= "Accept: text/plain, text/html\r\n";
				$request_k.= "Accept-Charset: utf-8\r\n";
				$request_k.= "Cache-Control: no-cache\r\n";
				//Wenn Cookie in Session vorhanden wird es mit übergeben
				if(isset($_SESSION["gw_cookie"]))
					$request_k.= "Cookie: ".$_SESSION["gw_cookie"]."\r\n";
			    $request_k.= "\r\n";
	
			    $data = "";
			    fwrite($fp, $request_k);
			    while (!feof($fp))
			    {
			    	$data .= fgets($fp, 128);
			    }
			    fclose($fp);
		
				//Nachricht extrahieren
				$headerend = stripos($data, "\r\n\r\n");
				$resultmsg = substr($data,$headerend+4);
				$resultmsg = substr($resultmsg,stripos($resultmsg, "\r\n")+2, -7); //erste und letzte Zeile abschneiden, da diese die Content-Length enthält
			
				
				//Header extrahieren
				$headerdata = substr($data,0,$headerend);
				$header = explode("\r\n",$headerdata); //Header in einzelne Felder zerlegen
				//Nach Cookieheader suchen und in Session speichern
				foreach ($header as $v_key => $value) {
					$header_line = explode(": ",$value);
					if($header_line[0]=="Set-Cookie")
						$_SESSION["gw_cookie"] = $header_line[1];
				}
			
				//Neuen Sitzungsschlüssel mit altem Schlüssel entschlüsseln und speichern
				$_SESSION["key"] = $this->rp_decrypt(base64_decode($resultmsg),$_SESSION["key"]);
	
				//Nutzung des Sitzungsschlüssels auf 0 zurücksetzen
				$_SESSION["key_usage"] = 0;	
			}
		}
	
	
		//Nachfolgend nun den Request ($request) an den RP-GW übermitteln und Antwort ($returndata_serial) empfangen
		
		//Request verschlüsseln
		$request = $this->rp_encrypt($request, $_SESSION["key"]);
		
		//Request an RP-Gateway übermitteln
		$fp = fsockopen($this->rpgwhost, $this->rpgwport, $errno, $errstr, $this->rpgwtimeout);
		if($fp)
		{
			$request_param = rawurlencode(base64_encode($request));
			
			$request_gw = "POST ".$this->rpgwurl."/ HTTP/1.1\r\n";
		    $request_gw.= "Host: ".$this->rpgwhost."\r\n";
		    $request_gw.= "Connection: Close\r\n";
			$request_gw.= "Accept: text/plain, text/html\r\n";
			$request_gw.= "Accept-Charset: utf-8\r\n";
			//Wenn Cookie in Session vorhanden wird es mit übergeben
			if(isset($_SESSION["gw_cookie"]))
				$request_gw.= "Cookie: ".$_SESSION["gw_cookie"]."\r\n";
			$request_gw.= "Content-Length:".strlen("request=".$request_param)."\r\n";
			$request_gw.= "Content-Type: application/x-www-form-urlencoded\r\n";
			$request_gw.= "Cache-Control: no-cache\r\n";
		    $request_gw.= "\r\n";
			$request_gw.= "request=".$request_param;
			$request_gw.= "\r\n";
			
		    $data = "";
		    fwrite($fp, $request_gw);
		    while (!feof($fp))
		    {
		    	$data .= fgets($fp, 128);
		    }
		    fclose($fp);
	
			//Nachricht extrahieren
			$headerend = stripos($data, "\r\n\r\n");
			$resultmsg = substr($data,$headerend+4);
			$resultmsg = substr($resultmsg,stripos($resultmsg, "\r\n")+2, -7); //erste und letzte Zeile abschneiden, da diese die Content-Length enthält
			
			//Header extrahieren
			$headerdata = substr($data,0,$headerend);
			$header = explode("\r\n",$headerdata); //Header in einzelne Felder zerlegen
	
			//Nach Cookieheader suchen und in Session speichern
			foreach ($header as $v_key => $value) {
				$header_line = explode(": ",$value);
				if($header_line[0]=="Set-Cookie")
					$_SESSION["gw_cookie"] = $header_line[1];
			}
		}
		else
		{
		    $resultmsg = ""; //Bei einem Fehler wird die abgerufene Ausgabe verworfen
		    echo $errstr;
		}
		$returndata_serial = $resultmsg;
		
	
		// Ausgabedaten entschlüsseln
		$_SESSION["key_usage"]++;
		$returndata_serial = $this->rp_decrypt($returndata_serial, $_SESSION["key"]);
		// Ausgabewerte deserialisieren
		$returndata = unserialize($returndata_serial);
		
		if(!$returndata) //Wenn keine Daten zurückgeliefert wurden. 
			die();
		
		/// AUSGABE
	
		header_remove(); //Eigenen Defaultheader löschen
		//Headerdaten  von der Zeilseite an eigenen Rückgabeheader übergeben
		foreach ($returndata["header"] as $v_key => $value) {
			if($v_key > 0)
				header($value);
		}
		
		echo $returndata["content"];
	
		/// AUSGABE */
		
		
	}
	
	
}

?>