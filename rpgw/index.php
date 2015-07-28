<?
//Reverseproxy (Gateway-Server)


	require_once "../rpclasses/ReverseProxyGateway.php";

//KONFIGURATION//

	$vwdchost    = 'my.internal.server.local'; //Adresse des internen Zielservers (kann auch eine IP sein)
	$vwdcport    = '80'; //Port des internen Zielservers
	$vwdctimeout = '30'; //Verbindungstimeout
	$vwdcurl     = ''; //Zielpfad am internen Zielserver (Unterverzeichnis)

	$key		 = 'Test122@333#56842014dr#gTge4cC34'; //Schlüssel für ersten Verbindungsaufbau (muss in GW und EXT Server ident sein)


//KONFIGURATION ENDE//
//Ab hier nichts mehr ändern!////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////

	$server = new ReverseProxyGateway($vwdchost, $vwdcport, $vwdctimeout, $vwdcurl, $key);
	$server->ServeRequest();


?>