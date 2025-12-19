<?php
/*
Plugin Name: castor
Version: 3.5
Description: Castor - diffuser vos photos et vidéos sur une télé android ou via un chromecast
Plugin URI: https://piwigo.org/ext/extension_view.php?eid=1001
Author: Charles69
Author URI: 
*/


 // ============  VERSIONS =========================================================
 // historique des versions
 /*
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

//echo ' CAST_PATH= ' , CAST_PATH ;
//echo ' CAST_DIR= ' , CAST_DIR ;



// SCRIPT =======================================================================================
// ajoute les scripts Google js Cast.js et css

add_event_handler('loc_begin_page_tail', 'cast_add_api');
function cast_add_api()
{
    global $page, $template;
    //print_r($page) ;
    
    $template->append('footer_elements', '
    <script src="https://www.gstatic.com/cv/js/sender/v1/cast_sender.js"></script>
    <script src="plugins/castor/cast.js"></script>
    <link rel="stylesheet" href="plugins/castor/css/cast.css" />
    ');
    
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
  global $template,$user;

  $menu = & $menu_ref_arr[0];
 
  if (($block = $menu->get_block( 'mbCast' )) != null) {  
  //echo 'guetblock ok bouton ajouté ' ;

    $template->set_template_dir(CAST_PATH.'template/');
		if ($user['theme'] == 'bootstrapdefault'||$user['theme'] == 'bootstrap_darkroom'){
		  $block->template = 'menubar_cast_etat.tpl';
          //echo 'menu boostrap' ;		
		}else{
		  $block->template = 'menubar_cast_etat.tpl';
		}
} 
  //else { echo 'getblock échoué';} 
}
//-----------------------------------------------------------------------




// Ajout du bouton cast dans la barre d'actions de l'image
add_event_handler('loc_end_picture', 'cast_picture_toolbar');

function cast_picture_toolbar()
{
    global $template, $user;
    
    $template->set_template_dir(CAST_PATH.'template/');
    
    // Choix du template selon le thème
    if ($user['theme'] == 'bootstrapdefault' || $user['theme'] == 'bootstrap_darkroom') {
        $tpl_file = 'picture_cast_Bootstrap.tpl';
    } else {
        $tpl_file = 'picture_cast.tpl';
    }
    
    // Assigner le template au bloc d'actions
    $template->set_filename('cast_button', $tpl_file);
    $template->concat('PLUGIN_PICTURE_ACTIONS', $template->parse('cast_button', true));
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
    //echo gettype($url_picture) ;
    $mime_type = mime_content_type($url_picture) ;
    //echo 'mime type= ' , $mime_type;

    //if (substr($url_original,-3) !== 'mp4') {    // ce n'est pas une vidéo
    if ($mime_type !== 'video/mp4') {    // ce n'est pas une vidéo

      $url_local = make_derivative_xl($url_original);
      $url_local = $url_base . $url_local ;
      //echo 'url_local= ' , $url_local ;
            // Assigner à Smarty
            //$url_original = json_encode($url_original);
            $template->assign('IMG_URL', $url_local); // Assignation dans le template
            $template->assign('MIME_TYPE', $mime_type);  // assignation dans le template

      
    } else {
            // CAS DES VIDEOS - on passe la photo de pwg_representative et le plugin JS VIDEO fait le job !!      
            // Assigner à Smarty
            //$url_original = json_encode($url_original);
            //echo 'vidéo' ;
            $template->assign('IMG_URL', $url_original); // Assignation dans le template
            $template->assign('MIME_TYPE', $mime_type);  // assignation dans le template
    }      
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

    // Crée l’objet source
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
