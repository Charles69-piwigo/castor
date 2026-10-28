// Vérification du chargement du script js
console.log("cast.js chargé ");

const htmlLang = document.documentElement.lang; // ex: "fr" ou "en"
//*console.log("langue = " ,htmlLang);

var session = null;
var btnCast = document.getElementById("castor-btn-cast"),
    iconPath = document.getElementById("castor-icon-cast");
var btnCastEtat = document.getElementById("castor-btn-cast-etat"),
    iconPathEtat = document.getElementById("castor-icon-cast-etat");

var currentMedia = null;       // média vidéo en cours sur le récepteur
var controlsBound = false;     // écouteurs de la télécommande posés une seule fois
var apiInitialized = false;
var isMuted = false;           // état du son sur la TV
var seeking = false;           // l'utilisateur déplace la barre de position
var progressTimer = null;

// message selon la langue
function tr(fr, en) { return (htmlLang === "fr") ? fr : en; }

// la page contient-elle une photo ou une vidéo à caster ?
function hasMedia() {
  return typeof jsUrl !== "undefined" && typeof jsMime !== "undefined" && !!jsUrl && !!jsMime;
}

function isVideo() {
  return hasMedia() && jsMime.indexOf('video/') === 0;
}

// message utilisateur (réutilise le bandeau castor-alert)
var alertTimer = null;
function showCastMessage(msg) {
  console.warn("castor : " + msg);
  var box = document.getElementById("castor-alert");
  if (!box) {
    box = document.createElement("div");
    box.id = "castor-alert";
    box.className = "castor-alert";
    document.body.appendChild(box);
  }
  box.textContent = msg;
  box.style.display = "block";
  clearTimeout(alertTimer);
  alertTimer = setTimeout(function() { box.style.display = "none"; }, 5000);
}

//*console.log('castState original',sessionStorage.getItem('castState'));

//---------------------------------------------------------------------------------
// surveillance du CHANGEMENT DE PAGE

document.addEventListener("DOMContentLoaded", function() { changePage() });

function changePage() {

  //*console.log('changement de page')
  //*console.log("page en cours  ", document.body.id);

  // Récupération dynamique des éléments (au cas où ils n'étaient pas disponibles au chargement)
  var btnEtat = btnCastEtat || document.getElementById("castor-btn-cast-etat");
  var iconEtat = iconPathEtat || document.getElementById("castor-icon-cast-etat");

  if ((document.body.id === 'theCategoryPage') && (sessionStorage.getItem('castState') === "1")) {
     //*console.log("theCategoryPage - castState= 1");
     const message = tr("Arrêter la diffusion", "Stop casting");
    if (btnEtat) {
      btnEtat.setAttribute("title", message); // Modification du title
      btnEtat.style.display = "inline-block" ; // ré-affiche l'icone d'état dans la barre de menu
    }
    if (iconEtat) {
      iconEtat.style.fill = "#003cffff"; // Met l'icône d'état bleu
    }
    return ;
  }


  if (sessionStorage.getItem('castState') === "1") {
    console.log("Session active détectée, mise à jour de l'icône. castState=1");
    const message = tr("Arrêter la diffusion", "Stop casting");
    // récupération dynamique : le bouton de la picture page peut être injecté après le chargement du script
    var btnPicture = btnCast || document.getElementById("castor-btn-cast");
    var iconPicture = iconPath || document.getElementById("castor-icon-cast");
    if (btnPicture) {
      btnPicture.setAttribute("title", message); // Modification du title
    }
    if (iconPicture) {
      iconPicture.style.fill = "#007bff"; // Met l'icône en bleu
    }
    if (btnEtat) {
      btnEtat.style.display = "inline-block" ; // ré-affiche l'icone d'état dans la barre de menu
    }
    if (iconEtat) {
      iconEtat.style.fill = "#003cffff"; // Met l'icône d'état bleu
    }
  }

  if (!hasMedia()) { return; }  // page sans photo ni vidéo

  let storedUrl = sessionStorage.getItem("urlPrevMedia");

  if ( storedUrl === window.jsUrl )  {
      //*console.log("même image - castState =  ",sessionStorage.getItem('castState'));

      return ;

  } else {
      //*console.log("changement de page + castState= ", sessionStorage.getItem('castState'));
      sessionStorage.setItem('urlPrevMedia', window.jsUrl) ;

      if (sessionStorage.getItem('castState') === "1") {  // si on est en train de caster
        if (session) {  // si la session est définie
          castOn();
          loadMedia();
        }else{
          //*console.log("Session non définie , impossible de caster l'image ")
        }
      } else {
        //*console.log("Cast non actif");
      }
  }
}

// CAST  =========================================================

  // Fonction inverseur pour lancer ou arrêter le cast *** click sur le bouton de CAST de picture page

