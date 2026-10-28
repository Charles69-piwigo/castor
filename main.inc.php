<?php
/*
Plugin Name: castor
Version: auto
Description: Castor - diffuser vos photos et vidéos sur une télé android ou via un chromecast
Plugin URI: https://piwigo.org/ext/extension_view.php?eid=1001
Author: Charles69
Author URI:
*/


 // ============  VERSIONS =========================================================
 // historique des versions
 /*
version 3.7.1 - 28/10/2026
  vidéos : streamType, métadonnées (titre), autoplay et message d'erreur à l'envoi
  vidéos : détection de toute la famille video/* (webm, mov, m4v...), plus seulement video/mp4
  télécommande : son coupé/rétabli (icônes pilotées par classe CSS), saut dans la vidéo au relâchement de la barre, barre de position qui avance
  cast.js et cast.css chargés avec ?v=<date du fichier> (cache navigateur périmé)
  vidéos : URL rendue absolue (pdp renvoie une URL relative, illisible par la TV)
  bouton cast : onclick au lieu de javascript:, callback API défini avant cast_sender.js
  messages utilisateur : aucun appareil trouvé, erreur d'initialisation, format non lu
  corrections : jsURL/jsUrl, gardes null (page sans média, session absente),
  écouteurs de la télécommande vidéo cumulés à chaque vidéo

version 3.7 - 23/06/2026
  conformation au standard get_original_url    
 
version 3.6 - 18/01/2026
  refactoring CSS et templates pour compatibilité multi-thèmes
  ajout theme_config.php pour gestion centralisée des thèmes
  ajout fallback JavaScript pour thèmes sans PLUGIN_PICTURE_ACTIONS
  suppression de la dépendance aux classes Bootstrap

version 3.5 - 19/12/2025
  déplacement de la télécommande vidéo ( suite modif VideoJS )
  commentaires sur console.log
  plugin URI modifié pour mise à jour automatique

version 3.4 - 18/10/2025
  une icone de lancement - arrêt du cast sur la ligne de navigation de Picture Page
  une icone d'état et d'arrêt du cast sur la barre de menu de Category Page
  ajouté traduction anglais

version 3.3 - 16/10/2025
  déplacement du bouton de cast de la barre de menu à la barre de navigation

version 3.2 - 12/10/2025
  remplacement des fonctions imagemagick par les fonctions natives piwigo derivatives

version 3.1 - 25/03/2025 (diffusé)
  changement numérotation des versions - nettoyage du code
  ajouté le pilotage des vidéos Play on/off - avancement - Son on/off - Volume
  css modifié prise en compte du thème bootstrap-darkroom & modus

 version 2.0.3 - 12/03/2025
  modifié imagemagick - correction calcul du redimensionnement avec respect du ratio
  conservation des miniatures -xl  1224x918 pixels

version 2.0.2 - 10/03/2025 (diffusé)
	supprimé création rep dans _data/i/galleries

version 2.0.1 - 07/03/2025
	modifié le calcul du mime type
	modifié le calcul des dimensions réduites , problème reSize aléatoire

version 2.0.0 - 02/03/2025
	gain de vitesse substantiel
	envoi d'une version réduite de la photo au lieu de la photo originale

 */

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

if (basename(dirname(__FILE__)) != 'castor')
{
  add_event_handler('init', 'castor_error');
  function castor_error()
  {
    global $page;
    $page['errors'][] = 'le nom du répertoire castor est incorrect, désactiver le plugin et le renommer  "castor"';
  }
  return;
}

// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+

define('CAST_DIR',    basename(dirname(__FILE__)));
define('CAST_PATH' , PHPWG_PLUGINS_PATH . CAST_DIR . '/');

// Include theme configuration
include_once(CAST_PATH . 'theme_config.php');



// SCRIPT =======================================================================================
// ajoute les scripts Google js Cast.js et css + fallback JS pour thèmes incompatibles

