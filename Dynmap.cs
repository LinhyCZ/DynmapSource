using Rocket.API;
using Rocket.API.Collections;
using Rocket.Core.Logging;
using Rocket.Core.Plugins;
using Rocket.Unturned;
using Rocket.Unturned.Chat;
using Rocket.Unturned.Player;
using SDG.Unturned;
using Steamworks;
using System;
using System.Collections.Generic;
using System.IO;
using System.Net;
using System.Security.Cryptography;
using System.Text;
using System.Timers;
using UnityEngine;

namespace dynmap.core
{
    public class DynmapConfiguration : IRocketPluginConfiguration
    {
        //Konfigurační soubor
        public string PrivateKey;
        public int syncInterval;
        public string WebCoreAddress;

        public void LoadDefaults()
        {
            PrivateKey = "MySecretPrivateKey";
            syncInterval = 5000;
            WebCoreAddress = "http://localhost";
        }
    }

    public class Dynmap : RocketPlugin<DynmapConfiguration>
    {
        //Definice proměnných
        public static Dynmap Instance;
        public List<CSteamID> Nicks = new List<CSteamID>();
        public Timer myTimer;
        public string directory = Directory.GetCurrentDirectory();
        public string[] maps;
        public string sendMaps;
        public string data = string.Empty;
        public string url = string.Empty;
        public string encoded = string.Empty;
        public string output = string.Empty;
        public string map = string.Empty;
        public string[] uploadMaps;
        public string PrivateKey;
        public string postData;
        public bool firstrun = true;
        public bool shutdown = false;
        public string characterName;
        public int rotation;
        public string playerStatus;