function toggleCast(event) {
  'use strict';
  if (event && event.preventDefault) { event.preventDefault(); }  // href="#"

  let castEtat = sessionStorage.getItem('castState');
  if (castEtat === "1"  ) {
      // Terminer la session active
      stopApp(); /// castOff est appelé si ça réussit
  } else {
      if (!hasMedia()) {
        showCastMessage(tr("Ouvrir une photo ou une vidéo avant de caster.", "Open a photo or a video before casting."));
      } else {
        launchApp();    // castOn est appelé si ça réussit
      }
  }
}

// fonction pour arrêter le cast par le bouton de la barre d'outil
function stopCast() {
    var btnEtat = btnCastEtat || document.getElementById("castor-btn-cast-etat");
    if (btnEtat) {
      btnEtat.style.display = "none" ; // supprime l'icone d'état dans la barre de menu
    }
    stopApp(); /// castOff est appelé si ça réussit
}




// ---------------------------------------------------------------------------------
function castOn() {  /// si launch réussi
  //*console.log('castOn');
  sessionStorage.setItem('castState', '1');
  sessionStorage.setItem("urlPrevMedia", jsUrl); // on stocke l'url

  // Met à jour l'icône de la barre de navigation (picture page)
  const message = tr("Arrêter la diffusion", "Stop casting");
  var btnPicture = btnCast || document.getElementById("castor-btn-cast");
  var iconPicture = iconPath || document.getElementById("castor-icon-cast");
  if (btnPicture) {
    btnPicture.setAttribute("title", message);
  }
  if (iconPicture) {
    iconPicture.style.fill = "#007bff"; // met en bleu l'icone de la barre de navigation
  }

  // Affiche l'icône d'état dans la barre de menu en bleu
  var btnEtat = btnCastEtat || document.getElementById("castor-btn-cast-etat");
  var iconEtat = iconPathEtat || document.getElementById("castor-icon-cast-etat");
  if (btnEtat) {
    btnEtat.style.display = "inline-block";
    btnEtat.setAttribute("title", message);
  }
  if (iconEtat) {
    iconEtat.style.fill = "#003cffff"; // bleu
  }
  //*console.log('cast démarré + castState= ',sessionStorage.getItem('castState'));
}

// --------------------------------------------------------------------------------
function castOff() {   /// si stopApp réeussi
  //*console.log('castOff');
  session = null ;
  currentMedia = null ;
  clearInterval(progressTimer);
  var remote = document.getElementById('castor-remote');
  if (remote) { remote.style.display = 'none'; }
  sessionStorage.setItem('castState', '0');
  //*console.log('cast arrêté + castState= ',sessionStorage.getItem('castState'));
  // Met à jour le texte du bouton
  const message = tr("Démarrer la diffusion", "Start casting");
  var btnPicture = btnCast || document.getElementById("castor-btn-cast");
  var iconPicture = iconPath || document.getElementById("castor-icon-cast");
  if (btnPicture) {
    btnPicture.setAttribute("title", message); // Modification du title
  }
  if (iconPicture) {
    iconPicture.style.fill = "#fff"; // blanc
  }
  // Cacher l'icone d'état de la barre de menu (récupération dynamique au cas où)
  var btnEtat = btnCastEtat || document.getElementById("castor-btn-cast-etat");
  if (btnEtat) {
    btnEtat.style.display = "none";
  }
}

// ===============================================================

// Le callback __onGCastApiAvailable est défini dans la page avant cast_sender.js (main.inc.php) :
// il appelle castorApiReady. Si l'API était déjà prête avant ce script, on rattrape ici.
function castorApiReady(loaded, errorInfo) {
  if (loaded) {
    initializeCastApi();
  } else {
    console.warn("castor : API Cast non chargée", errorInfo);
  }
}
if (window.castApiLoaded !== undefined) { castorApiReady(window.castApiLoaded, window.castApiError); }

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
}

// ============================================================
function sessionListener(e) {
  session = e;
  if (session !== undefined ) {
          sessionStorage.setItem('sessionId', e.sessionId);
          // session fermée côté TV (télécommande, autre appareil) : on remet l'état à zéro
          watchSession(e);
          // Vérifier si le cast était actif avant le changement de page
          if (sessionStorage.getItem('castState') === "1" && hasMedia()) {
            loadMedia();
          }
  }
}

