<?php
	//error_reporting(0);
	include "dynmap-config.php";
	header('Content-Type: text/html; charset=utf-8');

	//Definice složek
	$cachefolder = $_SERVER["DOCUMENT_ROOT"] . "/.cache";
	$mapsfolder = $_SERVER["DOCUMENT_ROOT"] . "/.maps";

	//Vytvoření složek, pokud neexistují
	if (!file_exists($cachefolder)) {
		$old_umask = umask(0);
		mkdir($cachefolder, 0775);
		umask($old_umask);
	}

	if (!file_exists($mapsfolder)) {
		$old_umask = umask(0);
		mkdir($mapsfolder, 0775);
		umask($old_umask);
	}

	//Zpracování serverového požadavku
	if ($_GET["user"] == "server") {
		//Kontrola privatekey
		if ($_POST["privatekey"] == $privatekey) {
			//Zpracování TransferID

			if (isset($_POST["TransferID"])) {
				$file = fopen($cachefolder . "/TransferID", "w+");
				fwrite($file, $_POST["TransferID"]);
				fclose($file);
			}
			//Zpracování zápisu pozice do souboru
			if (!isset($_GET["maps"])) {
				$file = $cachefolder . "/dynmap.dat";
				$file = fopen($file, "w+");
				fwrite($file, "[map=" . $_POST["map"] . "]" . $_POST["data"]);
			} else {
			//Kontrola stažených souborů map
				//Čtení dat od serveru
				$maps = urldecode($_GET["maps"]);
				$maps = explode(" ", $maps);

				$downloadedMaps = scandir($mapsfolder);

				//Vypíše stažené mapy do array;
				foreach ($downloadedMaps as $downloadedMap) {
					if($downloadedMap != "." && $downloadedMap != "..") {
						$substrMaps[] = substr($downloadedMap, 0, -4);
					}
				}

				//Porovná stažené mapy a vypíše, které mapy se mají stáhnout
				foreach ($maps as $map) {
					if (!in_array($map, $substrMaps)) {
						echo $map . ";";
					}
				}
			}
		} elseif ($_GET["do"] == "uploadfile") {
			$file = fopen($cachefolder . "/TransferID", "r");
			$TransferID = fread($file, filesize($cachefolder . "/TransferID"));
			unlink($cachefolder . "/TransferID");
			if ($TransferID == $_GET["TransferID"]) {
				if (is_uploaded_file($_FILES['file']['tmp_name'])) 
	    		{
        			$uploadfile = $mapsfolder. "/" . $_GET["mapname"] . ".png";
        			if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
        				echo "Error.UploadDone";
        				$_SESSION["TransferID"] = null;
	        		} else {
    	        		print_r($_FILES);
        	    	}
    			} else {
			       	echo "Error.UploadFailed";
    	    	}
    	    } else {
    	    	echo "Error.TransferIDMismatch";
    	    }
		} else {
			echo "Error.PrivateKeyNotMatch";
		}
	} elseif ($_GET["user"] == "client"){
		$file = $cachefolder . "/dynmap.dat";
		$filehandle = fopen($file, "r");
		$data = fread($filehandle, filesize($file));
		echo $data;
	}	
?>