        protected override void Load()
        {   
            //Načtení privátního klíče a složky Maps
            PrivateKey = Configuration.Instance.PrivateKey;
            maps = Directory.GetDirectories(directory + @"\..\..\..\Maps");


            //Vypsání map na serveru
            foreach (string splitMap in maps)
            {
                var map = splitMap.Split('\\');
                int length = map.Length;
                sendMaps += map[length - 1] + ";";
            }

            //Odeslání map serveru do PHP skriptu
            var mapUrl = Configuration.Instance.WebCoreAddress + "/dynmap-core.php?user=server&maps=" + Uri.EscapeDataString(sendMaps);
            string postData = "&privatekey=" + PrivateKey;
            var post = Encoding.ASCII.GetBytes(postData);

            HttpWebRequest request = (HttpWebRequest)WebRequest.Create(mapUrl);
            request.Method = "POST";
            request.ContentType = "application/x-www-form-urlencoded";
            request.ContentLength = post.Length;
            using (var stream = request.GetRequestStream())
            {
                stream.Write(post, 0, post.Length);
            }

            HttpWebResponse response = (HttpWebResponse)request.GetResponse();
            using (Stream stream = response.GetResponseStream())
            {
                StreamReader reader = new StreamReader(stream, Encoding.UTF8);
                output = reader.ReadToEnd();
            }

            //Deaktivuje plugin, pokud se PrivateKey neshoduje
            
            if(output == "Error.PrivateKeyNotMatch")
            {
                Logger.LogError("Priavte keys in dynmap-config.php and in Dynmap.configuration.xml doesn't match!");
                Logger.LogError("Unloading plugin!");
                return;
            }

            //Příjem názvů map, které se ještě nenacházejí na serveru
            var sentMessage = false;

            uploadMaps = output.Split(';');
            for (var o = 0; o < uploadMaps.Length; o++)
            {
                if (sentMessage == false) { Logger.LogWarning("Uploading map files to the server! This may take some time!"); sentMessage = true; };
                if (uploadMaps[o] != string.Empty)
                {
                    //Generování TransferID

                    RNGCryptoServiceProvider rng = new RNGCryptoServiceProvider();
                    byte[] rndNumber = new byte[20]; 
                    rng.GetBytes(rndNumber);
                    string TransferID = Convert.ToBase64String(rndNumber);
                    
                    //TransferID
                    TransferID = TransferID.Remove(TransferID.Length - 1);
                    
                    
                    //Odeslání TransferID na server 
                    url = Configuration.Instance.WebCoreAddress + "/dynmap-core.php?user=server";
                    string TransferIDdata = "TransferID=" + Uri.EscapeDataString(TransferID) + "&privatekey=" + PrivateKey;
                    var postTransferID = Encoding.ASCII.GetBytes(TransferIDdata);

                    CookieContainer cookies = new CookieContainer();
                    HttpWebRequest requestTransferID = (HttpWebRequest)WebRequest.Create(url);

                    requestTransferID.Method = "POST";
                    requestTransferID.ContentType = "application/x-www-form-urlencoded";
                    requestTransferID.ContentLength = TransferIDdata.Length;
                    requestTransferID.CookieContainer = cookies;
                    using (var stream = requestTransferID.GetRequestStream())
                    {
                        stream.Write(postTransferID, 0, postTransferID.Length);
                    }

                    HttpWebResponse responseTransferID = (HttpWebResponse)requestTransferID.GetResponse();
                    using (Stream stream = responseTransferID.GetResponseStream())
                    {
                        StreamReader reader = new StreamReader(stream, Encoding.UTF8);
                        output = reader.ReadToEnd();
                    }
 

                    //Nahrání souborů map na server
                    System.Net.WebClient Client = new System.Net.WebClient ();
                    Client.Headers.Add("Content-Type", "binary/octet-stream");
                    byte[] result = Client.UploadFile(Configuration.Instance.WebCoreAddress + "/dynmap-core.php?user=server&do=uploadfile&TransferID=" + Uri.EscapeDataString(TransferID) + "&mapname=" + uploadMaps[o], "POST", directory + @"\..\..\..\Maps\" + uploadMaps[o] + @"\Map.png"); 
                    String s = System.Text.Encoding.UTF8.GetString (result,0,result.Length);

                    if (s == "Error.UploadDone")
                    {
                        Logger.LogWarning("Uploaded " + uploadMaps[o]);
                    }
                    else if (s == "Error.UploadFailed")
                    {
                        Logger.LogWarning("Uploading " + uploadMaps[o] + " failed.");
                    }
                }
                if (o == uploadMaps.Length - 1) { Logger.LogWarning("Uploading done!");};
                
            }

            //Časovač odesílající data o pozici na server
            myTimer = new System.Timers.Timer();
            myTimer.Elapsed += new ElapsedEventHandler(callFunc);
            myTimer.Interval = Configuration.Instance.syncInterval;
            myTimer.Enabled = true;

            //Přidání hráčů do listu při načtení pluginu
            int f = 0;
            foreach (SDG.Unturned.SteamPlayer plr in SDG.Unturned.Provider.Players)
            {
                UnturnedPlayer unturnedPlayer = UnturnedPlayer.FromSteamPlayer(plr);
                Nicks.Add(unturnedPlayer.CSteamID);
                f++;
            }

            //Přidání hráčů do listu při připojení na server
            U.Events.OnPlayerConnected += (UnturnedPlayer player) =>
                {
                    Nicks.Add(player.CSteamID);
                };
            //Odebrání hráčů z listu při odpojení ze serveru
            U.Events.OnPlayerDisconnected += (UnturnedPlayer player) =>
                {
                    Nicks.Remove(player.CSteamID);
                };
            U.Events.OnShutdown += () =>
            {
                shutdown = true;
                ShowCords();
            };
        }

        //Zjištění souřadnic
        private void callFunc(object sender, EventArgs e)
        {
            ShowCords();
        }

        private void ShowCords()
        {
            int count = Nicks.Count;
            data = string.Empty;
            url = string.Empty;
            encoded = string.Empty;

            //Pro každého hráče zjistí jméno, CSteamID a pozici
            for (int i = 1; i <= count; i++)
            {
                UnturnedPlayer player = UnturnedPlayer.FromCSteamID(Nicks[i - 1]);
                characterName = player.CharacterName.Replace(";", "&#59").Replace("[", "&#91").Replace("]", "&#93").Replace("=", "&#61");
                rotation = Convert.ToInt32(player.Rotation);
                if (player.IsAdmin == true) { playerStatus = "admin"; } else if (player.IsPro == true) { playerStatus = "pro"; } else { playerStatus = "player"; }
                if (player.Features.VanishMode == false) { data = data + "[Charactername=" + characterName + ";CSteamID=" + player.CSteamID + ";Position=" + player.Position + ";Rotation=" + rotation + ";PlayerStatus=" + playerStatus + "]"; };
            }

            //Odešle data na server
            if (data != string.Empty || firstrun == true)
            {
                url =  Configuration.Instance.WebCoreAddress + "/dynmap-core.php?user=server";
                postData = "map=" + SDG.Unturned.Provider.map + "&data=" + Uri.EscapeDataString(data) + "&privatekey=" + PrivateKey;
                if (shutdown == true) { postData = "map=" + SDG.Unturned.Provider.map + "&privatekey=" + PrivateKey; };
                var post = Encoding.ASCII.GetBytes(postData);

                HttpWebRequest request = (HttpWebRequest)WebRequest.Create(url);

                request.Method = "POST";
                request.ContentType = "application/x-www-form-urlencoded";
                request.ContentLength = post.Length;
                using (var stream = request.GetRequestStream())
                {
                    stream.Write(post, 0, post.Length);
                }

                HttpWebResponse response = (HttpWebResponse)request.GetResponse();
                using (Stream stream = response.GetResponseStream())
                {
                    StreamReader reader = new StreamReader(stream, Encoding.UTF8);
                    output = reader.ReadToEnd();
                }

                //Deaktivuje plugin, pokud se PrivateKey neshoduje
                if (output == "Error.PrivateKeyNotMatch")
                {
                    Logger.LogError("Priavte keys in dynmap-config.php and in Dynmap.configuration.xml doesn't match!");
                    Logger.LogError("Unloading plugin!");
                    myTimer.Enabled = false;
                }

                if (data != string.Empty) { firstrun = true; } else { firstrun = false; };
            }
        }
    }
}