// ====================================================================
function loadMedia() {

  if (!session || !hasMedia()) {
    return;
  }

  // Récupération dynamique des éléments
  var btnEtat = btnCastEtat || document.getElementById("castor-btn-cast-etat");
  var iconEtat = iconPathEtat || document.getElementById("castor-icon-cast-etat");

  if (btnEtat) {
    btnEtat.style.display = "inline-block" ; // ré-affiche l'icone d'état dans la barre de menu
    btnEtat.setAttribute("title", tr("Arrêter la diffusion", "Stop casting"));
  }
  if (iconEtat) {
    iconEtat.style.fill = "#003cffff"; // met en bleu l'icone de la barre de menu
  }

  // Le récepteur par défaut affiche "Default Media Receiver" tant qu'il n'a ni type de flux ni titre
  var mediaInfo = new chrome.cast.media.MediaInfo(jsUrl, jsMime);
  mediaInfo.streamType = chrome.cast.media.StreamType.BUFFERED;
  mediaInfo.metadata = new chrome.cast.media.GenericMediaMetadata();
  mediaInfo.metadata.title = (typeof jsTitle === "string") ? jsTitle : "";

  var request = new chrome.cast.media.LoadRequest(mediaInfo);
  request.autoplay = true;
  request.currentTime = 0;

  if (isVideo()) {    //// Cas des vidéos ************************************** VIDEO
    console.log("castor : envoi de la vidéo", jsUrl);
    session.loadMedia(request, onVideoLoaded, onLoadError);
  } else {  // Cas des photos  ************************************************************* PHOTO
    currentMedia = null;
    var remote = document.getElementById('castor-remote');
    if (remote) { remote.style.display = 'none'; } // on n'affiche plus la télécommande
    session.loadMedia(request, onLoadSuccess, onLoadError);
  }
}
// =================================================================

// Surveille la session : fermeture côté TV, et média créé (filet de sécurité si le retour de loadMedia n'arrive pas)
function watchSession(e) {
  e.addUpdateListener(function(isAlive) { if (!isAlive) { castOff(); } });
  e.addMediaListener(function(media) {
    console.log("castor : média détecté sur la session", media.playerState);
    if (isVideo()) { onVideoLoaded(media); }
  });
}

// Vidéo acceptée par le récepteur : activation de la télécommande
function onVideoLoaded(media) {
  // appelée par le retour de loadMedia ET par addMediaListener : on n'active la télécommande qu'une fois
  if (currentMedia && currentMedia.mediaSessionId === media.mediaSessionId) { return; }
  currentMedia = media;
  isMuted = !!(media.volume && media.volume.muted);
  console.log("castor : vidéo acceptée par le récepteur", jsUrl, media.playerState, media.idleReason);
  var lastState = null;
  media.addUpdateListener(function(isAlive) {
    var state = media.playerState + "/" + media.idleReason;
    if (state !== lastState) {   // trace des changements d'état pour le diagnostic
      lastState = state;
      console.log("castor : état du récepteur =", state, "vivant=", isAlive);
      if (media.playerState === chrome.cast.media.PlayerState.IDLE
          && media.idleReason === chrome.cast.media.IdleReason.ERROR) {
        showCastMessage(tr("Le Chromecast n'a pas pu lire cette vidéo (erreur du récepteur).",
                           "The Chromecast could not play this video (receiver error)."));
      }
    }
    syncRemote();
  });
  clearInterval(progressTimer);
  progressTimer = setInterval(syncProgress, 1000);
  var remote = document.getElementById('castor-remote');
  if (remote) { remote.style.display = 'block'; }
  setupControls();
  syncRemote();
}

function getMedia() {
  if (currentMedia) return currentMedia;
  return (session && session.media && session.media[0]) ? session.media[0] : null;
}

// Aligne les boutons de la télécommande sur l'état réel du récepteur
function syncRemote() {
  var media = getMedia();
  if (!media) return;
  var show = function(id, on) {
    var el = document.getElementById(id);
    if (el) { el.style.display = on ? 'block' : 'none'; }
  };
  var playing = (media.playerState === chrome.cast.media.PlayerState.PLAYING
              || media.playerState === chrome.cast.media.PlayerState.BUFFERING);
  show('castor-video-btn-play', !playing);
  show('castor-video-btn-pause', playing);
  // l'état muet suit le récepteur quand il le communique (le CSS impose le display des boutons son : on passe par une classe)
  if (media.volume && typeof media.volume.muted === "boolean") { isMuted = media.volume.muted; }
  var remote = document.getElementById('castor-remote');
  if (remote) { remote.classList.toggle('castor-muted', isMuted); }
  syncProgress();
}

// Position de lecture sur la barre (sauf pendant que l'utilisateur la déplace)
function syncProgress() {
  var media = getMedia();
  var bar = document.getElementById('castor-video-bar');
  if (!media || !bar || seeking) return;
  var duration = media.media && media.media.duration;
  if (!duration) return;
  bar.value = Math.min(100, media.getEstimatedTime() * 100 / duration);
}


// ================================================================
function receiverListener(e) {
  if( e === 'available' ) {
    //*console.log("Chromecast trouvé sur le réseau.");
  }
  else {
    //*console.log("Pas de Chromecast sur le réseau.");
  }
}

