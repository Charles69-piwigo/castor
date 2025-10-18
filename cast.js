// Vérification du chargement du script js
console.log("cast.js chargé !");

const htmlLang = document.documentElement.lang; // ex: "fr" ou "en"
console.log("langue = " ,htmlLang);

var session = null;
var btnCast = document.getElementById("castor-btn-cast"),
    iconPath = document.getElementById("castor-icon-cast");
var btnCastEtat = document.getElementById("castor-btn-cast-etat"),
    iconPathEtat = document.getElementById("castor-icon-cast-etat");    

var btn_video = document.getElementById('castor-remote');
//if (btn_video) { 
//  console.log('btn video ok');    
//btn_video.style.display = 'none' ;
//}

console.log('castState original',sessionStorage.getItem('castState'));

//---------------------------------------------------------------------------------
// surveillance du CHANGEMENT DE PAGE

document.addEventListener("DOMContentLoaded", function() { changePage() });

function changePage() {

  console.log('changement de page')
  console.log("page en cours  ", document.body.id);

  if ((document.body.id === 'theCategoryPage') && (sessionStorage.getItem('castState') === "1")) {
     console.log("theCategoryPage - castState= 1");
     const message = (htmlLang === "fr") ? "Arrêter la diffusion" : "Stop casting";
    btnCastEtat.setAttribute("title", message); // Modification du title
    btnCastEtat.style.display = "inline-block" ; // ré-affiche l'icone d'état dans la barre de menu
    iconPathEtat.style.fill = "#003cffff"; // Met l'icône d'état bleu
    //iconPathEtat.style.fill = "#00ff37ff"; // Met l'icône d'état bleu
    return ;
  }


  let storedUrl = sessionStorage.getItem("urlPrevMedia");

  if (sessionStorage.getItem('castState') === "1") {  
    console.log("Session active détectée, mise à jour de l'icône. castState=1");
    const message = (htmlLang === "fr") ? "Arrêter la diffusion" : "Stop casting";
    btnCast.setAttribute("title", message); // Modification du title
    iconPath.style.fill = "#007bff"; // Met l'icône en bleu
    btnCastEtat.style.display = "inline-block" ; // ré-affiche l'icone d'état dans la barre de menu
    iconPathEtat.style.fill = "#003cffff"; // Met l'icône d'état bleu
    //iconPathEtat.style.fill = "#00ff37ff"; // Met l'icône d'état bleu
  }

  //console.log(storedUrl,jsUrl) ;
  
  if ( storedUrl === window.jsURL )  {
      console.log("même image - castState =  ",sessionStorage.getItem('castState'));

      return ;

  } else {
      console.log("changement de page + castState= ", sessionStorage.getItem('castState'));
      console.log('nouvelle url = ', jsUrl);
      sessionStorage.setItem('urlPrevMedia', window.jsURL) ;
      
      if (sessionStorage.getItem('castState') === "1") {  // si on est en train de caster
        //console.log('avant d\'appeler loadMedia, session = ',session.Id);
        if (session) {  // si la session est définie  
          castOn();
          loadMedia(jsUrl);
        }else{
          console.log("Session non définie , impossible de caster l'image ")
        }
      } else {
        console.log("Cast non actif");
      }
  }    
}

// CAST  =========================================================

  // Fonction inverseur pour lancer ou arrêter le cast *** click sur le bouton de CAST de picture page

function toggleCast() {
  'use strict';
  console.log('toggle + castState= ',sessionStorage.getItem('castState'));

  let castEtat = sessionStorage.getItem('castState');
  if (castEtat === "1"  ) {
      // Terminer la session active
      if ((session) != null) {
        stopApp(); /// castOff est appelé si ça réussit 
      }   
  } else {
      // pas d'image sur la page 
      if (typeof jsUrl === "undefined" || typeof jsMime === "undefined" || !jsUrl || !jsMime) {
        console.log("toggle > Aucun média sur cette page");
        //showCastAlert(); // Affiche le message
        } else {
                   
          launchApp();    // castOn est appelé si ça réussit
        } 
    }
  }

