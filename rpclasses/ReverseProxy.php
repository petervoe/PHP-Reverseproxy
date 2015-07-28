<?

/**
 * ReverseProxy
 * 
 * Abstrakte Klasse für die Instanzierung der Reverseproxyserver
 * 
 * @author	Peter Völkl <peter@voelkl.at>
 * @version	2015-07-28
 * 
 */
 abstract class ReverseProxy{
	
	protected $vwdchost;
	protected $vwdcport;
	protected $vwdctimeout;
	protected $vwdcurl;
	protected $key;
	
	
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
	 * 
	 */
	protected function __construct($vwdchost, $vwdcport, $vwdctimeout, $vwdcurl, $key){
		
		$this->vwdchost 	= $vwdchost;
		$this->vwdcport   	= $vwdcport;
		$this->vwdctimeout	= $vwdctimeout;
		$this->vwdcurl 		= $vwdcurl;
		$this->key 			= $key;
	}
	
	/**
	 * rp_encrypt
	 * 
	 * Verschlüsselt einen String
	 * Cypher: RIJNDAEL_256 (AES256), CBC
	 * Schlüssellänge: 256 Bit (32 Byte)
	 * Der Initialisierungs-Vektor wird automatisch generiert und vor den Ciphertext gehängt.
	 * 
	 * @param string $c_text	Plaintext
	 * @param string c_key		Schlüssel, 256 Bit (32 Byte)
	 * 
	 */
	protected function rp_encrypt($c_text, $c_key){
		$c_text .= str_repeat("\0", strlen($c_key)-(strlen($c_text) % strlen($c_key)));

		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC); 
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);

		$_SESSION["iv_size_ec"] = $iv_size;
		$_SESSION["iv_ec"] = $iv;
		return $iv.mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $c_key, $c_text, MCRYPT_MODE_CBC, $iv);
	}
	
	/**
	 * rp_decrypt
	 * 
	 * Entschlüsselt einen String
	 * Cypher: RIJNDAEL_256 (AES256), CBC
	 * Schlüssellänge: 256 Bit (32 Byte)
	 * Der Initialisierungs-Vektor wird automatisch aus dem übergebenen String extrahiert (befindet sich vor dem Ciphertext).
	 * 
	 * @param string $c_text	Initialisierungs-Vektor + Ciphertext
	 * @param string c_key		Schlüssel, 256 Bit (32 Byte)
	 * 
	 */
	protected function rp_decrypt($c_text, $c_key){
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC); 
		$iv = substr($c_text, 0, $iv_size);
		
		$_SESSION["iv_size_dc"] = $iv_size;
		$_SESSION["iv_dc"] = $iv;
		
		//Bei der Entschlüsselung wird das Ende mit binären Nullen '\0' aufgefüllt um die Blockgröße zu erreichen. 
		// Diese müssen abgeschnitten werden (rtrim).
		return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $c_key, substr($c_text, $iv_size), MCRYPT_MODE_CBC, $iv),"\0");
	}
		
	/**
	 * ServeRequest
	 * 
	 * Verarbeitet den eingehenden Request des ReverseProxyExternal- oder ReverseProxyGateway-Servers.
	 * 
	 */
	abstract function ServeRequest();
	
}

?>