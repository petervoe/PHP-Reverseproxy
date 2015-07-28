<?

require_once "ReverseProxy.php";

/**
 * ReverseProxyGateway
 * 
 * Reverseproxyserver-Gateway
 * Wird als Gateway in das interne Netz ausgeführt
 * und behandelt die Anfragen des ReverseproxyExternal-Servers.
 * 
 * @author	Peter Völkl <peter@voelkl.at>
 * @version	2015-07-28
 * 
 */
 class ReverseProxyGateway extends ReverseProxy{
	
	/**
	 * __construct
	 * 
	 * Konstruktor der Klasse
	 * Initialisiert die Klasse und die Klassenvariablen.
	 * 
	 * @param string $vwdchost		Adresse des internen Zielservers (kann auch eine IP sein)
	 * @param string $vwdcport		Port des internen Zielservers
	 * @param string $vwdctimeout	Verbindungstimeout
	 * @param string $vwdcurl		Zielpfad am internen Zielserver (Unterverzeichnis)
	 * @param string $key			Schlüssel für ersten Verbindungsaufbau (muss in RP-GW und RP-EXT Server ident sein)
	 * 
	 */
	function __construct($vwdchost, $vwdcport, $vwdctimeout, $vwdcurl, $key){
		parent::__construct($vwdchost, $vwdcport, $vwdctimeout, $vwdcurl, $key);
	}
		
	/**
	 * ServeRequest
	 * 
	 * Verarbeitet den eingehenden Request des ReverseProxyExternal-Servers.
	 * 
	 * $_POST[request]: ein eingehender Request wird an den internen Server weitergeleitet
	 * $_GET[newkey]: ein neuer Sitzungsschlüssel wird generiert und zurückgegeben
	 * 
	 */
	function ServeRequest(){
		//Wenn es noch keinen Sitzungsschlüssel gibt, wird der Startschlüssel verwendet
		session_start();
		if(!isset($_SESSION["key"]))
			$_SESSION["key"] = $this->key;
	
		//Prüfen ob Schlüsseländerung angefordert wurde und ggf. ausführen
		if(isset($_GET["newkey"])){
			
			//Neuen 256 Bit Schlüssel generieren (256 Bit >> 32 Byte)
			$old_session_key = $_SESSION["key"];
			$this->key="";
			
			//Die Schleife kann genutzt werden, wenn OpenSSL nicht verfügbar ist
			//for ($i=0; $i < 32; $i++) { 
			//	$key .=	chr(mt_rand(0,255));
			//}
			//wenn OpenSSL verfügbar ist wird openssl_random_pseudo_bytes verwendet
			$this->key = openssl_random_pseudo_bytes(32);
			$_SESSION["key"] = $this->key;
			
			//Neuen Schlüssel mit altem Schlüssel verschlüsseln und ausgeben
			echo base64_encode($this->rp_encrypt($_SESSION["key"],$old_session_key));
			
		} elseif (isset($_POST["request"])) { //Prüfen ob Request übergeben wurde
			//Request aus Parameter übernehmen
			//$request = rawurldecode(base64_decode($_POST["request"])); //URL-Decodierung der Parameter erfolgt bereits durch Web-Server
			$request = base64_decode($_POST["request"]);
			
			//Request entschlüsseln
			$request = $this->rp_decrypt($request,$_SESSION["key"]);
	
			//Prüfen, ob es sich um einen HTTP/1.1 Request handelt
			if(!strpos($request,"HTTP/1.1")){
				//Kein HTTP/1.1 Request
				$returndata["header"] = Array();
				$returndata["content"] = "Kein gültiger Request:\n".$request."\n\nIV:".
										$_SESSION["iv_dc"]."\n".
										"KEY:".$_SESSION["key"]."\n".
										"IV_SIZE:".$_SESSION["iv_size_dc"]."\n".
										"POSTREQUEST:".$_POST["request"];
				$returndata_serial = serialize($returndata);
				echo $returndata_serial;
				die();
			}
	
			//Verbindung zu Zielserver aufbauen, Request übergeben und Antwort entgegennehmen
			$fp = fsockopen($this->vwdchost, $this->vwdcport, $errno, $errstr, $this->vwdctimeout);
			if($fp)
			{
				////////////////////////////////////////
			    // Auswerten der Antwort.
			    ////////////////////////////////////////
			    $data = "";
			    fwrite($fp, $request);
			    while (!feof($fp))
			    {
			    	$data .= fgets($fp, 128);
			    }
			    fclose($fp);
				
				//Header extrahieren
				$headerend = stripos($data, "\r\n\r\n");
				$headerdata = substr($data,0,$headerend);
				$resultmsg = substr($data,$headerend+4);
				$header = explode("\r\n",$headerdata); //Header in einzelne Felder zerlegen
			}
			else
			{
			    $resultmsg = $errstr;
			}
			
			//Rückgabewerte für Übermittlung serialisieren
			$returndata["header"] = $header;
			$returndata["content"] = $resultmsg;
			$returndata_serial = serialize($returndata);
			//Serialisierte Rückgabewerte verschlüsseln
			$returndata_serial = $this->rp_encrypt($returndata_serial,$_SESSION["key"]);
			
			///Verschlüsselte Rückgabewerte ausgeben
			echo $returndata_serial;		
		}
	}
	
	
}

?>