// fonction pour arrêter le cast par le bouton de la barre d'outil
function stopCast() {
    btnCastEtat.style.display = "none" ; // supprime l'icone d'état dans la barre de menu
    stopApp(); /// castOff est appelé si ça réussit 

}




// ---------------------------------------------------------------------------------  
function castOn() {  /// si launch réussi
  console.log('castOn');
  sessionStorage.setItem('castState', '1');
  sessionStorage.setItem("urlPrevMedia", jsUrl); // on stocke l'url
  // Met à jour le texte du bouton

  const message = (htmlLang === "fr") ? "Arrêter la diffusion" : "Stop casting";
  btnCast.setAttribute("title", message); // Modification du title
  iconPath.style.fill = "#007bff"; // met en bleu l'icone de la barre de navigation
  //btnCastEtat.style.display = "inline-block" ; // ré-affiche l'icone d'état dans la barre de menu
  console.log('cast démarré + castState= ',sessionStorage.getItem('castState'));
}

// --------------------------------------------------------------------------------
function castOff() {   /// si stopApp réeussi
  console.log('castOff');
  //console.log('stop ', session.media.length);
  session = null ;
  sessionStorage.setItem('castState', '0');
  console.log('cast arrêté + castState= ',sessionStorage.getItem('castState'));
  //initializeCastApi()
  // Met à jour le texte du bouton
  const message = (htmlLang === "fr") ? "Démarrer la diffusion" : "Start casting";
  btnCast.setAttribute("title", message); // Modification du title
  iconPath.style.fill = "#fff"; // blanc
  btnCastEtat.style.display = "none"; // enlève l'icone d'état de la barre de menu
}

// ===============================================================

// Wait for the API to complete loading
window['__onGCastApiAvailable'] = function(loaded, errorInfo) {
  if (loaded) {
    initializeCastApi();
  } else {
    log(errorInfo);
  }
}

// ========================================================================

function initializeCastApi() {
  var applicationID = chrome.cast.media.DEFAULT_MEDIA_RECEIVER_APP_ID;
  var autoJoinPolicy = chrome.cast.AutoJoinPolicy.TAB_AND_ORIGIN_SCOPED;  // permet de conserver la session
  var sessionRequest = new chrome.cast.SessionRequest(applicationID);
  var apiConfig = new chrome.cast.ApiConfig(sessionRequest,
          sessionListener,
          receiverListener,
          autoJoinPolicy);
  chrome.cast.initialize(apiConfig, onInitSuccess, onInitError);
  let savedSessionId = sessionStorage.getItem('sessionId');
  console.log("Récupération de la session Chromecast :", savedSessionId);

}

// ============================================================
function sessionListener(e) {
  session = e;
  //console.log('Nouvelle session');
  //if (session.media.length != 0) {
  if (session !== undefined ) {
          //console.log('Nouvelle sessionTrouvé ' + session.media.length + ' session.');
          sessionStorage.setItem('sessionId', e.sessionId);
          console.log('init session ' + e.sessionId);
          // Vérifier si le cast était actif avant le changement de page
            if (sessionStorage.getItem('castState') === "1") {
              console.log("Session récupérée, envoi de la nouvelle image :", jsUrl);
              loadMedia(jsUrl);
            }
  } else  {
    console.log('init zéro session ' + e.sessionId);
  }
}