add_event_handler('loc_begin_page_tail', 'cast_add_api');
function cast_add_api()
{
    global $page, $template;

    // Get current theme safely
    $current_theme = castor_get_current_theme();

    // Get theme configuration
    $theme_config = castor_get_theme_config($current_theme);
    $needs_fallback = castor_needs_js_fallback($current_theme);
    $toolbar_selector = castor_get_toolbar_selector($current_theme);

    // numéro de version = date du fichier : force les navigateurs à recharger cast.js / cast.css modifiés
    $cast_ver_js  = @filemtime(CAST_PATH . 'cast.js');
    $cast_ver_css = @filemtime(CAST_PATH . 'css/cast.css');

    // Base scripts and CSS
    // le callback doit exister avant le chargement de cast_sender.js (cast.js le relaie ensuite)
    $scripts = '
    <script>
    window.castApiLoaded = undefined;
    window["__onGCastApiAvailable"] = function(loaded, errorInfo) {
        window.castApiLoaded = loaded;
        window.castApiError = errorInfo;
        if (typeof castorApiReady === "function") { castorApiReady(loaded, errorInfo); }
    };
    </script>
    <script src="https://www.gstatic.com/cv/js/sender/v1/cast_sender.js"></script>
    <script src="plugins/castor/cast.js?v=' . $cast_ver_js . '"></script>
    <link rel="stylesheet" href="plugins/castor/css/cast.css?v=' . $cast_ver_css . '" />
    ';

    // Add JavaScript fallback for themes that don't support PLUGIN_PICTURE_ACTIONS
    if ($needs_fallback) {
        $scripts .= '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Check if Cast button was injected via template
        if (!document.getElementById("castor-btn-cast")) {
            // Try to find toolbar using theme-specific selectors
            var selectors = "' . addslashes($toolbar_selector) . '";
            var selectorList = selectors.split(",").map(function(s) { return s.trim(); });
            var toolbar = null;

            for (var i = 0; i < selectorList.length; i++) {
                toolbar = document.querySelector(selectorList[i]);
                if (toolbar) break;
            }

            if (toolbar) {
                var castButton = document.createElement("a");
                castButton.id = "castor-btn-cast";
                castButton.href = "#";
                castButton.addEventListener("click", function(e) { toggleCast(e); });
                castButton.className = "castor-btn pwg-state-default pwg-button";
                castButton.title = "Start Casting";
                castButton.rel = "nofollow";
                castButton.innerHTML = \'<svg class="castor-icon" width="17" height="17" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g fill="none" fill-rule="evenodd"><path id="castor-icon-cast" d="M1 18v3h3c0-1.66-1.34-3-3-3Zm0-4v2c2.76 0 5 2.24 5 5h2c0-3.87-3.13-7-7-7Zm0-4v2a9 9 0 0 1 9 9h2c0-6.08-4.93-11-11-11Zm20-7H3c-1.1 0-2 .9-2 2v3h2V5h18v14h-7v2h7c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2Z"/></g></svg>\';

                // Try to insert in appropriate position
                var firstChild = toolbar.firstChild;
                if (firstChild) {
                    toolbar.insertBefore(castButton, firstChild);
                } else {
                    toolbar.appendChild(castButton);
                }

                // Also add alert div if not present
                if (!document.getElementById("castor-alert")) {
                    var alertDiv = document.createElement("div");
                    alertDiv.id = "castor-alert";
                    alertDiv.className = "castor-alert";
                    alertDiv.style.display = "none";
                    alertDiv.textContent = "Ouvrir une photo ou une vidéo avant de caster.";
                    document.body.appendChild(alertDiv);
                }
            }
        }
    });
    </script>
    ';
    }

    // Add theme class to body for CSS targeting
    if (!empty($current_theme)) {
        $scripts .= '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        document.body.classList.add("theme-' . addslashes($current_theme) . '");
    });
    </script>
    ';
    }

    $template->append('footer_elements', $scripts);
}

// =========================================================================================
// MENU PRINCIPAL - Ajout du bouton d'état et d'arrêt de cast dans la barre de menu

add_event_handler('blockmanager_register_blocks', 'register_cast_menubar_blocks');

function register_cast_menubar_blocks( $menu_ref_arr )
{
  $menu = & $menu_ref_arr[0];
  if ($menu->get_id() != 'menubar')
  return;
  $menu->register_block( new RegisteredBlock( 'mbCast', 'Cast', 'CAST'));
}