function onInitSuccess() {
  apiInitialized = true;
}

function onInitError(err) {
  apiInitialized = false;
  console.warn("castor : initialisation API échouée", err);
}

function launchApp() {
  if (typeof chrome === "undefined" || !chrome.cast || !apiInitialized) {
    showCastMessage(tr("Cast indisponible : utilisez Google Chrome sur le même réseau que la TV.",
                       "Cast unavailable: use Google Chrome on the same network as the TV."));
    return;
  }
  chrome.cast.requestSession(onRequestSessionSuccess, onLaunchError);
}

function onLaunchError(err) {
  sessionStorage.setItem("castState", "0");
  var code = err && err.code;
  if (code === "cancel") { return; }  // l'utilisateur a fermé la fenêtre de choix
  if (code === "receiver_unavailable") {
    showCastMessage(tr("Aucun appareil Cast trouvé sur le réseau.", "No Cast device found on the network."));
  } else {
    showCastMessage(tr("Erreur de connexion au Chromecast", "Chromecast connection error") + (code ? " (" + code + ")" : ""));
  }
}

function onRequestSessionSuccess(e) {
  castOn();  // mise à jour bouton et variables
  session = e;
  sessionStorage.setItem('sessionId', e.sessionId); // Enregistrement ici
  watchSession(e);
  loadMedia();
}

function onLoadSuccess() {
  //*console.log('Image envoyée avec succès.');
}

function onLoadError(err) {
  var code = err && err.code;
  var detail = (err && err.description) ? err.description : "";
  showCastMessage(tr("Le Chromecast n'a pas pu lire ce média", "The Chromecast could not play this media")
                  + (code ? " (" + code + (detail ? " : " + detail : "") + ")" : ""));
}

function stopApp() {
  if (!session) {  // session perdue (changement de page...) : on remet simplement l'état à zéro
    castOff();
    return;
  }
  session.stop(onStopAppSuccess, onStopAppError);
}

function onStopAppSuccess() {
  castOff() ;
}

function onStopAppError() {
  castOff();  // la session n'existe plus côté TV : on remet l'état à zéro
}

//***************************************************************************** */

function setupControls() {
  if (controlsBound) return;   // éviter d'empiler les écouteurs à chaque vidéo
  var bind = function(id, evt, fn) {
    var el = document.getElementById(id);
    if (el) { el.addEventListener(evt, fn); }
  };
  bind("castor-video-btn-play", "click", togglePlayPause);
  bind("castor-video-btn-pause", "click", togglePlayPause);
  bind("castor-video-bar", "input", function() { seeking = true; });  // pas de mise à jour auto pendant le déplacement
  bind("castor-video-bar", "change", seekVideo);                        // un seul saut, au relâchement
  bind("castor-audio-btn-on", "click", toggleMute);
  bind("castor-audio-btn-off", "click", toggleMute);
  bind("castor-audio-bar", "input", setVolume);
  controlsBound = true;
}

// 1️⃣ Play/Pause
function togglePlayPause() {
  let media = getMedia();
  if (!media) return;

  if (media.playerState === chrome.cast.media.PlayerState.PLAYING) {
      media.pause(null, syncRemote, onLoadError);
  } else {
      media.play(null, syncRemote, onLoadError);
  }
}

// 2️⃣ Changer la position
function seekVideo(event) {
  let media = getMedia();
  if (!media) { seeking = false; return; }
  let duration = media.media && media.media.duration;
  if (!duration) {
    seeking = false;
    showCastMessage(tr("Durée de la vidéo inconnue : déplacement impossible.", "Video duration unknown: cannot seek."));
    return;
  }
  let request = new chrome.cast.media.SeekRequest();
  request.currentTime = event.target.value * duration / 100;
  media.seek(request, function() { seeking = false; syncRemote(); }, function(err) { seeking = false; onLoadError(err); });
}

// 3️⃣ Mute/Unmute
function toggleMute() {
  let media = getMedia();
  if (!media) return;

  let volumeRequest = new chrome.cast.media.VolumeRequest();
  volumeRequest.volume = new chrome.cast.Volume();
  var wantMuted = !isMuted;
  volumeRequest.volume.muted = wantMuted;
  media.setVolume(volumeRequest, function() { isMuted = wantMuted; syncRemote(); }, onLoadError);
}

// 4️⃣ Changer le volume
function setVolume(event) {
  let media = getMedia();
  if (!media) return;

  let volumeRequest = new chrome.cast.media.VolumeRequest();
  volumeRequest.volume = new chrome.cast.Volume();
  volumeRequest.volume.level = parseFloat(event.target.value);

  media.setVolume(volumeRequest);
}