// ====================================================================
function loadMedia() {
  
if (!session) {
      console.log(">> Erreur - Pas de session définie.");
      return;
}
  

  btnCastEtat.style.display = "inline-block" ; // ré-affiche l'icone d'état dans la barre de menu
  const message = (htmlLang === "fr") ? "Arrêter la diffusion" : "Stop casting";
  btnCastEtat.setAttribute("title", message); // Modification du title
  iconPathEtat.style.fill = "#003cffff"; // met en bleu l'icone de la barre de menu
  

if (jsMime === 'video/mp4') {    //// Cas des vidéos ************************************** VIDEO
  console.log('jsMime = ' ,jsMime) ;
  
  var videoSrc = jsUrl ;
  var mediaInfo = new chrome.cast.media.MediaInfo(videoSrc);
  mediaInfo.contentType = jsMime; 

  var request = new chrome.cast.media.LoadRequest(mediaInfo);

  session.loadMedia(request, (media) => {
    console.log("✅ Vidéo envoyée avec succès.");
    session.media[0] = media; // Assigner correctement le média
    waitForMediaUpdate(); // Attendre que le média soit bien mis à jour & activation de la télécommande
  }, onLoadError);

} else {  // Cas des photos  ************************************************************* PHOTO
    
  btn_video.style.display = 'none'; // on n'affiche plus la télécommande

  var videoSrc = jsUrl ;
  var mediaInfo = new chrome.cast.media.MediaInfo(videoSrc);
  mediaInfo.contentType = jsMime; // 'image/jpeg';

  var request = new chrome.cast.media.LoadRequest(mediaInfo);
  //request.autoplay = true;

  session.loadMedia(request, onLoadSuccess, onLoadError);
}

}
// =================================================================

/// Fonction pour attendre que la vidéo soit envoyée avant d'activer la télécommande
function waitForMediaUpdate() {
  if (!session) return;
  
  console.log("⏳ Attente de la mise à jour du media...");

  // Attendre 1 seconde et récupérer les infos du média
  setTimeout(() => {
    if (session.media && session.media.length > 0 && session.media[0].media) {
        console.log("✅ Média mis à jour :", session.media[0]);
        btn_video.style.display = 'block';
        videoPlay() ;
        audioOn() ;
        setupControls(); // Active les boutons après confirmation
    } else {
        console.log("❌ Toujours aucun média détecté après 1s !");
    }
  }, 1000); // Attente de 1 seconde
}


// ================================================================
function receiverListener(e) {
  if( e === 'available' ) {
    console.log("Chromecast trouvé sur le réseau.");
  }
  else {
    console.log("Pas de Chromecast sur le réseau.");
  }
}

function onInitSuccess() {
  console.log("Initialisation API succès");
}

function onInitError() {
  console.log("Initialisation API échouée");
}

function launchApp() {
  console.log("Sélection du Chromecast > lauchApp ");
  chrome.cast.requestSession(onRequestSessionSuccess, onLaunchError);
}

function onLaunchError() {
  console.log("Erreur connexion au Chromecast.");
  sessionStorage.setItem("castState", "0");
}

function onRequestSessionSuccess(e) {
  console.log("ReqSSucces session créée:  " + e.sessionId);
  castOn();  // mise à jour bouton et variables
  session = e;
  sessionStorage.setItem('sessionId', e.sessionId); // Enregistrement ici
  console.log("Session Chromecast enregistrée :", e.sessionId);
  loadMedia();
}

function onLoadSuccess() {
  console.log('Image envoyée avec succès.');
}

function onLoadError() {
  console.log('Image/Vidéo echec de l\'envoi.');
}

function stopApp() {
  session.stop(onStopAppSuccess, onStopAppError);
  
}

function onStopAppSuccess() {
  console.log('Successfully stopped app.');
  castOff() ;
  
}

function onStopAppError() {
  console.log('Error stopping app.');
}
    
//***************************************************************************** */    

function setupControls() {
  document.getElementById("castor-video-btn-play").addEventListener("click", togglePlayPause);
  document.getElementById("castor-video-btn-pause").addEventListener("click", togglePlayPause);
  document.getElementById("castor-video-bar").addEventListener("input", seekVideo);
  document.getElementById("castor-audio-btn-on").addEventListener("click", toggleMute);
  document.getElementById("castor-audio-btn-off").addEventListener("click", toggleMute);
  document.getElementById("castor-audio-bar").addEventListener("input", setVolume);
}