add_event_handler('blockmanager_apply', 'cast_apply');

function cast_apply($menu_ref_arr)
{
  global $template;

  $menu = & $menu_ref_arr[0];

  if (($block = $menu->get_block( 'mbCast' )) != null) {
    $template->set_template_dir(CAST_PATH.'template/');
    // Use universal template for all themes
    $block->template = 'menubar_cast_etat.tpl';
  }
}
//-----------------------------------------------------------------------




// Ajout du bouton cast dans la barre d'actions de l'image
add_event_handler('loc_end_picture', 'cast_picture_toolbar');

function cast_picture_toolbar()
{
    global $template;

    // Get current theme safely
    $current_theme = castor_get_current_theme();

    // Get theme configuration to check if we should use template injection
    $theme_config = castor_get_theme_config($current_theme);

    // Only inject via template if theme supports PLUGIN_PICTURE_ACTIONS
    $uses_plugin_actions = isset($theme_config['uses_plugin_actions']) ? $theme_config['uses_plugin_actions'] : true;
    if ($uses_plugin_actions) {
        $template->set_template_dir(CAST_PATH.'template/');

        // Use universal template for all themes
        $tpl_file = 'picture_cast.tpl';

        // Assigner le template au bloc d'actions
        $template->set_filename('cast_button', $tpl_file);
        $template->concat('PLUGIN_PICTURE_ACTIONS', $template->parse('cast_button', true));
    }
    // If theme doesn't support PLUGIN_PICTURE_ACTIONS, the JS fallback will handle injection
}

//===================================================================================
//   IDENTIFICATION PHOTO/VIDEO - Récupération de l'URL et du MIME
/// on est dans une page qui contient une photo  ou une video * loc_end_picture *

//==================================================================================
// Récupération de l'URL et du MIME TYPE
add_event_handler('loc_end_picture','calc_url') ;

function calc_url() {

  //echo('picture');

  global $template, $picture;

    if (!isset($picture['current']['path'])) {
        return;
    }

    // Récupérer l'URL de base  ==============
    $url_base = get_absolute_root_url();            // renvoie https://robert .. /photo.charles/
    //echo $url_base ;
    $url_picture = $picture['current']['path'];     // débute par ./
    $url_picture2 = substr($url_picture,2);         // enlève ./ au début
    $url_original = $url_base . $url_picture2;      // url https de la photo originale
    //echo '  url orig= ' , $url_original ;

    // Récupération du Mime Type ==============
    $mime_type = castor_get_mime($url_picture);
    $is_video = (strpos($mime_type, 'video/') === 0);

    // Titre affiché sur la TV (JSON : sûr dans un bloc <script>)
    $title = !empty($picture['current']['name']) ? $picture['current']['name'] : $picture['current']['file'];
    $template->assign('MEDIA_TITLE', json_encode((string)$title, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));

    if (!$is_video) {    // ce n'est pas une vidéo

      $url_local = make_derivative_xl($url_original);
      $url_local = $url_base . $url_local ;
            // Assigner à Smarty
            $template->assign('IMG_URL', $url_local); // Assignation dans le template
            $template->assign('MIME_TYPE', $mime_type);  // assignation dans le template


    } else {
            // CAS DES VIDEOS - on passe la photo de pwg_representative et le plugin JS VIDEO fait le job !!
            // Compatibilité pdp : laisser le plugin de protection réécrire l'URL si actif
            // (redirige vers serve_original.php?id=X qui vérifie les droits avant de servir)
            include_once(PHPWG_ROOT_PATH . 'include/derivative.inc.php');
            $src_image = new SrcImage($picture['current']);
            $url_original = trigger_change('get_original_url', $url_original, $src_image);
            // un plugin peut renvoyer une URL relative : la TV a besoin d'une URL absolue
            $url_original = castor_absolute_url($url_original);
            // Assigner à Smarty
            $template->assign('IMG_URL', $url_original); // Assignation dans le template
            $template->assign('MIME_TYPE', $mime_type);  // assignation dans le template
    }
  }

