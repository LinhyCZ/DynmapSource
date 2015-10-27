<?php 
	include "dynmap-config.php"
?>

<!DOCTYPE html>
<html>
<head>
	<title><?php echo $DynmapTitle;?></title>
	<meta charset="UTF-8">
	<meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' name='viewport' />
	<script type="text/javascript" src="http://code.jquery.com/jquery-2.1.4.min.js"></script>
	<style type="text/css">
	html, body {
		margin: 0;
		padding: 0;
		height: 100%;
		width: 100%;
		overflow: hidden;
	}
	#main {
		height: 100%;
		width: 100%;
		background: <?php echo $backgroundColor; ?>
	}

	#mainImage {
		display: block;
		margin: 0 auto;
	}

	#info {
		padding-right: 20px;
		padding-left: 20px;
		border-radius: 20px;
		border: 2px #1A1A1A solid;
		background: rgba(232, 232, 232, 0.2);
		position: fixed;
		left: 10px;
		top: 10px;
		font-family: Garamond;
		font-size: 20px;
		text-align: center;
		color: white;
	}

	#positionFrame {
		margin: 0 auto;
		z-index: 99999;
		position: absolute;
		top: 0;
		left: 50%;
	}

	#positionFrameWrap {
		width: inherit;
		height: inherit;
		z-index: 999999;
		position: relative;
	}

	.playerName {
		display: table;
	}

	.playerInfo {
		display: table;
		margin-top: -20px;
		margin-left: 24px;
		padding-right: 10px;
		padding-left: 10px;
		border-radius: 5px;
		border: 2px #1A1A1A solid;
		background: rgba(232, 232, 232, 0.2);
		font-family: Garamond;
		font-size: 12px;
		text-align: center;
		color: white;
	}

	.player {
		position: absolute;
		top: 500px;
		left: 500px;
		margin-top: -7px;
		margin-left: -10px;
	}

	#version {
		position: fixed;
		bottom: 10px;
		left: 10px;
		font-family: Garamond;
		font-size: 11px;
		color: white;
	}

	</style>
