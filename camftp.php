<?php
	date_default_timezone_set('Europe/Paris');	
	ini_set('memory_limit', '512M');
	
	// DEBUG : permet d'afficher les erreurs
	ini_set('display_errors', 1); 
	error_reporting(E_ALL); 
	
	//*************************************** ChangeLog *********************************************************
	// V0.0 : alpha fonctionnel
	// 
	// 
	// 
	// 
	//****************************************** Paramètres à modifier ******************************************************
	// Snapshot Cameras possibles
	$snapshot = array( // Pas d'espace dans le noms, utilisez l'underscore
			1 => array("name" => "Camera_Veranda", "url" => "http://xxxxx:xxxx@xx.xxx.xxx.xxx:xxx/snapshot.xxx"),
			2 => array("name" => "Camera_Jardin", "url" => "http://xxxxx:xxxxx@xx.xxx.xxx.xxx:xxx/snapshot.xxx"),
			);
	$cammax = 2; // mettre le nombre total de caméras du tableau $snapshot
	//*
	// Serveurs FTP cibles
	$ftp = array(
			1 => array("server" => "camera.eedomus.com", "user" => "cameraXXXXX", "pwd" => "xxxxxxx", "path" => ""),
			2 => array("server" => "camera.eedomus.com", "user" => "cameraXXXXX", "pwd" => "xxxxxxx", "path" => ""),
			3 => array("server" => "xx.xx.xxx.xxx", "user" => "xxxxxx", "pwd" => "xxxxxxxxxxx", "path" => "xxxxx"));
	//*
	$ftpmax = 3; // mettre le nombre total de serveurs ftp du tableau $ftp
	$ftpcentral = 3; // mettre le numéro du serveur ftp centralisateur dans le tableau $ftp
	//*
	$mail_dest = "xxxxx.xxxxxx@gmail.com";
	$mail_from = "Notification Eedomus";
	$mail_subj = "Notification Eedomus - Cameras";	
	//***********************************************************************************************************************************************************
	// Parametres d'appel du script :
	//	numcam  : numéro de la caméra à capturer. Si numcam = 99, toutes les caméras sont transmises au serveur de numéro $ftpcentral
	//	numftp1 : numéro de serveur ftp cible de la capture. Si numcam = 99, ce parametre n'est pas pris en compte
	//	numftp2 : numéro de serveur ftp cible complémentaire. Si numcam = 99, ce parametre n'est pas pris en compte
	//	nbsnap  : nombre de captures transmises (1 seul par défaut si non renseigné)
	//	updelay : delay en secondes entre deux transferts de snapshot, 1s par défaut. Si nbsnap est vide ou égal à 1, le paramétre n'est pas pris en compte
	//
	//  exemple : camftp.php?numcam=99&nbsnap=10&updelay=3 => transfert de 10 captures toutes les 3 secondes de toutes les cameras dans le serveur $ftpcentral
	//  exemple : camftp.php							   => transfert d'une capture de toutes les cameras dans le serveur $ftpcentral
	//  exemple : camftp.php?numcam=1&numftp1=1&numftp2=3  => transfert d'une capture de la caméra 1 dans les serveurs 1 et 3
	//***********************************************************************************************************************************************************
	// récupération des paramètres d'appel du script
	$numcam = 99; //toutes les caméras par défaut
	$numftp1 = 0;
	$numftp2 = 0;
	$nbsnap = 1; // 1 capture par défaut
	$updelay = 1; // 1 seconde par défaut
	$getmail = 0;
	if (isset($_GET['numcam'])) {
		if ($_GET['numcam'] <= $cammax && $_GET['numcam'] > 0) {
			$numcam = $_GET['numcam']; 
		}
	}
	if (isset($_GET['numftp1'])) {
		if ($_GET['numftp1'] <= $ftpmax && $_GET['numftp1'] > 0) {
			$numftp1 = $_GET['numftp1']; 
		}
	}
	if (isset($_GET['numftp2'])) {
		if ($_GET['numftp2'] <= $ftpmax && $_GET['numftp2'] > 0) {
			$numftp2 = $_GET['numftp2']; 
		}
	}
	if (isset($_GET['nbsnap'])) {
		$nbsnap = $_GET['nbsnap']; 
	}
	if (isset($_GET['updelay'])) {
		$updelay = $_GET['updelay']; 
	}
	if (isset($_GET['getmail'])) {
		$getmail = $_GET['getmail']; 
	}
	//echo "<p>Camera : ".$numcam." FTP : ".$numftp1." Nb : ".$nbsnap." Delay : ".$updelay;
	
	$today = date("YmdHis");
	$index = 1000;
	$nbtotalsnap = 0;
	
	// connexions aux serveurs FTP
	if ($numcam == 99 && $ftpmax > 0) {
		$conn_id_central = ftp_connect($ftp[$ftpcentral]["server"]);
		ftp_pasv($conn_id_central, true);
		$login_result_central = ftp_login($conn_id_central, $ftp[$ftpcentral]["user"], $ftp[$ftpcentral]["pwd"]);
		if ((!$conn_id_central) || (!$login_result_central)) {
           	echo "<p> Connexion au serveur ".$ftp[$ftpcentral]["server"]." pour l'utilisateur ".$ftp[$ftpcentral]["user"]." KO !";
    		exit;
		}
		if ($ftp[$ftpcentral]["path"] != "") {
			ftp_chdir($conn_id_central, $ftp[$ftpcentral]["path"]);
		}
	} else {
		if ($numftp1 > 0) {
			$conn_id1 = ftp_connect($ftp[$numftp1]["server"]);
			ftp_pasv($conn_id1, true);
			$login_result1 = ftp_login($conn_id1, $ftp[$numftp1]["user"], $ftp[$numftp1]["pwd"]);
			if ((!$conn_id1) || (!$login_result1)) {
           		echo "<p> Connexion au serveur ".$ftp[$numftp1]["server"]." pour l'utilisateur ".$ftp[$numftp1]["user"]." KO !";
    			exit;
			}
			if ($ftp[$numftp1]["path"] != "") {
				ftp_chdir($conn_id1, $ftp[$numftp1]["path"]);
			}
		}
		if ($numftp2 > 0 && $numftp2 != $numftp1) {
			$conn_id2 = ftp_connect($ftp[$numftp2]["server"]);
			ftp_pasv($conn_id2, true);
			$login_result2 = ftp_login($conn_id2, $ftp[$numftp2]["user"], $ftp[$numftp2]["pwd"]);
			if ((!$conn_id2) || (!$login_result2)) {
           		echo "<p> Connexion au serveur ".$ftp[$numftp2]["server"]." pour l'utilisateur ".$ftp[$numftp2]["user"]." KO !";
    			exit;
			}
			if ($ftp[$numftp2]["path"] != "") {
				ftp_chdir($conn_id2, $ftp[$numftp2]["path"]);
			}
		}
	}
	
	// Transfert des captures
	while ($nbtotalsnap < $nbsnap) {
		if ($numcam == 99) {
			if ($getmail == 1) {
				$boundary = md5(uniqid(microtime(), TRUE));
				// Headers
				$headers = 'From: '.$mail_from."\r\n";
				$headers .= 'Mime-Version: 1.0'."\r\n";
				$headers .= 'Content-Type: multipart/mixed;boundary='.$boundary."\r\n";
				$headers .= "\r\n";
 				// Message
				$msg = 'Notification Eedomus'."\r\n\r\n";
 				// Texte
				$msg .= '--'.$boundary."\r\n";
				$msg .= 'Content-type:text/plain;charset=iso-8859-1'."\r\n";
				$msg .= 'Content-transfer-encoding:8bit'."\r\n";
				$msg .= 'Merci de consulter les captures ci-jointes'."\r\n";
 			}
			foreach ($snapshot as $capture) {
				$img = fopen($capture["url"],"r");
				$remFile = $capture["name"]."_".$today."_".$index.".jpg";
				$mailFile = "Cam".$today.".jpg";
				$ftpresultcentral = ftp_fput($conn_id_central, $remFile, $img, FTP_BINARY);
				fclose($img);
				if ($getmail == 1) {
					// Pièce jointe
					$img = fopen($capture["url"],"r");
					$content = stream_get_contents($img);
					$content = chunk_split(base64_encode($content));
					$msg .= '--'.$boundary."\r\n";
					$msg .= 'Content-type: \'image/jpeg\';name="'.$mailFile.'"'."\r\n";
					$msg .= 'Content-transfer-encoding:base64'."\r\n";
					$msg .= $content."\r\n";
					$msg .= '--'.$boundary."\r\n";
					fclose($img);
				}
			}
			$index = $index + $updelay;
			if ($getmail == 1) {
				// Function mail()
				//echo "Envoi mail ".$mail_dest. " ".$mailFile;
 				mail($mail_dest, $mail_subj, $msg, $headers);	
 			}
			if ($nbsnap > 1) {
				sleep($updelay);
			}
		} else {
			$remFile = $snapshot[$numcam]["name"]."_".$today."_".$index.".jpg";
			$mailFile = "Cam".$today.".jpg";
			$index = $index + $updelay;
			if ($numftp1 > 0) {
				$img = fopen($snapshot[$numcam]["url"], "r");
				$ftpresult1 = ftp_fput($conn_id1, $remFile, $img, FTP_BINARY);
				fclose($img);
			}
			if ($numftp2 > 0 && $numftp2 != $numftp1) {
				$img = fopen($snapshot[$numcam]["url"], "r");
				$ftpresult2 = ftp_fput($conn_id2, $remFile, $img, FTP_BINARY);
				fclose($img);
			}
			if ($getmail == 1) {
				$boundary = md5(uniqid(microtime(), TRUE));
				// Headers
				$headers = 'From: '.$mail_from."\r\n";
				$headers .= 'Mime-Version: 1.0'."\r\n";
				$headers .= 'Content-Type: multipart/mixed;boundary='.$boundary."\r\n";
				$headers .= "\r\n";
 				// Message
				$msg = 'Notification Eedomus'."\r\n\r\n";
 				// Texte
				$msg .= '--'.$boundary."\n";
				$msg .= 'Content-type:text/plain; charset=ISO-8859-1'."\r\n";
				$msg .= 'Content-transfer-encoding: 8bit'."\r\n";
				$msg .= 'Merci de consulter la capture ci-jointe.'."\r\n";
				// Pièce jointe
				$img = fopen($snapshot[$numcam]["url"], "r");
				$content = stream_get_contents($img);
				$content = chunk_split(base64_encode($content));
				$msg .= '--'.$boundary."\n";
				$msg .= 'Content-type: \'image/jpeg\';name="'.$mailFile.'"'."\r\n";
				$msg .= 'Content-transfer-encoding:base64'."\r\n";
				$msg .= $content."\r\n";
				$msg .= '--'.$boundary."\r\n";
				$msg .= '--'.$boundary."\r\n";
 				// Function mail()
 				mail($mail_dest, $mail_subj, $msg, $headers);	
 				fclose($img);
			}
			if ($nbsnap > 1) {
				sleep($updelay);
			}
			
		}
		$nbtotalsnap++;
	}
	
	
	// fermeture des connexions ftp
	if ($numcam == 99) {
		ftp_close($conn_id_central);
	} else {
		if ($numftp1 > 0) {
			ftp_close($conn_id1);
		}
		if ($numftp2 > 0 && $numftp2 != $numftp1) {
			ftp_close($conn_id2);
		}
	}
	echo "1";
?>