// Rend une URL absolue (http(s)://...) à partir d'une URL relative à la racine de Piwigo
function castor_absolute_url($url)
{
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    $root = get_absolute_root_url();                       // https://hote/chemin/ (avec / final)
    if (strpos($url, '//') === 0) {                         // //hote/chemin
        return parse_url($root, PHP_URL_SCHEME) . ':' . $url;
    }
    if (strpos($url, '/') === 0) {                          // /chemin depuis la racine du site
        $p = parse_url($root);
        return $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . $url;
    }
    return $root . preg_replace('#^\./#', '', $url);
}

// Mime type du fichier ; les conteneurs QuickTime / M4V sont envoyés comme video/mp4 (lus par Chromecast)
function castor_get_mime($path)
{
    $mime = false;
    if (function_exists('mime_content_type') && file_exists($path)) {
        $mime = mime_content_type($path);
    }
    if (!$mime || $mime === 'application/octet-stream') {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = array('mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/mp4',
                     'webm' => 'video/webm', 'png' => 'image/png', 'gif' => 'image/gif');
        $mime = isset($map[$ext]) ? $map[$ext] : 'image/jpeg';
    }
    if ($mime === 'video/quicktime' || $mime === 'video/x-m4v') {
        $mime = 'video/mp4';
    }
    return $mime;
}

//------------------------------------------------------------
function make_derivative_xl($url_original)
{
    include_once(PHPWG_ROOT_PATH . 'include/derivative.inc.php');

    // Convertit une URL absolue en chemin relatif (format utilisé par Piwigo)
    if (preg_match('#^https?://#', $url_original)) {
        $root = get_absolute_root_url();
        $url_original = str_replace($root, '', $url_original);
        $url_original = './' . ltrim($url_original, '/');
    }

    // Cherche l'image dans la table piwigo_images
    $query = '
        SELECT *
        FROM ' . IMAGES_TABLE . '
        WHERE path = "' . pwg_db_real_escape_string($url_original) . '"
        LIMIT 1;';
    $row = pwg_db_fetch_assoc(pwg_query($query));

    if (empty($row)) {
        trigger_error("make_derivative_xl: image non trouvée ($url_original)", E_USER_WARNING);
        return null;
    }

    // Crée l'objet source
    $src_image = new SrcImage($row);

    // Crée le derivative XL et retourne son URL
    $params = ImageStdParams::get_by_type('xlarge');
    $derivative = new DerivativeImage($params, $src_image);

    return $derivative->get_url();
}



// ==========================================================================================
// Création des variables JS   JSUrl et JSMime à partir des assignations

add_event_handler('loc_end_picture', 'castAddVariables');

function castAddVariables()  {
    global $page, $template;

    // Charger le template
    $template->set_filenames(array('cast_footer' => realpath(PHPWG_PLUGINS_PATH . 'castor/template/cast_var.tpl')));

    // Capturer le rendu du template
    ob_start();
    $template->parse('cast_footer');
    $footer_content = ob_get_clean(); // Capture le HTML généré

    // Ajouter ce contenu dans footer_elements
    $template->append('footer_elements', $footer_content);
}

// -----------------------------------------------------------------------------------------------

// Ajout d'un ensemble de boutons en dessous de la photo/vidéo

add_event_handler('loc_begin_picture', 'ajouter_videocontrol');

function ajouter_videocontrol() {



    global $template;
    $template->set_filename('videocontrol', CAST_PATH.'template/video_control.tpl');
    $template->assign('CAST_BT', $template->parse('videocontrol', true));
    // Enregistrement du pré-filtre pour modifier le template
    $template->set_prefilter('picture', 'addButton');
}


function addButton($content) {
    $search = '{$ELEMENT_CONTENT}';  // éléments Image dans picture.tpl
    //$replacement = '{$ELEMENT_CONTENT}{$CAST_BT}';  // place le bouton sous l'image
    $replacement = '{$CAST_BT}{$ELEMENT_CONTENT}';  // place le bouton au dessus de l'image
    return str_replace($search, $replacement, $content);
}

?>