</head>
<body>
	<div id="main">
		<div id="info"></div>
		<img id="mainImage">
		<div id="positionFrame">
			<div id="positionFrameWrap"></div>
		</div>
	</div>
	<div id="version">Version: Beta 0.2, Developed by LinhyCZ, http://linhy.cz</div>
	<script type="text/javascript">
	//Request na server
	var firstRun = true;
	var xmultiplicator;
	var zmultiplicator;
	var oldTop = {};
	var oldLeft = {};
	var animatePlayerCSteamID;
	var animateTop;
	var animateLeft;
	var skipAnimate = false;


	$(function() {

		$(document).keydown(function(event) {
	   	    if (event.ctrlKey==true && (event.which == '61' || event.which == '107' || event.which == '173' || event.which == '109'  || event.which == '187'  || event.which == '189'  ) ) {
				event.preventDefault();
		    }
		});

		$(window).bind('mousewheel DOMMouseScroll', function (event) {
	    	if (event.ctrlKey == true) {
    	   		event.preventDefault();
	       	}
		});
		sendRequest();
		$("#mainImage").load(function() {if(firstRun==true){init();firstRun=false;};});
		$(window).resize(function(){init()});
		var interval = setInterval(sendRequest, <?php echo $syncinterval;?>);
	});
	function init() {
		if (document.body.offsetHeight > document.body.offsetWidth) {
			//Pozice obrázku
			document.getElementById("mainImage").style.height = "";
			document.getElementById("mainImage").style.width = "100%";
			document.getElementById("mainImage").style.position = "absolute";
			document.getElementById("mainImage").style.top = "50%";
			marginTop = Number(document.getElementById("mainImage").offsetHeight)/2*-1;
			document.getElementById("mainImage").style.marginTop = marginTop + "px";
			document.getElementById("positionFrame").style.top = "50%";
			document.getElementById("positionFrame").style.marginTop = marginTop + "px";
		} else {
			document.getElementById("mainImage").style.position = "static";
			document.getElementById("mainImage").style.margin = "0 auto";
			document.getElementById("mainImage").style.height = "100%";
			document.getElementById("mainImage").style.width = "";
			document.getElementById("mainImage").style.top = "";
			document.getElementById("mainImage").style.marginTop = "";
			document.getElementById("positionFrame").style.top = "";
			document.getElementById("positionFrame").style.marginTop = ""; 
		}

		document.getElementById("positionFrame").style.height = document.getElementById("mainImage").offsetHeight + "px";
		document.getElementById("positionFrame").style.width = document.getElementById("mainImage").offsetWidth + "px";
		var marginLeft = Number(document.getElementById("mainImage").offsetWidth) / 2 * -1;
		document.getElementById("positionFrame").style.marginLeft = marginLeft + "px";


		//Vypočítání poměru velikosti obrázku a skutečného rozlišení obrázku
		var naturalHeight = document.getElementById("mainImage").naturalHeight;
		var naturalWidth = document.getElementById("mainImage").naturalWidth;
		var offsetHeight = document.getElementById("mainImage").offsetHeight;
		var offsetWidth = document.getElementById("mainImage").offsetWidth;


		xmultiplicator =  Number(offsetWidth) / Number(naturalWidth);
		zmultiplicator = Number(offsetHeight) / Number(naturalHeight);


		sendRequest();
	}

	function sendRequest() {
		var conn;
		if(window.XMLHttpRequest) {
			conn = new XMLHttpRequest();
		} else {
			conn = new ActiveXObject("Microsoft.XMLHTTP");
		}
		conn.open("GET", "dynmap-core.php?user=client", false);
		conn.setRequestHeader("Content-type","application/x-www-form-urlencoded");
		conn.send();

		var data = conn.responseText;
		var data = data.split("]");

		var players = Number(data.length) - 2;
		if (players != 1) {s = "s"} else {s = ""};

		//Zjisštění aktuální mapy
		var map = data[0].substring(1);
		var map = map.split("=");
		var map = map[1];
		document.getElementById("info").innerHTML = "Current map: " + map + "<br>Currently " + players + " player" + s + " online";
		document.getElementById("mainImage").src = ".maps/" + map + ".png";
		
		//Vypsání hráčů
		if (firstRun == false) {
			document.getElementById("positionFrameWrap").innerHTML = "";
			for (var i = 1; i < data.length; i++) {
				if (data[i] != "") {
					player = data[i].substring(1);

					//Jméno hráče
					var playerName = player.split(";");
					var playerName = playerName[0];
					var playerName = playerName.split("=");
					var playerName = playerName[1];

					//CSteamID hráče
					var playerCSteamID = player.split(";");
					var playerCSteamID = playerCSteamID[1];
					var playerCSteamID = playerCSteamID.split("=");
					var playerCSteamID = playerCSteamID[1];

					//Pozice hráče
					var playerPosition = player.split(";");
					var playerPosition = playerPosition[2];
					var playerPosition = playerPosition.split("=");
					var playerPosition = playerPosition[1];
					var playerPosition = playerPosition.substring(1);
					var playerPosition = playerPosition.substring(0, playerPosition.length -1);

					//Pozice x
					var x = playerPosition.split(",");
					var x = x[0];

					//Pozice z
					var z = playerPosition.split(",");
					var z = z[2];

					//Přepočítání pozice x na hodnotu left
					if (Number(x) < 0) {
						var left = Number(x)*-1;
						var left = Number(left)/1.93630573;
						var left = 512 - Number(left);
						var left = Number(left) * Number(xmultiplicator);
					} else {
						var left = Number(x);
						var left = Number(left)/1.93630573;
						var left = Number(left) + 512;
						var left = Number(left) * Number(xmultiplicator);
					}

					//Přepočítání hodnoty x na hodnotu top
					if (Number(z) < 0) {
						var top = Number(z);
						var top = Number(top)/1.93630573;
						var top = 512 - Number(top);
						var top = Number(top) * Number(zmultiplicator);
					} else {
						var top = Number(z)*-1;
						var top = Number(top)/1.93630573;
						var top = Number(top) + 512;
						var top = Number(top) * Number(zmultiplicator);
					}
					document.getElementById("positionFrameWrap").innerHTML = document.getElementById("positionFrameWrap").innerHTML + '<div class="player" id="' + playerCSteamID + '"><img class="playerImage" src="cursor.png"><div class="playerInfo">' + playerName + '</div></div>';
					if (oldTop[playerCSteamID] != undefined) {	
						var topDiference = oldTop[playerCSteamID] - top;
						var leftDiference = oldLeft[playerCSteamID] - left;
						if (topDiference > 100 || topDiference < -100 || leftDiference > 100 || leftDiference < -100) {skipAnimate = true};
						document.getElementById(playerCSteamID).style.top = oldTop[playerCSteamID] + "px";
						document.getElementById(playerCSteamID).style.left = oldLeft[playerCSteamID] + "px";
					} else {
						document.getElementById(playerCSteamID).style.top = top + "px";
						document.getElementById(playerCSteamID).style.left = left + "px";
					}
					if (skipAnimate == false) {
						animatePlayerCSteamID = animatePlayerCSteamID + ";" + playerCSteamID;
						animateTop = animateTop + ";" + top;
						animateLeft = animateLeft + ";" + left;
					} else {
						skipAnimate = false;
						document.getElementById(playerCSteamID).style.top = top + "px";
						document.getElementById(playerCSteamID).style.left = left + "px";
					}

					oldTop[playerCSteamID] = top;
					oldLeft[playerCSteamID] = left;
				}
			}
			animate();
		};
	}

	function animate() {
		animatePlayerCSteamID = animatePlayerCSteamID.split(";");
		animateTop = animateTop.split(";");
		animateLeft = animateLeft.split(";");
		for (var i = 0; i < animatePlayerCSteamID.length; i++) {
			$("#" + animatePlayerCSteamID[i]).animate({top: animateTop[i], left: animateLeft[i]}, {duration: <?php echo $syncinterval ?>, queue: false});
		};
		animatePlayerCSteamID = "";
		animateTop = "";
		animateLeft = "";

		checkArray();
	}

	function checkArray() {
		var inputs = document.getElementsByClassName("player");
		var shownPlayers = []
		var array = Object.keys(oldTop);
		for (var i = 0; i < inputs.length; i++) {
  			shownPlayers.push(inputs[i].id);
		}

		for (var i = 0; i < array.length; i++) {
  			if(shownPlayers.indexOf(array[i]) == -1) {
				delete oldLeft[array[i]];
				delete oldTop[array[i]];
			}
		};
	}
	</script>
</body>
</html>

<!--

TODO:
Modrá a zlatá barva pro admina a VIP
Animace a směr pohledu
-->