// 1️⃣ Play/Pause
function togglePlayPause() {

console.log("toggle");

  if (!session || !session.media[0]) return;
  let media = session.media[0];

  if (media.playerState === chrome.cast.media.PlayerState.PLAYING) {
      media.pause(null, console.log("Pause"), console.error);
      document.getElementById('castor-video-btn-play').style.display = 'block';
      document.getElementById('castor-video-btn-pause').style.display = 'none';

  } else {
      media.play(null, console.log("Play"), console.error);
      document.getElementById('castor-video-btn-play').style.display = 'none';
      document.getElementById('castor-video-btn-pause').style.display = 'block';

  }
}

// 2️⃣ Changer la position
function seekVideo(event) {
  if (!session || !session.media[0]) return;
  let media = session.media[0];
  let newTime = event.target.value * media.media.duration / 100;

  let request = new chrome.cast.media.SeekRequest();
  request.currentTime = newTime;
  media.seek(request, console.log("Seek"), console.error);
}

// 3️⃣ Mute/Unmute
function toggleMute() {
  if (!session || !session.media[0]) return;
  let media = session.media[0];

  let volumeRequest = new chrome.cast.media.VolumeRequest();
  volumeRequest.volume = new chrome.cast.Volume();
  console.log(volumeRequest);
  if (media.volume.muted) {
    //volumeRequest.volume.muted = false;
    volumeRequest.volume.muted = !media.volume.muted;
    media.setVolume(volumeRequest, console.log("Unmute"), console.error);
    document.getElementById('castor-audio-btn-on').style.display = 'none';
    document.getElementById('castor-audio-btn-off').style.display = 'block';
  } else {
    //volumeRequest.volume.muted = true;
    volumeRequest.volume.muted = !media.volume.muted;
    media.setVolume(volumeRequest, console.log("Mute"), console.error);
    document.getElementById('castor-audio-btn-on').style.display = 'block';
    document.getElementById('castor-audio-btn-off').style.display = 'none';
  }

}

// 4️⃣ Changer le volume
function setVolume(event) {
  if (!session || !session.media[0]) return;
  let media = session.media[0];

  let volumeRequest = new chrome.cast.media.VolumeRequest();
  volumeRequest.volume = new chrome.cast.Volume();
  volumeRequest.volume.level = parseFloat(event.target.value);

  media.setVolume(volumeRequest, console.log("Volume changé"), console.error);
}

// -----------------------------------------------------------------

// Play initialisation
function videoPlay() {
  if (!session || !session.media[0]) return;
  let media = session.media[0];

      media.play(null, console.log("init Play"), console.error);
      document.getElementById('castor-video-btn-play').style.display = 'none';
      document.getElementById('castor-video-btn-pause').style.display = 'block';
}

// audioOn initialisation
function audioOn() {
  if (!session || !session.media[0]) return;
  let media = session.media[0];

  let volumeRequest = new chrome.cast.media.VolumeRequest();
  volumeRequest.volume = new chrome.cast.Volume();
  console.log(volumeRequest);

    volumeRequest.volume.muted = false;
    media.setVolume(volumeRequest, console.log("init Audio On"), console.error);
    document.getElementById('castor-audio-btn-on').style.display = 'none';
    document.getElementById('castor-audio-btn-off').style.display = 'block';

}

//****************************************************
/// affiche un message quand on n'est pas sur une photo/vidéo, on ne peut pas lancer le cast
/*
function showCastAlert() {
  var alertBox = document.getElementById("castor-alert");
  if (!alertBox) return;

    alertBox.style.display = "block";
    
    setTimeout(function() {
    alertBox.style.display = "none";
      }, 5000); // Cache le message après 5 secondes
}
*/      
