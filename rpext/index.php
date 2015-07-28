<?
//Reverseproxy (Externer-Server)

	require_once "../rpclasses/ReverseProxyExternal.php";


//KONFIGURATION//

	$vwdchost    = 'my.internal.server.local'; //Adresse des internen Zielservers (kann auch eine IP sein)
	$vwdcport    = '80'; //Port des internen Zielservers
	$vwdctimeout = '30'; //Verbindungstimeout
	$vwdcurl     = ''; //Zielpfad am internen Zielserver (Unterverzeichnis)

	$key		 = 'Test122@333#56842014dr#gTge4cC34'; //Schlüssel für ersten Verbindungsaufbau (muss 32 Zeichen lang und am RP-GW und RP-EXT Server ident sein)

	$keyrotation = 5; //Nach wie vielen Verbindungen soll der Schlüssel neu ausgehandelt werden

	$myuri 		= '/rpext';  //Eigener Pfad zum Reverseproxy (wird bei den Anfragen vom weitergeleiteten URI abgezogen)
	$myfwduri 	= substr($_SERVER["REQUEST_URI"],strlen($myuri));

	$rpgwhost	 = 'www.myserverdomain.net'; // Adresse des RP-Gateways
	$rpgwport    = '80'; //Port des internen RP-Gateways
	$rpgwtimeout = '30'; //Verbindungstimeout
	$rpgwurl     = '/rpgw'; //Zielpfad zum RP-Gateways (wenn in einem Unterverzeichnis)


//KONFIGURATION ENDE//
//Ab hier nichts mehr ändern!////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////


	$server = new ReverseProxyExternal($vwdchost, $vwdcport, $vwdctimeout, $vwdcurl, $key,$keyrotation,$myuri,$myfwduri,$rpgwhost,$rpgwport,$rpgwtimeout,$rpgwurl);
	$server->ServeRequest();


?>